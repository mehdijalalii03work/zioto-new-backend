<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RigeController;
use App\Http\Controllers\Api\WishlistController;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryCatalogScaleTest extends TestCase
{
    use RefreshDatabase;

    public static function catalogs(): array
    {
        return ['products' => ['products'], 'rige' => ['rige'], 'wishlist' => ['wishlist']];
    }

    #[DataProvider('catalogs')]
    public function test_cold_catalog_queries_do_not_grow_with_products(string $catalog): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $products = Product::factory()->count(5)->create(['status' => 'published', 'visibility' => 'public']);
        foreach ($products as $product) {
            Wishlist::create(['product_id' => $product->id, 'user_id' => $user->id, 'platform' => 'main']);
        }
        $small = $this->queryCount($catalog);
        $products = Product::factory()->count(25)->create(['status' => 'published', 'visibility' => 'public']);
        foreach ($products as $product) {
            Wishlist::create(['product_id' => $product->id, 'user_id' => $user->id, 'platform' => 'main']);
        }
        $large = $this->queryCount($catalog);

        $this->assertLessThanOrEqual($small + 2, $large, "$catalog grows from $small to $large queries");
    }

    private function queryCount(string $catalog): int
    {
        Cache::flush();
        DB::enableQueryLog();
        DB::flushQueryLog();
        match ($catalog) {
            'products' => app(ProductController::class)->index(),
            'rige' => app(RigeController::class)->products(),
            'wishlist' => app(WishlistController::class)->index(Request::create('/api/wishlist')),
        };
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_rige_nullable_sku_uses_live_inventory_after_catalog_cache(): void
    {
        $product = Product::factory()->create(['status' => 'published', 'visibility' => 'public', 'sku' => null, 'stock_quantity' => 2]);
        $controller = app(RigeController::class);
        $data = $controller->products()->getData(true);
        $this->assertTrue($data[0]['in_stock']);
        $this->assertArrayNotHasKey('_product_id', $data[0]);
        $product->update(['stock_quantity' => 0]);
        $this->assertFalse($controller->products()->getData(true)[0]['in_stock']);
    }
}
