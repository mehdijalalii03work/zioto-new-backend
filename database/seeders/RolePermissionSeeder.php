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

    /**
     * Roles and the permissions this application expects them to hold.
     * Single source of truth shared with PermissionSeeder.
     *
     * @return array<string, list<Permission>>
     */
    public static function rolePermissions(): array
    {
        return [
            Role::Admin->value => Permission::cases(),
            Role::Manager->value => [
                Permission::DashboardView,
                ...self::crud(Permission::ProductView, Permission::ProductCreate, Permission::ProductEdit, Permission::ProductDelete),
                Permission::ProductPricing,
                ...self::crud(Permission::CategoryView, Permission::CategoryCreate, Permission::CategoryEdit, Permission::CategoryDelete),
                ...self::crud(Permission::BrandView, Permission::BrandCreate, Permission::BrandEdit, Permission::BrandDelete),
                ...self::crud(Permission::OrderView, Permission::OrderCreate, Permission::OrderEdit, Permission::OrderDelete),
                ...self::crud(Permission::PaymentView, Permission::PaymentCreate, Permission::PaymentEdit, Permission::PaymentDelete),
                ...self::crud(Permission::CustomerView, Permission::CustomerCreate, Permission::CustomerEdit, Permission::CustomerDelete),
                ...self::crud(Permission::ShippingView, Permission::ShippingCreate, Permission::ShippingEdit, Permission::ShippingDelete),
                ...self::crud(Permission::BlogPostView, Permission::BlogPostCreate, Permission::BlogPostEdit, Permission::BlogPostDelete),
                ...self::crud(Permission::BlogCategoryView, Permission::BlogCategoryCreate, Permission::BlogCategoryEdit, Permission::BlogCategoryDelete),
                ...self::crud(Permission::BlogTagView, Permission::BlogTagCreate, Permission::BlogTagEdit, Permission::BlogTagDelete),
                ...self::crud(Permission::ContactMessageView, Permission::ContactMessageEdit, Permission::ContactMessageDelete),
                Permission::ManagementReportView,
                Permission::PricingView,
                Permission::PricingEdit,
                Permission::DiscountView,
                Permission::DiscountEdit,
            ],
            Role::Operator->value => [
                Permission::DashboardView,
                Permission::OrderView,
                Permission::OrderEdit,
                Permission::CustomerView,
            ],
        ];
    }

    private function seedRoles(): void
    {
        foreach (self::rolePermissions() as $roleName => $permissions) {
            $model = RoleModel::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $model->syncPermissions(array_map(
                static fn (Permission $permission): string => $permission->value,
                $permissions,
            ));
        }
    }

    /** @param list<Permission> $permissions */
    private static function crud(Permission ...$permissions): array
    {
        return $permissions;
    }
}
