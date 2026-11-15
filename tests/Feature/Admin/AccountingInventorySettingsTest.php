<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\HesabfaService;
use App\Services\StockSyncService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
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
                'send_invoices_to_hesabfa' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('rahkaran', Setting::where('key', 'inventory_source')->value('value'));
        $this->assertSame('false', Setting::where('key', 'send_invoices_to_hesabfa')->value('value'));
        $this->assertSame('inventory', Setting::where('key', 'inventory_source')->value('category'));
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
