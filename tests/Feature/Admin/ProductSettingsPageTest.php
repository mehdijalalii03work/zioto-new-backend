<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Pricing\ProductSettingsPage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pricing\PricingSettings;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public function test_labor_tax_field_is_shown_on_the_page(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        Livewire::actingAs($user, 'web')
            ->test(ProductSettingsPage::class)
            ->assertSuccessful()
            ->assertSee('درصد مالیات اجرت طلا')
            ->assertSee('فقط روی اجرتِ محصولاتِ قیمت‌گذاری پویا');
    }

    public function test_labor_tax_is_saved_for_dynamic_pricing(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        Livewire::actingAs($user, 'web')
            ->test(ProductSettingsPage::class)
            ->fillForm(['tax_gold_labor' => 12.5])
            ->call('save');

        $setting = Setting::where('key', 'tax_gold_labor')->first();

        $this->assertNotNull($setting);
        $this->assertSame('tax', $setting->category);
        $this->assertSame(12.5, (float) $setting->value);
        $this->assertSame(12.5, PricingSettings::taxGoldLabor());
    }

    public function test_operator_cannot_change_the_labor_tax(): void
    {
        $user = User::factory()->create()->assignRole(Role::Operator->value);

        Livewire::actingAs($user, 'web')
            ->test(ProductSettingsPage::class)
            ->assertForbidden();
    }
}
