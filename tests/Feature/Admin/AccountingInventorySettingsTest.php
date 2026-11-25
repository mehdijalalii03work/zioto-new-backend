<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\HesabfaService;
use App\Services\RahkaranInventoryService;
use App\Services\RahkaranSettings;
use App\Services\StockSyncService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingInventorySettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $envBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
        $this->envBackup = (string) file_get_contents(base_path('.env'));
    }

    protected function tearDown(): void
    {
        file_put_contents(base_path('.env'), $this->envBackup);

        parent::tearDown();
    }

    public function test_inventory_source_and_hesabfa_invoice_destination_are_saved_in_database(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);

        Livewire::actingAs($admin, 'web')
            ->test(ManageSettings::class)
            ->assertFormFieldExists('inventory_source')
            ->assertFormFieldExists('send_invoices_to_hesabfa')
            ->assertFormFieldExists('send_invoices_to_rahkaran')
            ->fillForm([
                'inventory_source' => 'rahkaran',
                'rahkaran_stock_quantity_source' => 'stored_serials',
                'send_invoices_to_hesabfa' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('rahkaran', Setting::where('key', 'inventory_source')->value('value'));
        $this->assertSame('stored_serials', setting('rahkaran_stock_quantity_source'));
        $this->assertSame('false', Setting::where('key', 'send_invoices_to_hesabfa')->value('value'));
        $this->assertSame('inventory', Setting::where('key', 'inventory_source')->value('category'));
    }

    public function test_inventory_source_is_saved_even_when_rahkaran_stock_sync_reports_product_errors(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        $this->mock(RahkaranInventoryService::class)
            ->shouldReceive('syncAllSerials')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'موجودی 72 کالا همگام شد؛ 1736 سریال خوانده شد',
                'errors' => ['ZPGB5-00200-0' => 'شناسهٔ کالای راهکاران تنظیم نشده است'],
            ]);

        Livewire::actingAs($admin, 'web')
            ->test(ManageSettings::class)
            ->fillForm(['inventory_source' => 'rahkaran'])
            ->call('save')
            ->assertNotified(Notification::make()
                ->title('منبع موجودی تغییر کرد؛ همگام‌سازی ناقص است')
                ->body("موجودی 72 کالا همگام شد؛ 1736 سریال خوانده شد\nZPGB5-00200-0: شناسهٔ کالای راهکاران تنظیم نشده است")
                ->warning());

        $this->assertSame('rahkaran', Setting::where('key', 'inventory_source')->value('value'));
    }

    public function test_inventory_source_is_saved_even_when_rahkaran_stock_sync_throws(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        $this->mock(RahkaranInventoryService::class)
            ->shouldReceive('syncAllSerials')
            ->once()
            ->andThrow(new \RuntimeException('Rahkaran is unavailable'));

        Livewire::actingAs($admin, 'web')
            ->test(ManageSettings::class)
            ->fillForm(['inventory_source' => 'rahkaran'])
            ->call('save')
            ->assertNotified(Notification::make()
                ->title('منبع موجودی تغییر کرد؛ همگام‌سازی ناقص است')
                ->body('Rahkaran is unavailable')
                ->warning());

        $this->assertSame('rahkaran', Setting::where('key', 'inventory_source')->value('value'));
    }

    public function test_offline_inventory_source_and_serial_origin_are_saved_without_sync(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        $this->mock(RahkaranInventoryService::class)->shouldReceive('syncAllSerials')->never();
        $this->mock(StockSyncService::class)->shouldReceive('syncAllStock')->never();

        Livewire::actingAs($admin, 'web')
            ->test(ManageSettings::class)
            ->fillForm(['inventory_source' => 'offline'])
            ->fillForm(['offline_serial_origin' => 'excel'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('تنظیمات با موفقیت ذخیره شد');

        $this->assertSame('offline', setting('inventory_source'));
        $this->assertSame('excel', setting('offline_serial_origin'));
    }

    public function test_rahkaran_connection_uses_config_instead_of_legacy_database_settings(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        config(['rahkaran.inventory_id' => '181']);
        Setting::create([
            'key' => 'rahkaran_config_inventory_id',
            'value' => '999',
            'label' => 'Legacy Rahkaran inventory ID',
            'type' => 'string',
            'category' => 'accounting',
        ]);

        $this->assertSame('181', app(RahkaranSettings::class)->get('inventory_id'));

        Livewire::actingAs($admin, 'web')
            ->test(ManageSettings::class)
            ->assertFormFieldDoesNotExist('rahkaran_password')
            ->assertFormFieldDoesNotExist('rahkaran_inventory_id')
            ->assertFormFieldExists('inventory_source');
    }

    public function test_hesabfa_stock_sync_is_skipped_when_rahkaran_is_selected(): void
    {
        Setting::create([
            'key' => 'inventory_source',
            'value' => 'rahkaran',
            'type' => 'string',
            'category' => 'inventory',
            'label' => 'منبع موجودی سایت',
        ]);

        $hesabfa = $this->createMock(HesabfaService::class);
        $hesabfa->expects($this->never())->method('isConfigured');

        $result = (new StockSyncService($hesabfa))->syncAllStock();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('منبع موجودی سایت حسابفا نیست', $result['message']);
    }
}
