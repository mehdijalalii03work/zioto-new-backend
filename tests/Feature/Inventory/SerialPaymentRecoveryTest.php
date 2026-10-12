<?php

namespace Tests\Feature\Inventory;

use App\Jobs\ReconcilePaidSerialOrder;
use App\Models\ProductSerial;
use App\Models\SerialEvent;
use App\Payment\Exceptions\TerminalPaymentRejectedException;
use App\Services\Inventory\SerialInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shetabit\Multipay\Payment as ShetabitPayment;
use Shetabit\Multipay\Receipt;
use Tests\TestCase;

/**
 * Stage 5 — payment wiring and recovery (plan §5.3, §5.4).
 */
class SerialPaymentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeSerialProduct(string $prefix = 'ton'): Product
    {
        return Product::factory()->serial($prefix)->create([
            'name' => 'شمش '.$prefix,
            'slug' => 'serial-'.$prefix.'-'.uniqid(),
        ]);
    }

    private function addSerials(Product $product, int $count, string $prefix = 'ton'): void
    {
        for ($i = 1; $i <= $count; $i++) {
            ProductSerial::create([
                'product_id' => $product->id,
                'serial_code' => $prefix.str_pad((string) (70000000 + $i), 8, '0', STR_PAD_LEFT),
                'serial_prefix' => $prefix,
                'status' => 'available',
            ]);
        }
    }

    private function makeSerialOrder(Product $product, int $quantity, array $overrides = []): Order
    {
        $order = OrderFactory::new()->create(array_merge([
            'platform' => 'main',
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 1000000 * $quantity,
            'serial_inventory_enabled' => true,
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => $quantity,
            'subtotal' => 1000000 * $quantity,
        ]);

        return $order;
    }

    private function mockSuccessfulPurchase(): void
    {
        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('callbackUrl')->andReturnSelf();
        $mock->shouldReceive('purchase')->andReturnUsing(function ($invoice, $callback) {
            $callback(null, 'test-txn-'.uniqid());

            return new class
            {
                public function pay()
                {
                    return new class
                    {
                        public function getAction()
                        {
                            return 'https://example.com/pay';
                        }
                    };
                }
            };
        });
    }

    private function mockSuccessfulVerify(string $reference = 'REF-123'): void
    {
        $receipt = Mockery::mock(Receipt::class);
        $receipt->shouldReceive('getReferenceId')->andReturn($reference);
        $receipt->shouldReceive('getDetails')->andReturn([]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andReturn($receipt);
    }

    #[RunInSeparateProcess]
    public function test_init_claims_payment_and_reserves_serials(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makeSerialOrder($product, 2);

        $this->mockSuccessfulPurchase();

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertOk();

        $this->assertSame(1, Payment::where('order_id', $order->id)->where('status', 'pending')->count());
        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'reserved')->count());
        $this->assertSame('reserved', $order->fresh()->serial_fulfillment_status->value);
    }

    public function test_second_init_while_initiating_is_rejected(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makeSerialOrder($product, 2);

        Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'platform' => 'main',
            'transaction_id' => 'init-'.uniqid(),
            'amount' => $order->total_amount,
            'status' => 'initiating',
            'gateway' => 'parsian',
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'PAYMENT_ALREADY_INITIATED']);
        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
    }

    public function test_init_shortage_rolls_back_payment_claim(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeSerialProduct();
        $order = $this->makeSerialOrder($product, 1);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'INSUFFICIENT_STOCK']);
        $this->assertSame(0, Payment::where('order_id', $order->id)->count());
        $this->assertSame(0, SerialEvent::where('order_id', $order->id)->count());
    }

    #[RunInSeparateProcess]
    public function test_purchase_failure_marks_payment_failed_and_releases_holdings(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makeSerialOrder($product, 2);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('callbackUrl')->andReturnSelf();
        $mock->shouldReceive('purchase')->andThrow(new TerminalPaymentRejectedException('parsian', 'trusted-test-decline', ['verified_terminal_rejection' => true]));

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertStatus(500);

        $this->assertSame('failed', Payment::where('order_id', $order->id)->firstOrFail()->status);
        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
    }

    #[RunInSeparateProcess]
    public function test_callback_success_finalizes_serials(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makeSerialOrder($product, 2);

        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'platform' => 'main',
            'transaction_id' => 'test-txn-'.uniqid(),
            'amount' => $order->total_amount,
            'status' => 'pending',
            'gateway' => 'digipay',
        ]);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');

        $this->mockSuccessfulVerify();

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'Token' => $payment->transaction_id,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/confirm?order_id='.$order->id, $response->headers->get('Location'));

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'allocated')->count());
        $this->assertSame('allocated', $order->fresh()->serial_fulfillment_status->value);
    }

    #[RunInSeparateProcess]
    public function test_duplicate_callback_is_idempotent(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makeSerialOrder($product, 1);

        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'platform' => 'main',
            'transaction_id' => 'test-txn-'.uniqid(),
            'amount' => $order->total_amount,
            'status' => 'pending',
            'gateway' => 'digipay',
        ]);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');

        $this->mockSuccessfulVerify();

        $url = "/api/payment/callback/{$order->id}/digipay";
        $this->post($url, ['Token' => $payment->transaction_id])->assertRedirect();
        $this->post($url, ['Token' => $payment->transaction_id])->assertRedirect();

        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'allocated')->count());
        $this->assertSame(1, SerialEvent::where('order_id', $order->id)->where('event_type', 'allocated')->count());
    }

    #[RunInSeparateProcess]
    public function test_second_attempt_failure_after_paid_keeps_order_paid(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makeSerialOrder($product, 1, ['payment_status' => 'paid', 'status' => 'confirmed']);

        Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'platform' => 'main',
            'transaction_id' => 'paid-txn-'.uniqid(),
            'amount' => $order->total_amount,
            'status' => 'paid',
            'gateway' => 'digipay',
        ]);

        $retry = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'platform' => 'main',
            'transaction_id' => 'retry-txn-'.uniqid(),
            'amount' => $order->total_amount,
            'status' => 'pending',
            'gateway' => 'digipay',
        ]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andThrow(new TerminalPaymentRejectedException('digipay', 'trusted-test-decline', ['verified_terminal_rejection' => true]));

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'Token' => $retry->transaction_id,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/confirm?order_id='.$order->id, $response->headers->get('Location'));

        $this->assertSame('failed', $retry->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    #[RunInSeparateProcess]
    public function test_cancelled_late_callback_records_paid_without_reactivation(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makeSerialOrder($product, 1, ['status' => 'cancelled']);

        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'platform' => 'main',
            'transaction_id' => 'test-txn-'.uniqid(),
            'amount' => $order->total_amount,
            'status' => 'pending',
            'gateway' => 'digipay',
        ]);

        $this->mockSuccessfulVerify();

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'Token' => $payment->transaction_id,
        ]);

        $response->assertRedirect();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('needs_review', $order->fresh()->serial_fulfillment_status->value);
        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
    }

    public function test_reconcile_job_finalizes_interrupted_order(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makeSerialOrder($product, 2, ['payment_status' => 'paid', 'status' => 'confirmed']);

        // Crash between paid truth and finalization: reservation exists, paid, nothing allocated.
        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');
        $order->update(['payment_status' => 'paid']);

        ReconcilePaidSerialOrder::dispatch($order->id, 'main');

        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'allocated')->count());
        $this->assertSame('allocated', $order->fresh()->serial_fulfillment_status->value);
    }

    public function test_reconcile_command_sweeps_incomplete_paid_orders(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makeSerialOrder($product, 1, ['payment_status' => 'paid', 'status' => 'confirmed']);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');
        $order->update(['payment_status' => 'paid']);

        $this->artisan('serials:reconcile-paid')->assertSuccessful();

        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'allocated')->count());
    }

    #[RunInSeparateProcess]
    public function test_tapsi_init_skips_serial_checks(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeSerialProduct();
        $order = $this->makeSerialOrder($product, 2, ['platform' => 'tapsi']);

        $this->mockSuccessfulPurchase();

        $response = $this->withHeaders(['X-Platform' => 'tapsi'])->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertOk();
        $this->assertSame(0, SerialEvent::where('order_id', $order->id)->count());
    }

    public function test_cancel_unpaid_releases_serial_holdings(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makeSerialOrder($product, 1);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');
        $this->travel(21)->minutes();

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
    }

    #[RunInSeparateProcess]
    public function test_ambiguous_verification_keeps_hold_and_later_callback_can_retry(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makeSerialOrder($product, 1);
        $payment = Payment::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'platform' => 'main', 'transaction_id' => 'ambiguous-txn', 'amount' => $order->total_amount, 'status' => 'pending', 'gateway' => 'digipay']);
        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main', $payment->id);
        $receipt = Mockery::mock(Receipt::class);
        $receipt->shouldReceive('getReferenceId')->andReturn('RETRY-REF');
        $receipt->shouldReceive('getDetails')->andReturn([]);
        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $attempts = 0;
        $mock->shouldReceive('verify')->andReturnUsing(function () use (&$attempts, $receipt) {
            if (++$attempts === 1) {
                throw new \RuntimeException('network timeout');
            }

            return $receipt;
        });
        $url = "/api/payment/callback/{$order->id}/digipay";

        $this->post($url, ['Token' => $payment->transaction_id])->assertRedirect();

        $this->assertSame('processing', $payment->fresh()->status);
        $this->assertSame('needs_review', $order->fresh()->serial_fulfillment_status->value);
        $this->assertSame(1, ProductSerial::where('status', 'reserved')->count());

        $this->post($url, ['Token' => $payment->transaction_id])->assertRedirect();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('allocated', $order->fresh()->serial_fulfillment_status->value);
    }

    #[RunInSeparateProcess]
    public function test_callback_during_active_verification_does_not_verify_again(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makeSerialOrder($product, 1);
        $payment = Payment::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'platform' => 'main', 'transaction_id' => 'processing-txn', 'amount' => $order->total_amount, 'status' => 'processing', 'gateway' => 'digipay']);
        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main', $payment->id);
        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldNotReceive('verify');

        $this->post("/api/payment/callback/{$order->id}/digipay", ['Token' => $payment->transaction_id])->assertRedirect();

        $this->assertSame('processing', $payment->fresh()->status);
        $this->assertSame(1, ProductSerial::where('status', 'reserved')->count());
    }

    public function test_disabled_rollout_rejects_new_payment_without_claim(): void
    {
        config(['serial_inventory.enabled' => false]);
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makeSerialOrder($product, 1);

        $this->postJson('/api/payment/init', ['order_id' => $order->id, 'gateway' => 'parsian'])->assertServiceUnavailable()->assertJson(['error_code' => 'INVENTORY_NOT_READY']);

        $this->assertSame(0, Payment::count());
        $this->assertSame(1, ProductSerial::where('status', 'available')->count());
    }

    #[RunInSeparateProcess]
    public function test_legacy_paid_callback_does_not_consume_current_stock(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makeSerialOrder($product, 1, ['serial_inventory_enabled' => false]);
        $payment = Payment::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'platform' => 'main', 'transaction_id' => 'legacy-txn', 'amount' => $order->total_amount, 'status' => 'pending', 'gateway' => 'digipay']);
        $this->mockSuccessfulVerify();

        $this->post("/api/payment/callback/{$order->id}/digipay", ['Token' => $payment->transaction_id])->assertRedirect();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(1, ProductSerial::where('status', 'available')->count());
    }

    #[RunInSeparateProcess]
    public function test_ambiguous_purchase_after_token_save_retains_holds_for_review(): void
    {
        config(['serial_inventory.enabled' => true]);
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makeSerialOrder($product, 1);
        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('callbackUrl')->andReturnSelf();
        $mock->shouldReceive('purchase')->andReturnUsing(function ($invoice, $callback) {
            $callback(null, 'saved-before-timeout');

            throw new \RuntimeException('purchase timeout');
        });

        $this->postJson('/api/payment/init', ['order_id' => $order->id, 'gateway' => 'parsian'])->assertStatus(500);
        $this->travel(21)->minutes();
        app(SerialInventoryService::class)->releaseExpiredReservation($order->id, 'main');

        $this->assertSame(1, ProductSerial::where('status', 'reserved')->count());
        $this->assertSame('needs_review', $order->fresh()->serial_fulfillment_status->value);
    }
}
