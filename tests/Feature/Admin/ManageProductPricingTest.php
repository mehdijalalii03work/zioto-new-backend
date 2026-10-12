<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Products\ManageProductPricing;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Product\Models\Product;
use Tests\TestCase;

class ManageProductPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'شمش طلا ۰/۵ گرم',
            'slug' => 'gold-bar-05',
            'price_type' => 'dynamic',
            'price_board_item' => 'Gold9999_Sell',
            'weight' => '0.50',
            'price' => 1,
        ]);
    }

    public function test_labor_percent_is_shown_and_saved_as_coefficient(): void
    {
        $product = $this->product();
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        $component = Livewire::actingAs($user, 'web')
            ->test(ManageProductPricing::class)
            ->assertSuccessful()
            // The default coefficient (1.0) is presented as 100%.
            ->assertSet("values.{$product->id}.daily.basic", 100)
            ->assertSee("wire:model=\"values.{$product->id}.daily.basic\"", false)
            ->call('setPeriod', 'nightly')
            ->assertSet('activePeriod', 'nightly')
            ->call('setPeriod', 'daily')
            ->call('toggleEdit')
            ->set("values.{$product->id}.daily.basic", 7.7)
            ->call('save');

        $matrix = $product->fresh()->labor_coefficients;

        $this->assertEqualsWithDelta(0.077, $matrix['daily']['basic'] ?? null, 1e-9);
        // Cells left untouched keep the 100% default.
        $this->assertEqualsWithDelta(1.0, $matrix['daily']['pro'] ?? null, 1e-9);
        $this->assertEqualsWithDelta(1.0, $matrix['nightly']['basic'] ?? null, 1e-9);
        $this->assertFalse($component->get('isEditing'));
    }

    public function test_page_renders_the_matrix_table(): void
    {
        $this->product();
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        $this->actingAs($user, 'web')
            ->get('/admin/products/manage-pricing')
            ->assertOk()
            ->assertSee('درصد اجرت هر محصول')
            ->assertSee('وزن (گرم)')
            ->assertSee('شمش طلا ۰/۵ گرم')
            ->assertSee('روزانه')
            ->assertSee('مشتری سطح پایه')
            ->assertSee('٪');
    }

    public function test_non_numeric_values_keep_the_stored_coefficient(): void
    {
        $product = $this->product();
        $product->update(['labor_coefficients' => ['daily' => ['basic' => 0.077]]]);

        $user = User::factory()->create()->assignRole(Role::Admin->value);

        Livewire::actingAs($user, 'web')
            ->test(ManageProductPricing::class)
            ->call('toggleEdit')
            ->set("values.{$product->id}.daily.basic", '')
            ->call('save');

        $matrix = $product->fresh()->labor_coefficients;

        $this->assertEqualsWithDelta(0.077, $matrix['daily']['basic'] ?? null, 1e-9);
    }

    public function test_daily_and_nightly_values_are_saved_independently(): void
    {
        $product = $this->product();
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        Livewire::actingAs($user, 'web')
            ->test(ManageProductPricing::class)
            ->call('toggleEdit')
            ->set("values.{$product->id}.daily.basic", 7.7)
            ->call('setPeriod', 'nightly')
            ->set("values.{$product->id}.nightly.basic", 8.5)
            ->call('save');

        $matrix = $product->fresh()->labor_coefficients;

        $this->assertEqualsWithDelta(0.077, $matrix['daily']['basic'] ?? null, 1e-9);
        $this->assertEqualsWithDelta(0.085, $matrix['nightly']['basic'] ?? null, 1e-9);
    }

    public function test_each_period_renders_distinct_cell_keys(): void
    {
        $product = $this->product();
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        $component = Livewire::actingAs($user, 'web')
            ->test(ManageProductPricing::class)
            ->assertSee('wire:key="cell-'.$product->id.'-daily-basic"', false)
            ->call('setPeriod', 'nightly')
            ->assertSee('wire:key="cell-'.$product->id.'-nightly-basic"', false)
            ->assertDontSee('wire:key="cell-'.$product->id.'-daily-basic"', false);

        $component->call('setPeriod', 'daily')
            ->assertSee('wire:key="cell-'.$product->id.'-daily-basic"', false);
    }
}
