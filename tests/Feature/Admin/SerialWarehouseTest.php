<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\ProductSerial;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * Stage 6 — serial-first warehouse table (plan §7).
 */
class SerialWarehouseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function staffUser(Role $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    public function test_warehouse_lists_serial_product_with_prefix_and_mode(): void
    {
        $product = Product::factory()->serial('ton')->create([
            'name' => 'شمش طلای سریالی',
            'slug' => 'serial-gold-'.uniqid(),
        ]);

        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'ton70000001',
            'serial_prefix' => 'ton',
            'status' => 'available',
        ]);

        $analyst = $this->staffUser(Role::Analyst);

        $this->actingAs($analyst, 'web')
            ->get(URL::route('filament.admin.pages.site-warehouse'))
            ->assertOk()
            ->assertSee('شمش طلای سریالی', escape: false)
            ->assertSee('سریالی', escape: false);
    }

    public function test_warehouse_lists_fixed_manual_products(): void
    {
        $product = Product::create([
            'name' => 'محصول ثابت دستی',
            'slug' => 'fixed-manual-'.uniqid(),
            'price_type' => 'fixed',
            'price' => 1000000,
            'stock_quantity' => 4,
        ]);

        $analyst = $this->staffUser(Role::Analyst);

        $this->actingAs($analyst, 'web')
            ->get(URL::route('filament.admin.pages.site-warehouse'))
            ->assertOk()
            ->assertSee('محصول ثابت دستی', escape: false);
    }

    public function test_manager_sees_import_link(): void
    {
        $manager = $this->staffUser(Role::Manager);

        $this->actingAs($manager, 'web')
            ->get(URL::route('filament.admin.pages.site-warehouse'))
            ->assertOk()
            ->assertSee('ایمپورت سریال', escape: false);
    }
}
