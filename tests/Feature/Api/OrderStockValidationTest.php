<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\Cart;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

class OrderStockValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
    }

    private function mainHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ];
    }

    public function test_store_rejects_zero_stock_cart(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);

        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'شمش بدون موجودی',
            'slug' => 'zero-stock-store-'.uniqid(),
            'price' => 1000000,
            'stock_quantity' => 0,
        ]);
        $method = ShippingMethod::factory()->create(['is_active' => true]);

        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);

        $response = $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/orders', [
                'shipping_method_id' => $method->id,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'INSUFFICIENT_STOCK']);
        $response->assertJsonPath('products.0.available', 0);
        $response->assertJsonPath('products.0.requested', 1);
        $this->assertSame(0, Order::withoutTenantScope()->where('user_id', $user->id)->count());
    }

    public function test_store_rejects_negative_physical_stock(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);

        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'شمش موجودی منفی',
            'slug' => 'negative-stock-store-'.uniqid(),
            'price' => 1000000,
            'stock_quantity' => 0,
            'hesabfa_physical_stock' => -1,
        ]);
        $method = ShippingMethod::factory()->create(['is_active' => true]);

        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);

        $response = $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/orders', [
                'shipping_method_id' => $method->id,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'INSUFFICIENT_STOCK']);
        // Negative stock is clamped to zero, never treated as available.
        $response->assertJsonPath('products.0.available', 0);
        $this->assertSame(0, Order::withoutTenantScope()->where('user_id', $user->id)->count());
    }

    public function test_store_accepts_in_stock_cart(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);

        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'شمش موجود',
            'slug' => 'in-stock-store-'.uniqid(),
            'price' => 1000000,
            'stock_quantity' => 10,
        ]);
        $method = ShippingMethod::factory()->create(['is_active' => true]);

        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);

        $response = $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/orders', [
                'shipping_method_id' => $method->id,
            ]);

        $response->assertStatus(201);
        $this->assertSame(1, Order::withoutTenantScope()->where('user_id', $user->id)->count());
    }
}
