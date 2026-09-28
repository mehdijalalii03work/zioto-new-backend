<?php

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds permissions and role grants that are missing from an existing database.
 *
 * Unlike RolePermissionSeeder it never deletes a permission and never revokes a
 * grant, so it is safe to run on a live database after a deploy.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permission::cases() as $permission) {
            PermissionModel::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']);
        }

        foreach (RolePermissionSeeder::rolePermissions() as $roleName => $permissions) {
            $role = RoleModel::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo(array_map(
                static fn (Permission $permission): string => $permission->value,
                $permissions,
            ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
