<?php

namespace Tests\Feature\Inventory;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\Cart;
use App\Models\ProductSerial;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Inventory\AvailabilityCheck;
use App\Services\Inventory\SerialAvailabilityService;
use App\Services\Inventory\SerialInventoryService;
use App\Services\StockSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * Stage 6 — serial-first storefront availability (plan §7).
 */
class SerialAvailabilityTest extends TestCase
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

    private function makeSerialProduct(string $prefix = 'ton', int $stock = 0): Product
    {
        return Product::factory()->serial($prefix)->create([
            'name' => 'شمش '.$prefix,
            'slug' => 'serial-'.$prefix.'-'.uniqid(),
            'stock_quantity' => $stock,
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

    private function addToCart(User $user, Product $product, int $quantity): void
    {
        $method = ShippingMethod::factory()->create(['is_active' => true]);

        Cart::withoutTenantScope()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'platform' => 'main',
        ]);

        $this->shippingMethodId = $method->id;
    }

    private ?int $shippingMethodId = null;

    public function test_serial_product_with_zero_serials_but_positive_stock_is_unavailable(): void
    {
        $product = $this->makeSerialProduct('ton', 99);

        $show = $this->getJson('/api/products/'.$product->slug);
        $show->assertOk();
        $this->assertFalse($show->json('data.stock'));

        $user = User::factory()->create();
        $this->addToCart($user, $product, 1);

        $response = $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/orders', ['shipping_method_id' => $this->shippingMethodId]);

        $response->assertStatus(422);
        $response->assertJson(['error_code' => 'INSUFFICIENT_STOCK']);
    }

    public function test_two_available_three_requested_is_rejected_with_counts(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);

        $user = User::factory()->create();
        $this->addToCart($user, $product, 3);

        $response = $this->withHeaders($this->mainHeaders($user))
            ->postJson('/api/orders', ['shipping_method_id' => $this->shippingMethodId]);

        $response->assertStatus(422);
        $response->assertJsonPath('products.0.available', 2);
        $response->assertJsonPath('products.0.requested', 3);
    }

    public function test_own_holdings_count_toward_availability(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);

        $order = OrderFactory::new()->create(['platform' => 'main', 'serial_inventory_enabled' => true]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => 2,
            'subtotal' => 2000000,
        ]);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');

        $check = AvailabilityCheck::check([
            ['product' => $product->fresh(), 'product_id' => $product->id, 'quantity' => 2],
        ], 'main', $order);

        $this->assertTrue($check['valid']);
    }

    public function test_cached_list_reflects_fresh_stock_after_last_unit_reserved(): void
    {
        $product = $this->makeSerialProduct('ton', 99);
        $this->addSerials($product, 1);

        $first = $this->getJson('/api/products?slugs='.$product->slug);
        $first->assertOk();
        $this->assertTrue($first->json('data.0.stock'));

        $order = OrderFactory::new()->create(['platform' => 'main', 'serial_inventory_enabled' => true]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => 1,
            'subtotal' => 1000000,
        ]);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');

        $second = $this->getJson('/api/products?slugs='.$product->slug);
        $second->assertOk();
        $this->assertFalse($second->json('data.0.stock'));
    }

    public function test_product_list_avoids_per_product_stock_queries(): void
    {
        $prefixes = ['ton', 'tfv', 'tte', 'thd', 'zpf'];

        foreach ($prefixes as $i => $prefix) {
            $product = Product::factory()->serial($prefix)->create([
                'name' => 'شمش '.$i,
                'slug' => 'serial-bulk-'.$i.'-'.uniqid(),
            ]);
            $this->addSerials($product, 2, $prefix);
        }

        DB::enableQueryLog();
        $response = $this->getJson('/api/products');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertLessThanOrEqual(12, count($queries), 'Stock overlay must stay grouped, found '.count($queries).' queries.');
    }

    public function test_availability_service_counts_are_grouped_and_fresh(): void
    {
        $first = $this->makeSerialProduct('ton');
        $second = Product::factory()->serial('tfv')->create(['name' => 'شمش tfv', 'slug' => 'serial-tfv-'.uniqid()]);
        $this->addSerials($first, 3, 'ton');
        $this->addSerials($second, 1, 'tfv');

        DB::enableQueryLog();
        $counts = app(SerialAvailabilityService::class)->countsForProducts([$first->id, $second->id, 999999]);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([$first->id => 3, $second->id => 1, 999999 => 0], $counts);
        $this->assertLessThanOrEqual(4, count($queries));
    }

    public function test_rige_in_stock_follows_inventory_mode(): void
    {
        $serial = $this->makeSerialProduct('ton', 50);
        $serial->update(['sku' => 'RIGE-SERIAL-'.uniqid()]);
        $manual = Product::factory()->create([
            'name' => 'شمش دستی',
            'slug' => 'manual-'.uniqid(),
            'sku' => 'RIGE-MANUAL-'.uniqid(),
            'stock_quantity' => 3,
        ]);

        $response = $this->getJson('/api/rige/products');
        $response->assertOk();

        $byCode = collect($response->json())->keyBy('code');

        $this->assertFalse($byCode[$serial->sku]['in_stock']);
        $this->assertTrue($byCode[$manual->sku]['in_stock']);
    }

    public function test_hesabfa_stock_sync_is_stopped_without_http_calls(): void
    {
        Http::fake();

        $result = app(StockSyncService::class)->syncAllStock();

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_hesabfa_quantity_webhook_does_not_touch_stock_columns(): void
    {
        config(['hesabfa.webhook_secret' => 'test-secret']);

        $product = Product::factory()->create([
            'name' => 'شمش وب‌هوک',
            'slug' => 'webhook-'.uniqid(),
            'sku' => 'WEBHOOK-001',
            'price' => 1000000,
            'stock_quantity' => 7,
        ]);

        $response = $this->postJson('/api/hesabfa/webhook', [
            'EventType' => 'ItemQuantityChanged',
            'ItemCode' => 'WEBHOOK-001',
            'Quantity' => 99,
        ], ['X-Webhook-Secret' => 'test-secret']);

        $response->assertOk();
        $this->assertSame(7, (int) $product->fresh()->stock_quantity);
        $this->assertNull($product->fresh()->hesabfa_physical_stock);
    }

    public function test_hesabfa_price_webhook_still_applies(): void
    {
        config(['hesabfa.webhook_secret' => 'test-secret']);

        $product = Product::factory()->create([
            'name' => 'شمش قیمت',
            'slug' => 'price-'.uniqid(),
            'sku' => 'PRICE-001',
            'price' => 1000000,
        ]);

        $response = $this->postJson('/api/hesabfa/webhook', [
            'EventType' => 'ItemPriceChanged',
            'ItemCode' => 'PRICE-001',
            'Price' => 20000000,
        ], ['X-Webhook-Secret' => 'test-secret']);

        $response->assertOk();
        $this->assertSame(2000000, (int) $product->fresh()->price);
    }

    public function test_sellable_stock_ignores_legacy_hesabfa_columns(): void
    {
        $serial = $this->makeSerialProduct('ton', 99);
        $serial->forceFill(['hesabfa_physical_stock' => 50, 'hesabfa_reserved_stock' => 0])->save();

        $this->assertSame(0, $serial->fresh()->sellable_stock);

        $manual = Product::factory()->create([
            'name' => 'شمش دستی',
            'slug' => 'manual-'.uniqid(),
            'stock_quantity' => 5,
        ]);
        $manual->forceFill(['hesabfa_physical_stock' => -10, 'hesabfa_reserved_stock' => 99])->save();

        $this->assertSame(5, $manual->fresh()->sellable_stock);
    }
}
