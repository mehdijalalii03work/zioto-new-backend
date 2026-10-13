<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\ProductSerialsRelationManager;
use App\Models\ProductSerial;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Product\Models\Product;
use Tests\TestCase;

class ProductSerialsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function serialProduct(): Product
    {
        $product = Product::factory()->serial('ton')->create([
            'name' => 'شمش ton',
            'slug' => 'serial-ton-'.uniqid(),
        ]);
        ProductSerial::factory()->create(['product_id' => $product->id, 'serial_code' => 'TON00000001']);

        return $product;
    }

    public function test_relation_manager_is_registered_on_product_resource(): void
    {
        $this->assertContains(
            ProductSerialsRelationManager::class,
            array_map(static fn ($configuration): string => $configuration->relationManager, ProductResource::getRelations()),
        );
    }

    public function test_serial_viewer_sees_imported_serials(): void
    {
        $product = $this->serialProduct();
        $manager = User::factory()->create()->assignRole(Role::Manager->value);
        $this->actingAs($manager, 'web');

        $this->assertTrue(ProductSerialsRelationManager::canViewForRecord($product, EditProduct::class));

        Livewire::actingAs($manager, 'web')->test(ProductSerialsRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => EditProduct::class,
        ])->assertSee('TON00000001');
    }

    public function test_relation_manager_is_hidden_without_serial_view(): void
    {
        $product = $this->serialProduct();
        $editor = User::factory()->create()->givePermissionTo('product.view', 'product.edit');
        $this->actingAs($editor, 'web');

        $this->assertFalse(ProductSerialsRelationManager::canViewForRecord($product, EditProduct::class));

        Livewire::actingAs($editor, 'web')->test(EditProduct::class, ['record' => $product->id])
            ->assertDontSee('TON00000001');
    }
}
