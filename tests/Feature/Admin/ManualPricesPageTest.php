<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Pricing\ManualPricesPage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class ManualPricesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_manual_silver_price_invalidates_the_full_board_cache(): void
    {
        config()->set('app.key', str_repeat('a', 32));
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        Cache::put('zioto:payload', ['prices' => ['Silver9999_Sell' => ['value' => 100]]]);
        Cache::put('priceboard:prices', ['prices' => ['Silver9999_Sell' => ['value' => 100]]]);

        Livewire::actingAs($admin, 'web')
            ->test(ManualPricesPage::class)
            ->fillForm(['manual_silver9999_sell' => '513000'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(Cache::get('zioto:payload'));
        $this->assertNull(Cache::get('priceboard:prices'));
    }
}
