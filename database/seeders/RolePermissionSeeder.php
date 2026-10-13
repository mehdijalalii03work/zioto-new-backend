<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->forgetCachedPermissions();
        $this->seedPermissions();
        $this->forgetCachedPermissions();
        $this->seedRoles();
        $this->forgetCachedPermissions();
    }

    private function seedPermissions(): void
    {
        $validNames = Permission::values();

        foreach ($validNames as $name) {
            PermissionModel::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $staleNames = PermissionModel::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->reject(fn (string $name): bool => in_array($name, $validNames, true));

        if ($staleNames->isNotEmpty()) {
            PermissionModel::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $staleNames->all())
                ->delete();
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
            Permission::SerialView,
            Permission::SerialImport,
            Permission::SerialManage,
            Permission::SerialShip,
            Permission::SerialReturn,
            Permission::SerialRestock,
            Permission::ManagementReportView,
            Permission::AnalyticsReportView,
        ]);
        $this->seedRole(Role::Operator, [
            Permission::DashboardView,
            Permission::OrderView,
            Permission::OrderEdit,
            Permission::CustomerView,
        ]);
        $this->seedRole(Role::Analyst, [
            Permission::DashboardView,
            Permission::ManagementReportView,
            Permission::AnalyticsReportView,
            Permission::HesabfaView,
            Permission::SerialView,
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

        // Resolve permission models directly instead of passing names so a
        // stale Spatie cache can never fail the lookup with
        // PermissionDoesNotExist during deploys.
        $permissionModels = array_map(
            static fn (Permission $permission): PermissionModel => PermissionModel::firstOrCreate(['name' => $permission->value, 'guard_name' => 'web']),
            $permissions,
        );

        $model->syncPermissions($permissionModels);
    }

    private function forgetCachedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
