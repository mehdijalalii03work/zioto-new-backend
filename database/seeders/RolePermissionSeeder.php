<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedRoles();
    }

    private function seedPermissions(): void
    {
        $validNames = Permission::values();

        foreach ($validNames as $name) {
            PermissionModel::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $staleNames = PermissionModel::query()
            ->pluck('name')
            ->reject(fn (string $name): bool => in_array($name, $validNames, true));

        if ($staleNames->isNotEmpty()) {
            PermissionModel::query()->whereIn('name', $staleNames->all())->delete();
        }
    }

    private function seedRoles(): void
    {
        $this->seedRole(Role::Admin, Permission::cases());
        $this->seedRole(Role::Manager, [
            Permission::DashboardView,
            ...$this->crud(Permission::ProductView, Permission::ProductCreate, Permission::ProductEdit, Permission::ProductDelete),
            Permission::ProductPricing,
            ...$this->crud(Permission::CategoryView, Permission::CategoryCreate, Permission::CategoryEdit, Permission::CategoryDelete),
            ...$this->crud(Permission::BrandView, Permission::BrandCreate, Permission::BrandEdit, Permission::BrandDelete),
            ...$this->crud(Permission::OrderView, Permission::OrderCreate, Permission::OrderEdit, Permission::OrderDelete),
            ...$this->crud(Permission::PaymentView, Permission::PaymentCreate, Permission::PaymentEdit, Permission::PaymentDelete),
            ...$this->crud(Permission::CustomerView, Permission::CustomerCreate, Permission::CustomerEdit, Permission::CustomerDelete),
            ...$this->crud(Permission::ShippingView, Permission::ShippingCreate, Permission::ShippingEdit, Permission::ShippingDelete),
            ...$this->crud(Permission::BlogPostView, Permission::BlogPostCreate, Permission::BlogPostEdit, Permission::BlogPostDelete),
            ...$this->crud(Permission::BlogCategoryView, Permission::BlogCategoryCreate, Permission::BlogCategoryEdit, Permission::BlogCategoryDelete),
            ...$this->crud(Permission::BlogTagView, Permission::BlogTagCreate, Permission::BlogTagEdit, Permission::BlogTagDelete),
            ...$this->crud(Permission::ContactMessageView, Permission::ContactMessageEdit, Permission::ContactMessageDelete),
            Permission::ManagementReportView,
        ]);
        $this->seedRole(Role::Operator, [
            Permission::DashboardView,
            Permission::OrderView,
            Permission::OrderEdit,
            Permission::CustomerView,
        ]);
    }

    /** @param list<Permission> $permissions */
    private function crud(Permission ...$permissions): array
    {
        return $permissions;
    }

    /** @param array<int, Permission> $permissions */
    private function seedRole(Role $role, array $permissions): void
    {
        $model = RoleModel::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        $model->syncPermissions(array_map(
            static fn (Permission $permission): string => $permission->value,
            $permissions,
        ));
    }
}
