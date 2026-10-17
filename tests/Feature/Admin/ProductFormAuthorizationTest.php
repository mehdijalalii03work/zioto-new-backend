<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Product\Ayar;
use App\Enums\Product\MetalType;
use App\Enums\Product\ProductShape;
use App\Enums\Role;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Product\Models\Product;
use Tests\TestCase;

class ProductFormAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Test Product',
            'slug' => 'test-product',
            'status' => 'published',
            'visibility' => 'public',
            'metal_type' => MetalType::Gold,
            'form' => ProductShape::Pelak,
            'ayar' => Ayar::P999,
            'price_type' => 'fixed',
            'price' => 1000000,
            'stock_quantity' => 5,
        ]);
    }

    public function test_user_without_pricing_or_hesabfa_permissions_sees_only_basic_sections(): void
    {
        $user = User::factory()->create()
            ->givePermissionTo(Permission::ProductView->value, Permission::ProductEdit->value);
        $product = $this->product();

        Livewire::actingAs($user, 'web')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->assertFormFieldVisible('name')
            ->assertFormFieldHidden('price')
            ->assertFormFieldHidden('stock_quantity')
            ->assertFormFieldHidden('price_board_item')
            ->assertFormFieldHidden('hesabfa_manual_reserved')
            ->set('data.price_type', 'dynamic')
            ->assertFormFieldHidden('price_board_item');
    }

    public function test_manager_sees_pricing_but_not_hesabfa_section(): void
    {
        $manager = User::factory()->create()->assignRole(Role::Manager->value);
        $product = $this->product();

        Livewire::actingAs($manager, 'web')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->assertFormFieldVisible('price')
            ->assertFormFieldVisible('stock_quantity')
            ->assertFormFieldHidden('hesabfa_manual_reserved')
            ->set('data.price_type', 'dynamic')
            ->assertFormFieldVisible('price_board_item');
    }

    public function test_admin_sees_all_sections(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        $product = $this->product();

        Livewire::actingAs($admin, 'web')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->assertFormFieldVisible('price')
            ->assertFormFieldVisible('stock_quantity')
            ->assertFormFieldVisible('hesabfa_manual_reserved')
            ->set('data.price_type', 'dynamic')
            ->assertFormFieldVisible('price_board_item');
    }

    public function test_base_product_options_exclude_the_record_being_edited(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        $product = $this->product();

        $otherProduct = Product::create([
            'name' => 'Other Product',
            'slug' => 'other-product',
            'status' => 'published',
            'visibility' => 'public',
            'metal_type' => MetalType::Gold,
            'form' => ProductShape::Pelak,
            'ayar' => Ayar::P999,
            'price_type' => 'fixed',
            'price' => 2000000,
            'stock_quantity' => 3,
        ]);

        $component = Livewire::actingAs($admin, 'web')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->assertFormFieldExists('base_product_id')
            ->instance()
            ->getSchema('form')
            ->getComponentByStatePath('base_product_id', withHidden: true);

        $this->assertInstanceOf(Select::class, $component);
        $this->assertArrayHasKey($otherProduct->getKey(), $component->getOptions());
        $this->assertArrayNotHasKey($product->getKey(), $component->getOptions());
    }

    public function test_base_product_options_load_on_create_without_a_record(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        $product = $this->product();

        $component = Livewire::actingAs($admin, 'web')
            ->test(CreateProduct::class)
            ->assertFormFieldExists('base_product_id')
            ->instance()
            ->getSchema('form')
            ->getComponentByStatePath('base_product_id', withHidden: true);

        $this->assertInstanceOf(Select::class, $component);
        $this->assertArrayHasKey($product->getKey(), $component->getOptions());
    }
}
