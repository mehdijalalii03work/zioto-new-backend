<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Modules\Product\Models\Product;
use Tests\TestCase;

class SiteWarehouseTest extends TestCase
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

    private function createProduct(string $slug, string $priceType = 'dynamic'): Product
    {
        return Product::create([
            'name' => "محصول {$slug}",
            'slug' => $slug,
            'sku' => "SKU-{$slug}",
            'price' => 1000000,
            'price_type' => $priceType,
            'stock_quantity' => 5,
        ]);
    }

    public function test_analyst_can_view_the_warehouse_page(): void
    {
        $analyst = $this->staffUser(Role::Analyst);

        $this->actingAs($analyst, 'web')
            ->get(URL::route('filament.admin.pages.site-warehouse'))
            ->assertOk();
    }

    public function test_operator_without_hesabfa_permission_cannot_view_the_warehouse_page(): void
    {
        $operator = $this->staffUser(Role::Operator);

        $this->actingAs($operator, 'web')
            ->get(URL::route('filament.admin.pages.site-warehouse'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_the_warehouse_page(): void
    {
        $this->get(URL::route('filament.admin.pages.site-warehouse'))
            ->assertRedirect();
    }

    public function test_only_dynamic_products_are_listed(): void
    {
        $dynamic = $this->createProduct('dynamic-bar', 'dynamic');
        $fixed = $this->createProduct('fixed-bar', 'fixed');

        $analyst = $this->staffUser(Role::Analyst);

        $this->actingAs($analyst, 'web')
            ->get(URL::route('filament.admin.pages.site-warehouse'))
            ->assertOk()
            ->assertSee($dynamic->name, escape: false)
            ->assertDontSee($fixed->name, escape: false);
    }
}
