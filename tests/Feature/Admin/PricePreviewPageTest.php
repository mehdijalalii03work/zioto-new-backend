<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Pricing\PricePreviewPage;
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
            ->assertSee('1,000')
            ->assertSee('ضریب: 1')
            ->assertSee('اجرت: 2,000')
            ->assertSee('نهایی: 4,000')
            ->assertSee('1 محصول')
            ->call('setPeriod', 'daily')
            ->assertSee('11:00 - 18:00')
            ->assertDontSee('18:00 - 11:00');

        // Only the active period's role columns are rendered.
        $this->assertSame(4, substr_count($component->html(), 'ضریب:'));
        $this->assertSame(4, substr_count($component->html(), 'مشتری سطح '));
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
