<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Pricing\PricePreviewPage;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Modules\Product\Models\Product;
use Tests\TestCase;

class PricePreviewPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function seedBoard(float $value = 1000): void
    {
        Cache::put('zioto:payload', [
            'prices' => [
                'Gold750_Sell' => [
                    'name' => 'قیمت فروش طلای ۷۵۰',
                    'value' => $value,
                    'price_type' => 'sell',
                    'base_metal' => 'Gold750',
                    'trend' => 'stable',
                    'updated_at' => now()->timestamp,
                ],
                'Silver9999_Sell' => [
                    'name' => 'قیمت فروش نقره ۹۹۹.۹',
                    'value' => $value,
                    'price_type' => 'sell',
                    'base_metal' => 'Silver9999',
                    'trend' => 'stable',
                    'updated_at' => now()->timestamp,
                ],
            ],
            'debug' => [],
            'source_status' => [],
            'raw' => ['persian' => [], 'tala' => []],
            'used_fallback' => false,
        ], 60);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole(Role::Admin->value);
    }

    public function test_preview_renders_live_prices_and_matrix(): void
    {
        $this->seedBoard();

        Product::create([
            'name' => 'انگشتر طلا',
            'slug' => 'gold-ring',
            'price_type' => 'dynamic',
            'price_board_item' => 'Gold750_Sell',
            'weight' => '2.00',
            'price' => 1,
        ]);

        $component = Livewire::actingAs($this->admin(), 'web')
            ->test(PricePreviewPage::class)
            ->assertSuccessful()
            ->assertSee('قیمت لحظه‌ای هر گرم (تومان)')
            ->assertSee('طلای ۷۵۰:')
            ->assertSee('100')
            ->assertSee('درصد اجرت: 100٪')
            ->assertSee('اجرت: 200')
            ->assertSee('نهایی: 400')
            ->assertSee('1 محصول')
            ->call('setPeriod', 'daily')
            ->assertSee('11:00 - 18:00')
            ->assertDontSee('18:00 - 11:00');

        // Only the active period's role columns are rendered.
        $this->assertSame(4, substr_count($component->html(), 'درصد اجرت:'));
        $this->assertSame(4, substr_count($component->html(), 'مشتری سطح '));
    }

    public function test_preview_shows_tax_on_labor_for_gold_and_on_total_for_silver(): void
    {
        Setting::create([
            'key' => 'tax_silver',
            'value' => '10',
            'type' => 'number',
            'category' => 'tax',
            'label' => 'درصد مالیات نقره',
        ]);
        Setting::create([
            'key' => 'tax_gold_labor',
            'value' => '10',
            'type' => 'number',
            'category' => 'tax',
            'label' => 'درصد مالیات اجرت طلا',
        ]);

        $this->seedBoard();

        Product::create([
            'name' => 'انگشتر طلا',
            'slug' => 'gold-ring-with-tax',
            'price_type' => 'dynamic',
            'price_board_item' => 'Gold750_Sell',
            'weight' => '2.00',
            'price' => 1,
        ]);

        Product::create([
            'name' => 'دستبند نقره',
            'slug' => 'silver-bracelet',
            'price_type' => 'dynamic',
            'price_board_item' => 'Silver9999_Sell',
            'weight' => '2.00',
            'price' => 1,
        ]);

        $html = Livewire::actingAs($this->admin(), 'web')
            ->test(PricePreviewPage::class)
            ->assertSuccessful()
            ->assertSee('مالیات اجرت (10٪): 20')
            ->assertSee('مالیات روی کل (10٪): 40')
            ->html();

        // Gold: 10% of the 200 Toman labor only, so 400 → 420.
        $this->assertStringContainsString('نهایی: 420', $html);
        // Silver: 10% on the whole 400 Toman price.
        $this->assertStringContainsString('نهایی: 440', $html);
    }

    public function test_preview_shows_empty_state_without_dynamic_products(): void
    {
        $this->seedBoard();

        Product::create([
            'name' => 'محصول ثابت',
            'slug' => 'fixed-product',
            'price_type' => 'fixed',
            'price_board_item' => 'Gold750_Sell',
            'weight' => '2.00',
            'price' => 1,
        ]);

        Livewire::actingAs($this->admin(), 'web')
            ->test(PricePreviewPage::class)
            ->assertSuccessful()
            ->assertSee('محصولی با قیمت‌گذاری پویا یافت نشد')
            ->assertDontSee('نهایی:');
    }
}
