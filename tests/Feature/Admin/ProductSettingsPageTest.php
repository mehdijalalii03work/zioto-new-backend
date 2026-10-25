<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Pages\Pricing\ProductSettingsPage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pricing\PricingSettings;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_admin_can_stop_and_resume_sales_and_the_setting_is_reloaded(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        Livewire::actingAs($user, 'web')
            ->test(ProductSettingsPage::class)
            ->assertSet('data.sales_closed', false)
            ->assertSee('توقف فروش محصولات')
            ->set('data.sales_closed', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'zioto_pricing_sales_enabled',
            'value' => 'false',
            'type' => 'boolean',
        ]);
        $this->assertFalse(PricingSettings::salesEnabled());

        Livewire::actingAs($user, 'web')
            ->test(ProductSettingsPage::class)
            ->assertSet('data.sales_closed', true);

        Livewire::actingAs($user, 'web')
            ->test(ProductSettingsPage::class)
            ->set('data.sales_closed', false)
            ->call('save');

        $this->assertDatabaseHas('settings', [
            'key' => 'zioto_pricing_sales_enabled',
            'value' => 'true',
        ]);
        $this->assertTrue(PricingSettings::salesEnabled());
    }

    public function test_admin_can_save_and_reload_weekly_and_special_holidays(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        Livewire::actingAs($user, 'web')
            ->test(ProductSettingsPage::class)
            ->assertSee('روزهای تعطیل هفتگی')
            ->assertSee('تعطیلی‌های مناسبتی')
            ->fillForm([
                'weekly_holiday_days' => ['5'],
                'special_holiday_dates' => [
                    ['date' => '2026-10-21'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'zioto_pricing_weekly_holiday_days',
            'value' => '[5]',
            'type' => 'json',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'zioto_pricing_special_holiday_dates',
            'value' => '["2026-10-21"]',
            'type' => 'json',
        ]);
        $this->assertSame([5], PricingSettings::weeklyHolidayDays());
        $this->assertSame(['2026-10-21'], PricingSettings::specialHolidayDates());

        $reloadedForm = Livewire::actingAs($user, 'web')->test(ProductSettingsPage::class);
        $this->assertSame(['5'], array_map('strval', $reloadedForm->get('data.weekly_holiday_days')));
        $holidayRows = array_values($reloadedForm->get('data.special_holiday_dates'));
        $this->assertSame('2026-10-21', substr($holidayRows[0]['date'], 0, 10));
    }

    public function test_operator_cannot_change_the_labor_tax(): void
    {
        $user = User::factory()->create()->assignRole(Role::Operator->value);

        Livewire::actingAs($user, 'web')
            ->test(ProductSettingsPage::class)
            ->assertForbidden();
    }

    public function test_admin_can_save_and_reload_reference_rounding_settings(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        Livewire::actingAs($user, 'web')->test(ProductSettingsPage::class)
            ->set('data.round_prices', true)
            ->set('data.rounding_mode', 'reference')
            ->set('data.round_to', 1000)
            ->set('data.silver_round_to', 10000)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('reference', PricingSettings::roundingMode());
        $this->assertSame(10000, PricingSettings::silverRoundTo());
        $this->assertTrue(PricingSettings::roundPrices());
        Livewire::test(ProductSettingsPage::class)
            ->assertSet('data.rounding_mode', 'reference')
            ->assertSet('data.silver_round_to', 10000);
        Http::assertNothingSent();
    }

    public function test_invalid_rounding_options_do_not_save(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        Livewire::actingAs($user, 'web')->test(ProductSettingsPage::class)
            ->set('data.rounding_mode', 'invalid')
            ->set('data.silver_round_to', 0)
            ->call('save')
            ->assertHasFormErrors(['rounding_mode', 'silver_round_to']);

        $this->assertDatabaseMissing('settings', ['key' => 'zioto_pricing_rounding_mode']);
        $this->assertDatabaseMissing('settings', ['key' => 'zioto_pricing_silver_round_to']);
        Http::assertNothingSent();
    }

    public function test_view_only_user_cannot_save_pricing_settings(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::PricingView->value);

        Livewire::actingAs($user, 'web')->test(ProductSettingsPage::class)
            ->set('data.rounding_mode', 'reference')
            ->set('data.sales_closed', true)
            ->call('save');

        $this->assertDatabaseMissing('settings', ['key' => 'zioto_pricing_rounding_mode']);
        $this->assertDatabaseMissing('settings', ['key' => 'zioto_pricing_sales_enabled']);
        $this->assertDatabaseMissing('settings', ['key' => 'zioto_pricing_weekly_holiday_days']);
        Http::assertNothingSent();
    }
}
