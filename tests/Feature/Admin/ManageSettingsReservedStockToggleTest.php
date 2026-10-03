<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageSettingsReservedStockToggleTest extends TestCase
{
    use RefreshDatabase;

    private string $envBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');

        // ManageSettings persists the hesabfa_*/tapsi_* fields straight into .env.
        $this->envBackup = (string) file_get_contents(base_path('.env'));
    }

    protected function tearDown(): void
    {
        file_put_contents(base_path('.env'), $this->envBackup);

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole(Role::Admin->value);
    }

    public function test_toggle_is_rendered_on_the_settings_page(): void
    {
        Livewire::actingAs($this->admin(), 'web')
            ->test(ManageSettings::class)
            ->assertSuccessful()
            ->assertFormFieldExists('ignore_reserved_stock');
    }

    public function test_toggle_defaults_to_on_when_no_setting_row_exists(): void
    {
        Livewire::actingAs($this->admin(), 'web')
            ->test(ManageSettings::class)
            ->assertFormSet(['ignore_reserved_stock' => true]);
    }

    public function test_toggle_is_hydrated_from_the_saved_setting(): void
    {
        Setting::create([
            'key' => 'ignore_reserved_stock',
            'value' => 'false',
            'type' => 'boolean',
            'category' => 'hesabfa',
            'label' => 'نادیده گرفتن رزرو موجودی',
        ]);

        Livewire::actingAs($this->admin(), 'web')
            ->test(ManageSettings::class)
            ->assertFormSet(['ignore_reserved_stock' => false]);
    }

    public function test_saving_the_page_persists_the_toggle(): void
    {
        Livewire::actingAs($this->admin(), 'web')
            ->test(ManageSettings::class)
            ->fillForm(['ignore_reserved_stock' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = Setting::where('key', 'ignore_reserved_stock')->first();

        $this->assertNotNull($setting);
        $this->assertSame('false', $setting->value);
        $this->assertSame('hesabfa', $setting->category);
        $this->assertFalse(setting('ignore_reserved_stock', true));
    }

    public function test_turning_the_toggle_on_again_persists_it(): void
    {
        Setting::create([
            'key' => 'ignore_reserved_stock',
            'value' => 'false',
            'type' => 'boolean',
            'category' => 'hesabfa',
            'label' => 'نادیده گرفتن رزرو موجودی',
        ]);

        Livewire::actingAs($this->admin(), 'web')
            ->test(ManageSettings::class)
            ->fillForm(['ignore_reserved_stock' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('true', Setting::where('key', 'ignore_reserved_stock')->value('value'));
        $this->assertTrue(setting('ignore_reserved_stock', false));
    }

    public function test_seeder_ships_the_toggle_enabled(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->assertSame('true', Setting::where('key', 'ignore_reserved_stock')->value('value'));
    }
}
