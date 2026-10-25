<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Resources\ProductResource;
use App\Models\Cart;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * Covers the "pack of N units" feature: pack products store the pack price,
 * draw sellable stock from a shared base product in single units, and reserve
 * quantity × pack_size singles when an order is confirmed.
 */
class ProductPackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
        Http::preventStrayRequests();
    }

    private function mainHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ];
    }

    private function baseProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'شمش نقره زیوتو 1 گرم',
            'slug' => 'silver-1g-'.uniqid(),
            'price' => 2000000,
            'stock_quantity' => 12,
            'weight' => 1,
            'pack_size' => 1,
            'hesabfa_physical_stock' => 12,
            'hesabfa_reserved_stock' => 0,
            'hesabfa_manual_reserved' => 0,
        ], $overrides));
    }

    private function packProduct(Product $base, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'شمش نقره زیوتو 1 گرم (پک 5 عددی)',
            'slug' => 'silver-1g-pack5-'.uniqid(),
            'price' => 10000000,
            'stock_quantity' => 12,
            'weight' => 1,
            'pack_size' => 5,
            'base_product_id' => $base->id,
            'hesabfa_physical_stock' => 0,
            'hesabfa_reserved_stock' => 0,
            'hesabfa_manual_reserved' => 0,
        ], $overrides));
    }

    public function test_dynamic_pack_price_is_unit_price_times_pack_size(): void
    {
        Cache::put('zioto:payload', [
            'prices' => [
                'Silver9999_Sell' => ['value' => 1_000_000, 'trend' => 'stable'],
            ],
            'debug' => [],
            'source_status' => [],
            'raw' => ['persian' => [], 'tala' => []],
            'used_fallback' => false,
        ]);

        $unit = $this->baseProduct([
            'price_type' => 'dynamic',
            'price_board_item' => 'Silver9999_Sell',
            'fee_off_hours' => 0,
            'fee_business_hours' => 0,
        ]);
        $pack = $this->packProduct($unit, [
            'price_type' => 'dynamic',
            'price_board_item' => 'Silver9999_Sell',
            'fee_off_hours' => 0,
            'fee_business_hours' => 0,
        ]);

        $unitPrice = $unit->calculatePrice();
        $packPrice = $pack->calculatePrice();

        $this->assertNotNull($unitPrice);
        $this->assertNotNull($packPrice);
        $this->assertSame($unitPrice * 5, $packPrice);
    }

    public function test_sellable_stock_of_pack_derives_from_base_in_packs(): void
    {
        // 12 singles available on the base => floor(12 / 5) = 2 packs sellable.
        $base = $this->baseProduct(['hesabfa_physical_stock' => 12]);
        $pack = $this->packProduct($base);

        $this->assertSame(12, $base->sellable_stock);
        $this->assertSame(2, $pack->sellable_stock);
    }

    public function test_sellable_stock_respects_base_reservations(): void
    {
        Setting::updateOrCreate(['key' => 'ignore_reserved_stock'], [
            'value' => 'false',
            'type' => 'boolean',
            'category' => 'hesabfa',
            'label' => 'نادیده گرفتن رزرو موجودی',
        ]);

        $base = $this->baseProduct([
            'hesabfa_physical_stock' => 12,
            'hesabfa_reserved_stock' => 8,
            'hesabfa_manual_reserved' => 2,
        ]);
        $pack = $this->packProduct($base);

        // 12 - 8 - 2 = 2 singles => 0 full packs.
        $this->assertSame(0, $pack->sellable_stock);
    }

    public function test_order_stores_pack_price_and_quantity(): void
    {
        $user = User::factory()->create();
        $base = $this->baseProduct();
        $pack = $this->packProduct($base);
        $method = ShippingMethod::factory()->create(['is_active' => true]);

        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $pack->id,
            'quantity' => 2,
            'platform' => 'main',
        ]);

        $response = $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/orders', [
                'gateway' => 'parsian',
                'shipping_method_id' => $method->id,
            ]);

        $response->assertStatus(201);

        $order = Order::withoutTenantScope()->where('user_id', $user->id)->firstOrFail();
        $item = $order->items()->first();

        $this->assertSame($pack->id, (int) $item->product_id);
        $this->assertSame(10000000, (int) $item->product_price);
        $this->assertSame(2, (int) $item->quantity);
        $this->assertSame(20000000, (int) $item->subtotal);
    }

    public function test_store_rejects_pack_exceeding_base_single_stock(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);

        $user = User::factory()->create();
        // 7 singles => floor(7 / 5) = 1 pack sellable, requesting 2 packs fails.
        $base = $this->baseProduct(['hesabfa_physical_stock' => 7]);
        $pack = $this->packProduct($base);
        $method = ShippingMethod::factory()->create(['is_active' => true]);

        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $pack->id,
            'quantity' => 2,
            'platform' => 'main',
        ]);

        $response = $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/orders', [
                'gateway' => 'parsian',
                'shipping_method_id' => $method->id,
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'INSUFFICIENT_STOCK']);
        $response->assertJsonPath('products.0.available', 1);
        $response->assertJsonPath('products.0.requested', 2);
        $this->assertSame(0, Order::withoutTenantScope()->where('user_id', $user->id)->count());
    }

    public function test_store_accepts_pack_within_base_single_stock(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);

        $user = User::factory()->create();
        $base = $this->baseProduct(['hesabfa_physical_stock' => 7]);
        $pack = $this->packProduct($base);
        $method = ShippingMethod::factory()->create(['is_active' => true]);

        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $pack->id,
            'quantity' => 1,
            'platform' => 'main',
        ]);

        $response = $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/orders', [
                'gateway' => 'parsian',
                'shipping_method_id' => $method->id,
            ]);

        $response->assertStatus(201);
        $this->assertSame(1, Order::withoutTenantScope()->where('user_id', $user->id)->count());
    }

    public function test_confirmed_order_reserves_pack_singles_on_base_product(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);

        $base = $this->baseProduct(['hesabfa_physical_stock' => 12]);
        $pack = $this->packProduct($base);

        $order = Order::factory()->create(['status' => 'pending']);
        $order->items()->create([
            'product_id' => $pack->id,
            'product_name' => $pack->name,
            'product_price' => $pack->price,
            'quantity' => 2,
            'subtotal' => $pack->price * 2,
        ]);

        $order->update(['status' => 'confirmed']);

        // 2 packs × 5 singles = 10 reserved on the base product, not the pack.
        $base->refresh();
        $pack->refresh();

        $this->assertSame(10, (int) $base->hesabfa_reserved_stock);
        $this->assertSame(0, (int) $pack->hesabfa_reserved_stock);

        // Releasing on cancellation returns the base reservation to zero.
        $order->update(['status' => 'cancelled']);

        $base->refresh();
        $this->assertSame(0, (int) $base->hesabfa_reserved_stock);
    }

    public function test_pack_recalibration_reserves_on_base_product(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);

        $base = $this->baseProduct(['hesabfa_physical_stock' => 12]);
        $pack = $this->packProduct($base);

        $order = Order::factory()->create(['status' => 'confirmed']);
        $order->items()->create([
            'product_id' => $pack->id,
            'product_name' => $pack->name,
            'product_price' => $pack->price,
            'quantity' => 2,
            'subtotal' => $pack->price * 2,
        ]);

        // Simulate drift, then rebuild from order history.
        $base->updateQuietly(['hesabfa_reserved_stock' => 3]);

        $this->artisan('hesabfa:recalculate-reserved')->assertSuccessful();

        $base->refresh();
        $pack->refresh();

        $this->assertSame(10, (int) $base->hesabfa_reserved_stock);
        $this->assertSame(0, (int) $pack->hesabfa_reserved_stock);
    }

    public function test_product_resource_exposes_pack_fields(): void
    {
        $base = $this->baseProduct();
        $pack = $this->packProduct($base);

        $data = (new ProductResource($pack))->toArray(request());

        $this->assertSame(5, $data['pack_size']);
        $this->assertSame(2000000, $data['unit_price']);
        $this->assertSame(10000000, $data['price']);
        $this->assertSame('پک 5 عددی', $data['badge']);
        $this->assertStringContainsString('× 5 عدد', $data['weight']);
        $this->assertTrue($data['stock']);
    }

    public function test_single_product_resource_defaults_pack_fields(): void
    {
        $base = $this->baseProduct();

        $data = (new ProductResource($base))->toArray(request());

        $this->assertSame(1, $data['pack_size']);
        $this->assertSame((int) $base->price, $data['unit_price']);
        $this->assertNull($data['badge']);
        $this->assertStringNotContainsString('×', $data['weight']);
    }
}
