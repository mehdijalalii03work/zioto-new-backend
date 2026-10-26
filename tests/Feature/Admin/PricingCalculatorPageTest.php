<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Pricing\PricingCalculatorPage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PricingCalculatorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public function test_calculates_tax_on_labor_only_and_exposes_a_detailed_breakdown(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);
        Livewire::actingAs($user, 'web');

        $component = Livewire::test(PricingCalculatorPage::class)
            ->assertSet('spotPrice', '')
            ->assertSet('laborPercent', '')
            ->assertSet('taxScope', 'labor')
            ->assertSet('taxPercent', '10')
            ->set('spotPrice', '1000000')
            ->set('laborPercent', '5')
            ->call('calculate')
            ->assertSet('result.spotPrice', 1000000.0)
            ->assertSet('result.laborAmount', 50000.0)
            ->assertSet('result.taxableAmount', 50000.0)
            ->assertSet('result.taxAmount', 5000.0)
            ->assertSet('result.totalPrice', 1055000.0)
            ->assertSee('مبنای محاسبه مالیات: فقط اجرت');

        $this->assertSame('1٬000٬000', $component->get('result')['formattedSpotPrice']);
    }

    public function test_calculates_tax_on_product_and_labor_when_that_scope_is_selected(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);
        Livewire::actingAs($user, 'web');

        Livewire::test(PricingCalculatorPage::class)
            ->set('spotPrice', '1000000')
            ->set('laborPercent', '5')
            ->set('taxScope', 'product_and_labor')
            ->set('taxPercent', '10')
            ->call('calculate')
            ->assertSet('result.taxableAmount', 1050000.0)
            ->assertSet('result.taxAmount', 105000.0)
            ->assertSet('result.totalPrice', 1155000.0)
            ->assertSee('مبنای محاسبه مالیات: محصول و اجرت');
    }

    public function test_invalid_inputs_do_not_produce_a_calculation(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);
        Livewire::actingAs($user, 'web');

        Livewire::test(PricingCalculatorPage::class)
            ->set('spotPrice', '0')
            ->set('laborPercent', '-5')
            ->set('taxPercent', '1001')
            ->call('calculate')
            ->assertHasErrors(['spotPrice', 'laborPercent', 'taxPercent'])
            ->assertSet('result', null);
    }
}
