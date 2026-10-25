<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Product\Models\Product;
use Tests\TestCase;

class DirectTokenikoProductPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_uses_tokeniko_sku_price_and_keeps_order_item_final_price_consistent(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_tokeniko_product_prices_enabled',
            'value' => 'true',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'قیمت مستقیم توکنیکو',
        ]);
        Cache::put('tokeniko:shop_prices', ['tokeniko-sku-1' => '250000'], 60);

        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'محصول دارای قیمت توکنیکو',
            'slug' => 'tokeniko-order-product',
            'tokeniko_sku' => 'tokeniko-sku-1',
            'price' => 100000,
            'stock_quantity' => 1,
        ]);
        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);
        $shippingMethod = ShippingMethod::factory()->create(['is_active' => true]);

        $headers = [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ];

        $this->withHeaders($headers)->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.0.price', 250000);

        $response = $this->withHeaders($headers)->postJson('/api/orders', [
            'gateway' => 'parsian',
            'shipping_method_id' => $shippingMethod->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('order.items.0.product_price', '250000')
            ->assertJsonPath('order.items.0.final_price', '250000.00');
    }
}
