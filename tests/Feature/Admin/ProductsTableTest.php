<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Column;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Product\Models\Product;
use Tests\TestCase;

class ProductsTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public function test_dynamic_pricing_column_is_hidden_by_default(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        $component = Livewire::actingAs($user, 'web')
            ->test(ListProducts::class)
            ->assertSuccessful();

        $column = collect($component->instance()->getTable()->getColumns())
            ->first(fn (Column $column): bool => $column->getName() === 'dynamic_pricing');

        $this->assertNotNull($column);
        $this->assertTrue($column->isToggleable());
        $this->assertTrue($column->isToggledHidden());

        $html = $this->actingAs($user, 'web')
            ->get('/admin/products')
            ->assertOk()
            ->getContent();

        preg_match('/<thead.*?<\/thead>/Us', $html, $matches);
        $header = $matches[0] ?? '';

        $this->assertStringContainsString('قیمت (تومان)', $header);
        $this->assertStringNotContainsString('قیمت‌گذاری پویای اجرت', $header);
    }

    public function test_admin_can_copy_a_product_as_a_private_draft_without_inventory_or_external_ids(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);
        $product = Product::create([
            'name' => 'Gold bar',
            'slug' => 'gold-bar',
            'sku' => 'GOLD-1',
            'tapsi_product_id' => 'TAPSI-1',
            'status' => 'published',
            'visibility' => 'public',
            'price' => 1000000,
            'stock_quantity' => 5,
            'hesabfa_physical_stock' => 5,
            'hesabfa_reserved_stock' => 2,
        ]);

        Livewire::actingAs($user, 'web')
            ->test(ListProducts::class)
            ->assertTableActionVisible('copy', $product)
            ->callTableAction('copy', $product)
            ->assertHasNoActionErrors();

        $copy = Product::query()->whereKeyNot($product->getKey())->sole();
        $this->assertSame('Gold bar (کپی)', $copy->name);
        $this->assertStringStartsWith('gold-bar-copy-', $copy->slug);
        $this->assertNull($copy->sku);
        $this->assertNull($copy->tapsi_product_id);
        $this->assertSame('draft', $copy->status);
        $this->assertSame('private', $copy->visibility);
        $this->assertSame(0, $copy->stock_quantity);
        $this->assertSame('0.00', $copy->hesabfa_physical_stock);
        $this->assertSame('0.00', $copy->hesabfa_reserved_stock);
        $this->assertSame('1000000.00', $copy->price);
        $this->assertSame('published', $product->fresh()->status);
    }

    public function test_user_without_create_permission_cannot_copy_a_product(): void
    {
        $user = User::factory()->create()->givePermissionTo(Permission::ProductView->value);
        $product = Product::create([
            'name' => 'Gold bar',
            'slug' => 'gold-bar',
            'status' => 'published',
            'visibility' => 'public',
            'price' => 1000000,
        ]);

        Livewire::actingAs($user, 'web')
            ->test(ListProducts::class)
            ->assertTableActionHidden('copy', $product);

        $this->assertSame(1, Product::count());
    }

    public function test_copy_includes_independent_product_images(): void
    {
        Storage::fake('public');
        $user = User::factory()->create()->assignRole(Role::Admin->value);
        $product = Product::create([
            'name' => 'Gold bar',
            'slug' => 'gold-bar',
            'status' => 'published',
            'visibility' => 'public',
            'price' => 1000000,
        ]);
        $upload = UploadedFile::fake()->image('gold.png');
        Storage::disk('public')->put('product-images/gold.png', file_get_contents($upload->getRealPath()));
        $product->images()->create([
            'image_path' => 'product-images/gold.png',
            'alt' => 'Gold',
            'is_primary' => true,
        ]);
        $product->addMediaFromString(file_get_contents($upload->getRealPath()))
            ->usingFileName('gold.png')
            ->toMediaCollection('product-images', 'public');

        Livewire::actingAs($user, 'web')
            ->test(ListProducts::class)
            ->callTableAction('copy', $product)
            ->assertHasNoActionErrors();

        $copy = Product::query()->whereKeyNot($product->getKey())->sole();
        $copiedImage = $copy->images()->sole();
        $this->assertSame('Gold', $copiedImage->alt);
        $this->assertTrue($copiedImage->is_primary);
        $this->assertNotSame('product-images/gold.png', $copiedImage->image_path);
        Storage::disk('public')->assertExists($copiedImage->image_path);
        $this->assertCount(1, $copy->getMedia('product-images'));
        $this->assertNotSame($product->getFirstMedia('product-images')->getPath(), $copy->getFirstMedia('product-images')->getPath());
    }
}
