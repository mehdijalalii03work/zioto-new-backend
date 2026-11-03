<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Products\TapsiComparisonPage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Product\Models\Product;
use Tests\TestCase;

class TapsiComparisonPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public function test_page_uses_configured_tapsi_markup_as_the_comparison_price(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);
        Livewire::actingAs($user, 'web');
        config([
            'tapsi.markup_threshold' => 500_000_000,
            'tapsi.markup_below_threshold' => 2,
            'tapsi.markup_above_threshold' => 1,
        ]);

        $product = Product::create([
            'name' => 'Gold bar',
            'slug' => 'gold-bar-tapsi-compare',
            'price_type' => 'fixed',
            'weight' => '1.00',
            'price' => 300_000_000,
            'tapsi_product_id' => 'TAPSI-123',
        ]);
        $productWithSmallerDifference = Product::create([
            'name' => 'Silver bar',
            'slug' => 'silver-bar-tapsi-compare',
            'price_type' => 'fixed',
            'weight' => '1.00',
            'price' => 600_000_000,
            'tapsi_product_id' => 'TAPSI-456',
        ]);
        Product::create([
            'name' => 'Without Tapsi id',
            'slug' => 'without-tapsi-compare',
            'price' => 1_000,
        ]);

        $component = Livewire::test(TapsiComparisonPage::class)
            ->assertSuccessful()
            ->assertSee('قیمت ارسالی به تپسی‌شاپ')
            ->assertSee('TAPSI_MARKUP_THRESHOLD')
            ->assertSee('TAPSI_MARKUP_BELOW_50M')
            ->assertSee('TAPSI_MARKUP_ABOVE_50M')
            ->assertSee('محصولات زیر آستانه')
            ->assertSee('محصولات بالای آستانه')
            ->assertSee('TAPSI-123')
            ->assertSee('wire:click="sortByDiff"', false)
            ->assertSee('-1.96٪')
            ->assertDontSee('اختلاف مجاز')
            ->assertDontSee('bg-amber-200', false);

        $this->assertSame(2, $component->get('productCount'));
        $this->assertSame($product->id, $component->get('rows')[0]['id']);
        $this->assertSame(300_000_000.0, $component->get('rows')[0]['ourPrice']);
        $this->assertSame(306_000_000, $component->get('rows')[0]['tapsiPrice']);
        $this->assertSame(2, $component->get('rows')[0]['markupPercent']);
        $this->assertTrue($component->get('rows')[0]['isBelowThreshold']);
        $this->assertSame(1, $component->get('rows')[1]['markupPercent']);
        $this->assertFalse($component->get('rows')[1]['isBelowThreshold']);
        $this->assertSame(-1.9607843137254901, $component->get('rows')[0]['diffPct']);

        $component->call('sortByDiff');

        $this->assertSame(
            [$product->id, $productWithSmallerDifference->id],
            array_column($component->get('rows'), 'id'),
        );
    }
}
