<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserAddress;
use App\Services\DiscountCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;
use Shetabit\Multipay\Payment as ShetabitPayment;
use Tests\TestCase;

class PaymentControllerInitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_init_rejects_already_paid_order(): void
    {
        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'total_amount' => 100000,
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error_code' => 'ORDER_ALREADY_PAID',
        ]);
    }

    public function test_init_rejects_cancelled_order(): void
    {
        $order = Order::factory()->create([
            'status' => 'cancelled',
            'payment_status' => 'pending',
            'total_amount' => 100000,
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error_code' => 'ORDER_NOT_ACTIVE',
        ]);
    }

    public function test_init_rejects_expired_order(): void
    {
        $order = Order::factory()->create([
            'status' => 'expired',
            'payment_status' => 'pending',
            'total_amount' => 100000,
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error_code' => 'ORDER_NOT_ACTIVE',
        ]);
    }

    public function test_init_rejects_duplicate_pending_payment(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 100000,
        ]);

        Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'existing-txn',
            'amount' => 100000,
            'payment_method' => 'online',
            'gateway' => 'parsian',
            'status' => 'pending',
            'description' => 'در انتظار پرداخت',
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error_code' => 'PAYMENT_ALREADY_INITIATED',
        ]);
    }

    #[RunInSeparateProcess]
    public function test_digipay_attempt_is_reserved_before_gateway_request_to_reject_parallel_init(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 100000,
        ]);

        $nestedResponse = null;
        $nestedRequestSent = false;

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('callbackUrl')->andReturnSelf();
        $mock->shouldReceive('purchase')->andReturnUsing(function ($invoice, $callback) use ($order, &$nestedRequestSent, &$nestedResponse) {
            if (! $nestedRequestSent) {
                $nestedRequestSent = true;
                $nestedResponse = $this->postJson('/api/payment/init', [
                    'order_id' => $order->id,
                    'gateway' => 'digipay',
                ]);
            }

            $callback(null, 'digipay-ticket-'.uniqid());

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

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'digipay',
        ]);

        $response->assertOk();
        $this->assertNotNull($nestedResponse);
        $nestedResponse->assertUnprocessable();
        $nestedResponse->assertJson(['error_code' => 'PAYMENT_ALREADY_INITIATED']);
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    #[RunInSeparateProcess]
    public function test_init_transport_error_does_not_overwrite_a_callback_that_already_marked_payment_paid(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 100000,
        ]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('callbackUrl')->andReturnSelf();
        $mock->shouldReceive('purchase')->andReturnUsing(function ($invoice, $callback) {
            $callback(null, 'digipay-ticket-before-error');
            Payment::where('provider_id', $invoice->getUuid())->update(['status' => 'paid']);

            throw new \RuntimeException('Connection timed out after provider accepted purchase');
        });

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'digipay',
        ]);

        $response->assertStatus(500);
        $this->assertSame('paid', Payment::where('order_id', $order->id)->firstOrFail()->status);
    }

    #[RunInSeparateProcess]
    public function test_definitive_digipay_init_failure_fails_order_and_releases_discount_reservation(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 100000,
        ]);
        $discountCodeId = DB::table('discount_codes')->insertGetId([
            'code' => 'INITFAIL',
            'discount_amount' => 1000,
            'platforms' => json_encode(['main']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $order->update([
            'discount_code_id' => $discountCodeId,
            'discount_code_snapshot' => 'INITFAIL',
            'discount_amount' => 1000,
        ]);
        app(DiscountCodeService::class)->reserveUsage($order);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('callbackUrl')->andReturnSelf();
        $mock->shouldReceive('purchase')->andThrow(new PurchaseFailedException('purchase rejected'));

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'digipay',
        ]);

        $response->assertStatus(500);
        $this->assertSame('failed', Payment::where('order_id', $order->id)->firstOrFail()->status);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertDatabaseHas('discount_code_usages', [
            'order_id' => $order->id,
            'status' => 'released',
        ]);
    }

    public function test_init_rejects_an_order_with_an_unresolved_digipay_payment(): void
    {
        foreach (['verification_pending', 'needs_review'] as $paymentStatus) {
            $order = Order::factory()->create([
                'status' => 'pending',
                'payment_status' => 'pending',
                'total_amount' => 100000,
            ]);

            Payment::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'transaction_id' => 'unresolved-'.$paymentStatus,
                'amount' => $order->total_amount,
                'payment_method' => 'online',
                'gateway' => 'digipay',
                'status' => $paymentStatus,
                'description' => 'در حال بررسی پرداخت',
            ]);

            $response = $this->postJson('/api/payment/init', [
                'order_id' => $order->id,
                'gateway' => 'parsian',
            ]);

            $response->assertUnprocessable();
            $response->assertJson(['error_code' => 'PAYMENT_ALREADY_INITIATED']);
        }
    }

    public function test_init_allows_new_payment_for_order_with_previous_failed_payment(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'failed',
            'total_amount' => 100000,
        ]);

        Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'failed-txn',
            'amount' => 100000,
            'payment_method' => 'online',
            'gateway' => 'parsian',
            'status' => 'failed',
            'description' => 'پرداخت ناموفق',
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $this->assertNotEquals(422, $response->getStatusCode(), 'Init should allow retry after failed payment');
    }

    public function test_init_rejects_insufficient_stock(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);

        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 100000,
        ]);

        $product = Product::create([
            'name' => 'شمش بدون موجودی',
            'slug' => 'zero-stock-init-'.uniqid(),
            'price' => 100000,
            'stock_quantity' => 0,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => 1,
            'subtotal' => $product->price,
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error_code' => 'INSUFFICIENT_STOCK',
        ]);
        $response->assertJsonPath('products.0.available', 0);
        $response->assertJsonPath('products.0.requested', 1);
    }

    #[RunInSeparateProcess]
    public function test_init_uses_buyer_phone_not_recipient_phone(): void
    {
        $buyerPhone = '09121234567';
        $recipientPhone = '09999999999';

        $user = User::factory()->create(['phone' => $buyerPhone]);

        $address = UserAddress::factory()->create([
            'user_id' => $user->id,
            'receiver_phone' => $recipientPhone,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'user_address_id' => $address->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 100000,
        ]);

        $capturedInvoice = null;

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('callbackUrl')->andReturnSelf();
        $mock->shouldReceive('purchase')->andReturnUsing(function ($invoice, $callback) use (&$capturedInvoice) {
            $capturedInvoice = $invoice;
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

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertOk();

        $this->assertNotNull($capturedInvoice, 'Invoice should have been captured');
        $this->assertEquals(
            $buyerPhone,
            $capturedInvoice->getDetail('phone'),
            'Payment should use buyer phone, not recipient phone'
        );
        $this->assertNotEquals(
            $recipientPhone,
            $capturedInvoice->getDetail('phone'),
            'Payment should NOT use recipient phone'
        );
    }
}
