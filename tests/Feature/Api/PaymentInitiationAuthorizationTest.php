<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shetabit\Multipay\Invoice;
use Shetabit\Multipay\Payment as ShetabitPayment;
use Tests\TestCase;

class PaymentInitiationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public static function gatewayCases(): array
    {
        return [
            'parsian' => ['parsian', 'main'],
            'digipay' => ['digipay', 'main'],
            'kamanlend' => ['kamanlend', 'main'],
            'smartis' => ['smartis', 'main'],
            'nopay' => ['nopay', 'nopay'],
        ];
    }

    #[DataProvider('gatewayCases')]
    #[RunInSeparateProcess]
    public function test_owned_order_preserves_gateway_contract(string $gateway, string $platform): void
    {
        config([
            'app.frontend_url' => 'https://store.example.test',
            'app.nopay_frontend_url' => 'https://nopay.example.test',
            'payment.drivers.nopay.callbackUrl' => 'https://merchant.example.test/api/payment/callback',
        ]);

        $user = User::factory()->create([
            'platform' => $platform,
            'phone' => '09120000001',
            'national_code' => '0012345678',
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'platform' => $platform,
            'total_amount' => 100000,
        ]);
        $product = Product::create([
            'name' => 'Eligible payment product',
            'slug' => 'eligible-payment-product',
            'price' => 100000,
            'stock_quantity' => 10,
            'is_nopay' => true,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 100000,
            'quantity' => 1,
            'subtotal' => 100000,
        ]);

        $capturedInvoice = null;
        $purchaseCalls = 0;
        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->with($gateway)->andReturnSelf();
        $mock->shouldReceive('callbackUrl')->with(Mockery::on(
            fn (string $url): bool => $gateway === 'nopay'
                ? $url === 'https://merchant.example.test/api/payment/callback'
                : str_ends_with($url, '/api/payment/callback/'.$order->id.'/'.$gateway)
        ))->andReturnSelf();
        $mock->shouldReceive('purchase')->andReturnUsing(function (Invoice $invoice, callable $callback) use (&$capturedInvoice, &$purchaseCalls, $gateway) {
            $purchaseCalls++;
            $capturedInvoice = $invoice;
            $callback(null, 'transaction-'.$gateway);

            return new class
            {
                public function pay(): object
                {
                    return new class
                    {
                        public function getAction(): string
                        {
                            return 'https://gateway.example.test/pay';
                        }
                    };
                }
            };
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => $platform,
        ])->postJson('/api/payment/init', ['order_id' => $order->id, 'gateway' => $gateway]);

        $response->assertOk();
        $this->assertSame(1, $purchaseCalls);
        $this->assertSame([
            'payment_url' => 'https://gateway.example.test/pay',
            'transaction_id' => 'transaction-'.$gateway,
        ], $response->json());
        $this->assertSame('100000', $capturedInvoice->getAmount());
        $this->assertSame($order->id, $capturedInvoice->getDetail('orderId'));
        $this->assertSame('09120000001', $capturedInvoice->getDetail('phone'));
        $this->assertSame(
            ($platform === 'nopay' ? 'https://nopay.example.test' : 'https://store.example.test').'/confirm?order_id='.$order->id,
            $capturedInvoice->getDetail('redirectionUrl')
        );
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'platform' => $platform,
            'gateway' => $gateway,
            'status' => 'pending',
            'transaction_id' => 'transaction-'.$gateway,
        ]);
    }

    #[RunInSeparateProcess]
    public function test_another_users_order_cannot_replace_its_pending_payment(): void
    {
        $order = Order::factory()->create();
        $intruder = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'platform' => 'main',
            'transaction_id' => 'existing-owner-attempt',
            'amount' => $order->total_amount,
            'payment_method' => 'online',
            'gateway' => 'parsian',
            'status' => 'pending',
        ]);
        $provider = Mockery::mock('overload:'.ShetabitPayment::class);
        $provider->shouldNotReceive('via');
        $provider->shouldNotReceive('purchase');

        $this->withHeaders(['Authorization' => 'Bearer '.$intruder->api_token])
            ->postJson('/api/payment/init', ['order_id' => $order->id, 'gateway' => 'parsian'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_missing_bearer_is_rejected_before_an_attempt_is_created(): void
    {
        $order = Order::factory()->create();

        $this->postJson('/api/payment/init', ['order_id' => $order->id, 'gateway' => 'parsian'])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'TOKEN_MISSING');

        $this->assertSame(0, Payment::where('order_id', $order->id)->count());
    }

    public function test_invalid_bearer_is_rejected_before_an_attempt_is_created(): void
    {
        $order = Order::factory()->create();

        $this->withHeaders(['Authorization' => 'Bearer invalid-test-token'])
            ->postJson('/api/payment/init', ['order_id' => $order->id, 'gateway' => 'parsian'])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'TOKEN_INVALID');

        $this->assertSame(0, Payment::where('order_id', $order->id)->count());
    }

    public function test_expired_bearer_is_rejected_before_an_attempt_is_created(): void
    {
        $user = User::factory()->create(['token_created_at' => now()->subHours(73)]);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->withHeaders(['Authorization' => 'Bearer '.$user->api_token])
            ->postJson('/api/payment/init', ['order_id' => $order->id, 'gateway' => 'parsian'])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'TOKEN_EXPIRED');

        $this->assertSame(0, Payment::where('order_id', $order->id)->count());
    }

    public function test_nopay_bearer_cannot_initiate_a_main_order(): void
    {
        $mainOrder = Order::factory()->create();
        $nopayUser = User::factory()->nopay()->create();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$nopayUser->api_token,
            'X-Platform' => 'nopay',
        ])->postJson('/api/payment/init', ['order_id' => $mainOrder->id, 'gateway' => 'nopay'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);

        $this->assertSame(0, Payment::withoutTenantScope()->where('order_id', $mainOrder->id)->count());
    }

    public function test_main_bearer_with_forged_nopay_header_is_rejected(): void
    {
        $mainOrder = Order::factory()->create();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$mainOrder->user->api_token,
            'X-Platform' => 'nopay',
        ])->postJson('/api/payment/init', ['order_id' => $mainOrder->id, 'gateway' => 'nopay'])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'TOKEN_INVALID');

        $this->assertSame(0, Payment::withoutTenantScope()->where('order_id', $mainOrder->id)->count());
    }

    public function test_unknown_order_has_the_same_validation_response_as_foreign_order(): void
    {
        $user = User::factory()->create();

        $this->withHeaders(['Authorization' => 'Bearer '.$user->api_token])
            ->postJson('/api/payment/init', ['order_id' => 999999, 'gateway' => 'parsian'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_id'])
            ->assertJsonPath('errors.order_id.0', 'سفارش یافت نشد');
    }

    public function test_order_with_same_owner_id_but_other_platform_is_rejected(): void
    {
        $mainUser = User::factory()->create();
        $nopayOrder = Order::factory()->create([
            'user_id' => $mainUser->id,
            'platform' => 'nopay',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$mainUser->api_token])
            ->postJson('/api/payment/init', ['order_id' => $nopayOrder->id, 'gateway' => 'parsian'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);

        $this->assertSame(0, Payment::withoutTenantScope()->where('order_id', $nopayOrder->id)->count());
    }

    public function test_nopay_bearer_without_nopay_header_is_rejected(): void
    {
        $nopayUser = User::factory()->nopay()->create();
        $nopayOrder = Order::factory()->create([
            'user_id' => $nopayUser->id,
            'platform' => 'nopay',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$nopayUser->api_token])
            ->postJson('/api/payment/init', ['order_id' => $nopayOrder->id, 'gateway' => 'nopay'])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'TOKEN_INVALID');

        $this->assertSame(0, Payment::withoutTenantScope()->where('order_id', $nopayOrder->id)->count());
    }

    public function test_order_without_an_owner_cannot_be_initiated_by_a_customer(): void
    {
        $user = User::factory()->create();
        $unownedOrder = Order::factory()->create(['user_id' => null]);

        $this->withHeaders(['Authorization' => 'Bearer '.$user->api_token])
            ->postJson('/api/payment/init', ['order_id' => $unownedOrder->id, 'gateway' => 'parsian'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);

        $this->assertSame(0, Payment::where('order_id', $unownedOrder->id)->count());
    }

    #[RunInSeparateProcess]
    public function test_owner_retry_replaces_only_the_previous_pending_attempt(): void
    {
        $order = Order::factory()->create(['total_amount' => 100000]);
        $previous = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'platform' => 'main',
            'transaction_id' => 'old-pending-transaction',
            'amount' => 100000,
            'payment_method' => 'online',
            'gateway' => 'parsian',
            'status' => 'pending',
        ]);
        $failed = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'platform' => 'main',
            'transaction_id' => 'older-failed-transaction',
            'amount' => 100000,
            'payment_method' => 'online',
            'gateway' => 'parsian',
            'status' => 'failed',
        ]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->with('parsian')->andReturnSelf();
        $mock->shouldReceive('callbackUrl')->andReturnSelf();
        $mock->shouldReceive('purchase')->andReturnUsing(function (Invoice $invoice, callable $callback) {
            $callback(null, 'new-pending-transaction');

            return new class
            {
                public function pay(): object
                {
                    return new class
                    {
                        public function getAction(): string
                        {
                            return 'https://gateway.example.test/retry';
                        }
                    };
                }
            };
        });

        $this->withHeaders(['Authorization' => 'Bearer '.$order->user->api_token])
            ->postJson('/api/payment/init', ['order_id' => $order->id, 'gateway' => 'parsian'])
            ->assertOk()
            ->assertJsonPath('payment_url', 'https://gateway.example.test/retry');

        $this->assertSame('failed', $previous->fresh()->status);
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'transaction_id' => 'new-pending-transaction',
            'status' => 'pending',
        ]);
        $this->assertSame(3, Payment::where('order_id', $order->id)->count());
    }
}
