<?php

namespace Tests\Feature\Inventory;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\Cart;
use App\Models\ProductSerial;
use App\Models\ProductStockReservation;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Payment\Exceptions\TerminalPaymentRejectedException;
use App\Services\Inventory\SerialInventoryService;
use App\Services\Inventory\SerialStockShortageException;
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
 * Stage 6 — manual (made-to-order / capacity) inventory (plan §4.7).
 */
class ManualInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['serial_inventory.enabled' => true]);
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mainHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ];
    }

    private function makeManualProduct(int $stock = 2, bool $madeToOrder = true, bool $trackSerials = false): Product
    {
        return Product::factory()->create([
            'name' => 'شمش یک کیلویی دستی',
            'slug' => 'manual-1kg-'.uniqid(),
            'stock_quantity' => $stock,
            'made_to_order' => $madeToOrder,
            'track_serials' => $trackSerials,
            'production_lead_time_text' => '۱۰ روز کاری',
        ]);
    }

    private function orderViaCart(User $user, Product $product, int $quantity)
    {
        $method = ShippingMethod::factory()->create(['is_active' => true]);

        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'platform' => 'main',
        ]);

        return $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/orders', ['shipping_method_id' => $method->id]);
    }

    private function makePaidManualOrder(Product $product, int $quantity): array
    {
        $order = OrderFactory::new()->create([
            'platform' => 'main',
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'serial_inventory_enabled' => true,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => $quantity,
            'subtotal' => 1000000 * $quantity,
            'inventory_mode' => $product->inventory_mode->value,
            'serial_tracking_required' => $product->track_serials,
            'made_to_order' => $product->made_to_order,
            'production_lead_time_text' => $product->production_lead_time_text,
        ]);

        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'platform' => 'main',
            'transaction_id' => 'txn-'.uniqid(),
            'amount' => 1000000 * $quantity,
            'status' => 'pending',
            'gateway' => 'parsian',
        ]);

        return [$order, $item, $payment];
    }

    public function test_manual_product_with_capacity_is_orderable(): void
    {
        $product = $this->makeManualProduct(2);
        $user = User::factory()->create();

        $this->orderViaCart($user, $product, 2)->assertCreated();
    }

    public function test_order_beyond_capacity_is_rejected(): void
    {
        $product = $this->makeManualProduct(2);
        $user = User::factory()->create();

        $response = $this->orderViaCart($user, $product, 3);

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'INSUFFICIENT_STOCK']);
        $response->assertJsonPath('products.0.available', 2);
        $response->assertJsonPath('products.0.requested', 3);
    }

    public function test_second_order_beyond_remaining_capacity_is_rejected(): void
    {
        $product = $this->makeManualProduct(2);

        [$firstOrder] = $this->makePaidManualOrder($product, 2);
        [$secondOrder] = $this->makePaidManualOrder($product, 2);

        $firstPayment = Payment::where('order_id', $firstOrder->id)->firstOrFail();
        $secondPayment = Payment::where('order_id', $secondOrder->id)->firstOrFail();

        app(SerialInventoryService::class)->reserveForPayment($firstOrder->id, 'main', $firstPayment->id);

        try {
            app(SerialInventoryService::class)->reserveForPayment($secondOrder->id, 'main', $secondPayment->id);
            $this->fail('Expected SerialStockShortageException for over-capacity reservation.');
        } catch (SerialStockShortageException $e) {
            $this->assertSame($product->id, $e->getProductId());
        }

        $this->assertSame(1, ProductStockReservation::where('product_id', $product->id)->where('status', 'reserved')->count());
    }

    #[RunInSeparateProcess]
    public function test_callback_consumes_capacity_once(): void
    {
        $product = $this->makeManualProduct(5, false);
        [$order, $item, $payment] = $this->makePaidManualOrder($product, 2);
        $order->update(['payment_status' => 'pending', 'status' => 'pending']);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main', $payment->id);

        $receipt = Mockery::mock(Receipt::class);
        $receipt->shouldReceive('getReferenceId')->andReturn('REF-MANUAL-1');
        $receipt->shouldReceive('getDetails')->andReturn([]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andReturn($receipt);

        $url = "/api/payment/callback/{$order->id}/parsian";
        $this->post($url, ['Token' => $payment->transaction_id, 'status' => '0'])->assertRedirect();
        $this->post($url, ['Token' => $payment->transaction_id, 'status' => '0'])->assertRedirect();

        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
        $this->assertSame('consumed', ProductStockReservation::where('order_item_id', $item->id)->firstOrFail()->status->value);
    }

    #[RunInSeparateProcess]
    public function test_failed_payment_releases_manual_reservation(): void
    {
        config(['serial_inventory.enabled' => true]);

        $product = $this->makeManualProduct(5, false);
        $user = User::factory()->create();

        $method = ShippingMethod::factory()->create(['is_active' => true]);
        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'platform' => 'main',
        ]);

        $orderId = $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/orders', ['shipping_method_id' => $method->id])
            ->assertCreated()
            ->json('order.id');

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('callbackUrl')->andReturnSelf();
        $mock->shouldReceive('purchase')->andThrow(new TerminalPaymentRejectedException('parsian', 'trusted-test-decline', ['verified_terminal_rejection' => true]));

        $this->postJson('/api/payment/init', ['order_id' => $orderId, 'gateway' => 'parsian'])
            ->assertStatus(500);

        $this->assertSame(5, (int) $product->fresh()->stock_quantity);
        $this->assertSame(0, ProductStockReservation::where('product_id', $product->id)->where('status', 'reserved')->count());
    }

    public function test_production_then_second_finalize_does_not_double_decrement(): void
    {
        $product = $this->makeManualProduct(5, true, true);
        [$order, $item, $payment] = $this->makePaidManualOrder($product, 2);

        $service = app(SerialInventoryService::class);
        $service->reserveForPayment($order->id, 'main', $payment->id);
        $service->finalizePaidOrder($order->id, 'main');

        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
        $this->assertSame('awaiting_production', $order->fresh()->serial_fulfillment_status->value);

        // Bars are produced later and imported; a repeated finalize must not
        // consume capacity again (production allocation lands in stage 7).
        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst70000001',
            'serial_prefix' => 'tst',
            'status' => 'available',
        ]);
        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst70000002',
            'serial_prefix' => 'tst',
            'status' => 'available',
        ]);

        $service->finalizePaidOrder($order->id, 'main');

        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
    }

    public function test_mode_snapshot_protects_finalization_after_product_switch(): void
    {
        $product = $this->makeManualProduct(5, false);
        [$order, $item, $payment] = $this->makePaidManualOrder($product, 1);

        $item->update(['inventory_mode' => 'manual', 'serial_tracking_required' => false]);

        $service = app(SerialInventoryService::class);
        $service->reserveForPayment($order->id, 'main', $payment->id);

        // The product switches to serial mode afterwards; the paid order keeps
        // its manual contract from the snapshot.
        $product->update(['inventory_mode' => 'serial', 'serial_prefix' => 'tst', 'track_serials' => true]);

        $service->finalizePaidOrder($order->id, 'main');

        $this->assertSame(4, (int) $product->fresh()->stock_quantity);
        $this->assertSame('allocated', $order->fresh()->serial_fulfillment_status->value);
    }

    public function test_disabled_rollout_rejects_checkout_without_creating_an_order(): void
    {
        config(['serial_inventory.enabled' => false]);
        $product = $this->makeManualProduct(2);
        $user = User::factory()->create();

        $this->orderViaCart($user, $product, 1)->assertServiceUnavailable()->assertJson(['error_code' => 'INVENTORY_NOT_READY']);

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_snapshots_production_terms(): void
    {
        $product = $this->makeManualProduct(2, true, true);
        $user = User::factory()->create();

        $orderId = $this->orderViaCart($user, $product, 1)->assertCreated()->json('order.id');

        $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'made_to_order' => true, 'production_lead_time_text' => '۱۰ روز کاری', 'serial_tracking_required' => true]);
    }
}
