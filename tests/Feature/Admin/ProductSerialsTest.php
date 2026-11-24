<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductSerialsTable;
use App\Models\RahkaranSerial;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Product\Models\Product;
use Tests\TestCase;

class ProductSerialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_serials_modal_shows_only_its_available_and_reserved_serials(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
        $user = User::factory()->create()->assignRole(Role::Admin->value);
        $product = Product::create(['name' => 'Gold bar', 'slug' => 'gold-bar', 'price' => 100]);
        $other = Product::create(['name' => 'Other', 'slug' => 'other', 'price' => 100]);
        $available = RahkaranSerial::create(['product_id' => $product->id, 'serial' => 'FREE-001', 'status' => 'available']);
        $reserved = RahkaranSerial::create(['product_id' => $product->id, 'serial' => 'RESERVED-001', 'status' => 'reserved']);
        $sold = RahkaranSerial::create(['product_id' => $product->id, 'serial' => 'SOLD-001', 'status' => 'sold']);
        $unrelated = RahkaranSerial::create(['product_id' => $other->id, 'serial' => 'OTHER-001', 'status' => 'available']);

        Livewire::actingAs($user, 'web')->test(ListProducts::class)
            ->mountTableAction('serials', $product)
            ->assertSet('mountedActions.0.name', 'serials');
        Livewire::actingAs($user, 'web')->test(ProductSerialsTable::class, ['product' => $product])
            ->assertCanSeeTableRecords([$available, $reserved])
            ->assertCanNotSeeTableRecords([$sold, $unrelated])
            ->filterTable('status', 'reserved')
            ->assertCanSeeTableRecords([$reserved])
            ->assertCanNotSeeTableRecords([$available]);
    }

    public function test_serial_table_requires_permission_to_view_products(): void
    {
        $product = Product::create(['name' => 'Gold bar', 'slug' => 'gold-bar', 'price' => 100]);
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')->test(ProductSerialsTable::class, ['product' => $product])->assertForbidden();
    }
}
