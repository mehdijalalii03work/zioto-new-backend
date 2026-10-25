<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Pricing\LaborSettingsPage;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LaborSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public static function invalidTimePeriods(): array
    {
        return [
            'nightly period removed' => [[
                ['slug' => 'daily', 'name' => 'روزانه', 'start' => '11:00', 'end' => '18:00'],
            ]],
            'nightly period renamed' => [[
                ['slug' => 'daily', 'name' => 'روزانه', 'start' => '11:00', 'end' => '18:00'],
                ['slug' => 'night', 'name' => 'شبانه', 'start' => '18:00', 'end' => '11:00'],
            ]],
        ];
    }

    #[DataProvider('invalidTimePeriods')]
    public function test_admin_cannot_remove_or_rename_the_nightly_period(array $timePeriods): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);
        $originalPeriods = [
            ['slug' => 'daily', 'name' => 'روزانه', 'start' => '11:00', 'end' => '18:00'],
            ['slug' => 'nightly', 'name' => 'شبانه', 'start' => '18:00', 'end' => '11:00'],
        ];

        Setting::create([
            'key' => 'zioto_pricing_time_periods',
            'value' => json_encode($originalPeriods),
            'type' => 'json',
            'category' => 'pricing',
            'label' => 'بازه‌های زمانی',
        ]);

        Livewire::actingAs($user, 'web')
            ->test(LaborSettingsPage::class)
            ->fillForm(['time_periods' => $timePeriods])
            ->call('save')
            ->assertNotified('شناسه بازه nightly الزامی است');

        $this->assertSame(json_encode($originalPeriods), Setting::where('key', 'zioto_pricing_time_periods')->value('value'));
    }
}
