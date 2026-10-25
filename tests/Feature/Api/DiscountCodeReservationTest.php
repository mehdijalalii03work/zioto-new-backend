<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\Cart;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\DiscountCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

class DiscountCodeReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
    }

    public function test_unpaid_order_does_not_consume_discount_capacity_and_cancelled_order_releases_reservation(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Product',
            'slug' => 'discount-reservation-product',
            'price' => 2_000_000,
            'stock_quantity' => 5,
        ]);
        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);
        $codeId = DB::table('discount_codes')->insertGetId([
            'code' => 'RESERVE1',
            'discount_amount' => 200_000,
            'platforms' => json_encode(['main']),
            'is_active' => true,
            'max_uses' => 1,
            'max_uses_per_customer' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shippingMethod = ShippingMethod::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
            'discount_code' => 'RESERVE1',
        ]);

        $response->assertCreated();
        $order = Order::withoutTenantScope()->latest('id')->firstOrFail();
        $this->assertDatabaseHas('discount_code_usages', [
            'discount_code_id' => $codeId,
            'order_id' => $order->id,
            'status' => 'reserved',
        ]);

        $anotherUser = User::factory()->create();
        Cart::withoutTenantScope()->create([
            'user_id' => $anotherUser->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);
        $overCapacity = $this->withHeaders([
            'Authorization' => 'Bearer '.$anotherUser->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
            'discount_code' => 'RESERVE1',
        ]);
        $overCapacity->assertUnprocessable()->assertJsonPath('error_code', 'DISCOUNT_CODE_INVALID');

        $cancelled = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/orders', [
            'shipping_method_id' => $shippingMethod->id,
            'discount_code' => 'RESERVE1',
        ]);

        $cancelled->assertOk()->assertJsonPath('is_existing', true);
        $this->travel(21)->minutes();
        $this->artisan('orders:cancel-unpaid')->assertExitCode(0);
        $order->refresh();
        $this->assertSame('cancelled', $order->status);

        $remaining = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/discount-codes/validate', ['code' => 'RESERVE1']);

        $remaining->assertOk()->assertJsonPath('eligible', true);
        $this->assertDatabaseHas('discount_code_usages', [
            'discount_code_id' => $codeId,
            'order_id' => $order->id,
            'status' => 'released',
        ]);

        $order->update(['payment_status' => 'paid']);
        $this->assertDatabaseHas('discount_code_usages', [
            'discount_code_id' => $codeId,
            'order_id' => $order->id,
            'status' => 'redeemed',
        ]);
    }

    public function test_paid_order_consumes_discount_capacity(): void
    {
        $codeId = DB::table('discount_codes')->insertGetId([
            'code' => 'PAIDONE',
            'discount_amount' => 200_000,
            'platforms' => json_encode(['main']),
            'is_active' => true,
            'max_uses' => 1,
            'max_uses_per_customer' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'discount_code_id' => $codeId,
            'discount_code_snapshot' => 'PAIDONE',
            'discount_amount' => 200_000,
            'payment_status' => 'pending',
        ]);
        app(DiscountCodeService::class)->reserveUsage($order);
        $order->update(['payment_status' => 'paid']);
        $this->assertDatabaseHas('discount_code_usages', ['order_id' => $order->id, 'status' => 'redeemed']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ])->postJson('/api/discount-codes/validate', ['code' => 'PAIDONE']);

        $response->assertUnprocessable()->assertJsonPath('error_code', 'DISCOUNT_CODE_INVALID');
        $this->assertDatabaseCount('discount_code_usages', 1);
    }
}
