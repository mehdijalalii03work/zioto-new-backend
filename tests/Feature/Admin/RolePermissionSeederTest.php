<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Pages\SerialImport;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_serial_permissions_for_web_guard(): void
    {
        $this->seed(RolePermissionSeeder::class);

        foreach (['serial.view', 'serial.import', 'serial.manage', 'serial.ship', 'serial.return', 'serial.restock'] as $name) {
            $this->assertTrue(
                PermissionModel::where('name', $name)->where('guard_name', 'web')->exists(),
                "Expected permission {$name} for guard web.",
            );
        }
    }

    public function test_seeder_grants_serial_permissions_to_expected_roles(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = RoleModel::findByName(Role::Admin->value, 'web');
        $manager = RoleModel::findByName(Role::Manager->value, 'web');
        $analyst = RoleModel::findByName(Role::Analyst->value, 'web');

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($admin->hasPermissionTo($permission->value));
        }

        $this->assertTrue($manager->hasPermissionTo(Permission::SerialView->value));
        $this->assertTrue($manager->hasPermissionTo(Permission::SerialImport->value));
        $this->assertTrue($analyst->hasPermissionTo(Permission::SerialView->value));
    }

    public function test_seeder_is_idempotent_across_deploy_runs(): void
    {
        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Re-running the seeder (as deploys do) must neither throw
        // PermissionDoesNotExist nor duplicate rows.
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(1, PermissionModel::where('name', 'serial.view')->where('guard_name', 'web')->count());
        $this->assertTrue(RoleModel::findByName(Role::Manager->value, 'web')->hasPermissionTo(Permission::SerialImport->value));
    }

    public function test_serial_import_page_visibility_follows_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $manager = User::factory()->create()->assignRole(Role::Manager->value);
        $operator = User::factory()->create()->assignRole(Role::Operator->value);

        $this->assertTrue($manager->can(Permission::SerialView->value));
        $this->assertTrue($operator->cannot(Permission::SerialView->value));

        $this->actingAs($manager, 'web');
        $this->assertTrue(SerialImport::canAccess());

        $this->actingAs($operator, 'web');
        $this->assertFalse(SerialImport::canAccess());
    }
}
