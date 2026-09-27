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

    public function test_labor_percent_is_shown_and_saved_as_coefficient(): void
    {
        $product = Product::create([
            'name' => 'شمش طلا ۰/۵ گرم',
            'slug' => 'gold-bar-05',
            'price_type' => 'dynamic',
            'price_board_item' => 'Gold9999_Sell',
            'weight' => '0.50',
            'price' => 1,
        ]);
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        $component = Livewire::actingAs($user, 'web')
            ->test(ManageProductPricing::class)
            ->assertSuccessful();

        $itemKey = array_key_first($component->get('data.products'));

        $component
            // The default coefficient (1.0) is presented as 100%.
            ->assertSet("data.products.{$itemKey}.coef_daily_basic", 100)
            ->call('toggleEdit')
            ->set("data.products.{$itemKey}.coef_daily_basic", 7.7)
            ->call('save');

        $matrix = $product->fresh()->labor_coefficients;

        $this->assertEqualsWithDelta(0.077, $matrix['daily']['basic'] ?? null, 1e-9);
        // Cells left untouched keep the 100% default.
        $this->assertEqualsWithDelta(1.0, $matrix['daily']['pro'] ?? null, 1e-9);
        $this->assertEqualsWithDelta(1.0, $matrix['nightly']['basic'] ?? null, 1e-9);
    }
}
