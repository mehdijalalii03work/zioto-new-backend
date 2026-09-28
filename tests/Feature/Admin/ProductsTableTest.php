<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Column;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
}
