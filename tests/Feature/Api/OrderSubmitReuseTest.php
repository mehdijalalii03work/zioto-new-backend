<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\Cart;
use App\Models\City;
use App\Models\Province;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

class OrderSubmitReuseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
    }

    public function test_reusing_an_order_preserves_province_based_shipping_and_total(): void
    {
        $user = User::factory()->create();
        $province = Province::factory()->create();
        $city = City::factory()->create(['province_id' => $province->id]);
        $address = UserAddress::factory()->create([
            'user_id' => $user->id,
            'province_id' => $province->id,
            'city_id' => $city->id,
        ]);
        $product = Product::create([
            'name' => 'Gold bar',
            'slug' => 'reuse-order-gold-bar',
            'price' => 187_340_000,
            'stock_quantity' => 5,
            'weight' => 1,
        ]);
        $secondProduct = Product::create([
            'name' => 'Silver bar',
            'slug' => 'reuse-order-silver-bar',
            'price' => 36_080_000,
            'stock_quantity' => 5,
            'weight' => 1,
        ]);
        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);
        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $secondProduct->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);
        $shippingMethod = ShippingMethod::factory()->create();
        ShippingRate::factory()->create([
            'shipping_method_id' => $shippingMethod->id,
            'rate_type' => 'province',
            'province_id' => $province->id,
            'base_rate' => 1_500_000,
            'tax_rate' => 10,
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ];
        $payload = [
            'shipping_method_id' => $shippingMethod->id,
            'user_address_id' => $address->id,
            'gateway' => 'digipay',
        ];

        $created = $this->withHeaders($headers)->postJson('/api/orders', $payload);
        $created->assertCreated();
        $orderId = $created->json('order.id');

        $retry = $this->withHeaders($headers)->postJson('/api/orders', $payload);

        $retry->assertOk()
            ->assertJsonPath('is_existing', true)
            ->assertJsonPath('order.id', $orderId)
            ->assertJsonPath('order.total_amount', '234072800');

        $order = Order::withoutTenantScope()->with('shipping')->findOrFail($orderId);
        $this->assertSame(1_650_000, (int) $order->shipping->shipping_cost);
        $this->assertSame(150_000, (int) $order->shipping->tax_amount);
        $this->assertSame(223_420_000, (int) $order->items->sum('subtotal'));
        $this->assertSame(9_002_800, (int) data_get(json_decode($order->notes, true), 'installment_fee'));
    }
}
