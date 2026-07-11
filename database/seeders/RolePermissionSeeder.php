<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permission::unguard(false);

        $permissions = [
            'dashboard',
            'manage-products',
            'manage-categories',
            'manage-brands',
            'manage-orders',
            'manage-customers',
            'manage-shipping',
            'manage-settings',
            'manage-blog',
            'manage-hesabfa',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = [
            'admin' => $permissions,
            'manager' => [
                'dashboard',
                'manage-products',
                'manage-categories',
                'manage-brands',
                'manage-orders',
                'manage-customers',
                'manage-shipping',
                'manage-blog',
            ],
            'operator' => [
                'dashboard',
                'manage-orders',
                'manage-customers',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
