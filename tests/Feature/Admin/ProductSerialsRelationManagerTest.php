<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ViewProductSerials;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\ProductImagesRelationManager;
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

    public function test_serials_moved_off_edit_page_to_dedicated_serials_page(): void
    {
        $relations = array_map(
            static fn ($configuration): string => $configuration->relationManager,
            ProductResource::getRelations(),
        );

        $this->assertContains(ProductImagesRelationManager::class, $relations);
        $this->assertNotContains(ProductSerialsRelationManager::class, $relations);
        $this->assertArrayHasKey('serials', ProductResource::getPages());
    }

    public function test_serial_viewer_sees_imported_serials_on_serials_page(): void
    {
        $product = $this->serialProduct();
        $manager = User::factory()->create()->assignRole(Role::Manager->value);
        $this->actingAs($manager, 'web');

        $this->assertTrue(ProductSerialsRelationManager::canViewForRecord($product, ViewProductSerials::class));

        // The relation table itself lazy-loads, so the page test asserts the
        // wired summary while the table content is covered by rendering the
        // relation manager directly.
        Livewire::actingAs($manager, 'web')->test(ViewProductSerials::class, ['record' => $product->id])
            ->assertSee('سریال‌های محصول')
            ->assertSee('1 / 1');

        Livewire::actingAs($manager, 'web')->test(ProductSerialsRelationManager::class, [
            'ownerRecord' => $product,
            'pageClass' => ViewProductSerials::class,
        ])->assertSee('TON00000001');
    }

    public function test_serials_page_is_forbidden_without_product_view(): void
    {
        $product = $this->serialProduct();
        $outsider = User::factory()->create();

        Livewire::actingAs($outsider, 'web')->test(ViewProductSerials::class, ['record' => $product->id])
            ->assertForbidden();
    }

    public function test_relation_manager_is_hidden_without_serial_view(): void
    {
        $product = $this->serialProduct();
        $editor = User::factory()->create()->givePermissionTo('product.view', 'product.edit');
        $this->actingAs($editor, 'web');

        $this->assertFalse(ProductSerialsRelationManager::canViewForRecord($product, ViewProductSerials::class));

        Livewire::actingAs($editor, 'web')->test(EditProduct::class, ['record' => $product->id])
            ->assertDontSee('TON00000001');
    }
}
