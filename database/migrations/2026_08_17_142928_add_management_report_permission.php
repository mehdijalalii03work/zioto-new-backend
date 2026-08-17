<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'management-report.view',
            'guard_name' => 'web',
        ]);

        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole && ! $adminRole->hasPermissionTo($permission)) {
            $adminRole->givePermissionTo($permission);
        }

        $managerRole = Role::where('name', 'manager')->where('guard_name', 'web')->first();
        if ($managerRole && ! $managerRole->hasPermissionTo($permission)) {
            $managerRole->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        Permission::where('name', 'management-report.view')->delete();
    }
};
