<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Products\ProductComparisonPage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Product\Models\Product;
use Tests\TestCase;

class ProductComparisonPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function admin(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        Livewire::actingAs($user, 'web');
    }

    private function product(string $sku, float $price): Product
    {
        return Product::create([
            'name' => 'Product '.$sku,
            'slug' => 'product-'.preg_replace('/[^a-z0-9]/', '-', strtolower($sku)).uniqid(),
            'price_type' => 'dynamic',
            'price_board_item' => 'Gold995_Sell',
            'weight' => '1.00',
            'price' => $price,
            'tokeniko_sku' => $sku,
        ]);
    }

    private function fakeTokeniko(array &$requests): void
    {
        Http::fake([
            '*apigateway.tokeniko.com/shop/api/Category/getPrices*' => function ($request) use (&$requests) {
                $requests[] = $request;

                return Http::response(['Model' => [
                    ['Name' => 'zioto-gold-bar-1gram-995', 'SellPrice' => 300000000],
                    ['Name' => 'zioto-silver-bar-5gram', 'SellPrice' => 37290000],
                    ['Name' => 'gold-roll-1kilogram-24carat', 'SellPrice' => null],
                ]], 200);
            },
        ]);
    }

    public function test_page_lists_products_with_price_differences(): void
    {
        $this->admin();
        $matched = $this->product('zioto-gold-bar-1gram-995', 306000000);
        $this->product('no-such-sku', 1000);

        $requests = [];
        $this->fakeTokeniko($requests);

        $component = Livewire::test(ProductComparisonPage::class)->assertSuccessful();

        $this->assertCount(1, $requests);

        $rows = $component->get('rows');

        $this->assertCount(2, $rows);
        $this->assertSame($matched->id, $rows[0]['id']);
        $this->assertSame(306000000.0, $rows[0]['ourPrice']);
        $this->assertSame(300000000.0, $rows[0]['tokPrice']);
        $this->assertEqualsWithDelta(2.0, $rows[0]['diffPct'], 0.0001);

        // SKU missing from Tokeniko shows nulls instead of breaking.
        $this->assertSame(1000.0, $rows[1]['ourPrice']);
        $this->assertNull($rows[1]['tokPrice']);
        $this->assertNull($rows[1]['diffPct']);
    }

    public function test_refresh_refetches_tokeniko_without_storing(): void
    {
        $this->admin();
        $this->product('zioto-gold-bar-1gram-995', 306000000);

        $requests = [];
        $this->fakeTokeniko($requests);

        Livewire::test(ProductComparisonPage::class)
            ->call('refresh')
            ->assertSuccessful();

        $this->assertCount(2, $requests);
        $this->assertNull(cache('tokeniko:shop_prices'));
    }

    public function test_tokeniko_failure_still_shows_our_prices(): void
    {
        $this->admin();
        $this->product('zioto-gold-bar-1gram-995', 306000000);

        Http::fake([
            '*apigateway.tokeniko.com/shop/api/Category/getPrices*' => Http::response([], 500),
        ]);

        $component = Livewire::test(ProductComparisonPage::class)->assertSuccessful();

        $this->assertNotNull($component->get('tokenikoError'));
        $this->assertSame(306000000.0, $component->get('rows')[0]['ourPrice']);
        $this->assertNull($component->get('rows')[0]['tokPrice']);
    }
}
