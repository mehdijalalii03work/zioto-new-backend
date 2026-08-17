<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Pages\Hesabfa\HesabfaSyncLogs;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Permissions\PermissionResource;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class AuthorizationTest extends TestCase
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
        $user = User::factory()->create();

        return $user->assignRole($role->value);
    }

    public function test_seeder_is_idempotent_and_removes_stale_permissions(): void
    {
        PermissionModel::create(['name' => 'manage-stale', 'guard_name' => 'web']);

        $this->seed(RolePermissionSeeder::class);

        $this->assertNull(PermissionModel::where('name', 'manage-stale')->first());
        $this->assertSame(count(Permission::cases()), PermissionModel::count());

        $admin = RoleModel::where('name', Role::Admin->value)->first();
        $this->assertCount(count(Permission::cases()), $admin->permissions);
    }

    public function test_operator_cannot_access_product_listing(): void
    {
        $operator = $this->staffUser(Role::Operator);

        Livewire::actingAs($operator, 'web')
            ->test(ListProducts::class)
            ->assertForbidden();
    }

    public function test_operator_cannot_access_settings_page(): void
    {
        $operator = $this->staffUser(Role::Operator);

        Livewire::actingAs($operator, 'web')
            ->test(ManageSettings::class)
            ->assertForbidden();
    }

    public function test_operator_cannot_access_hesabfa_sync_logs(): void
    {
        $operator = $this->staffUser(Role::Operator);

        Livewire::actingAs($operator, 'web')
            ->test(HesabfaSyncLogs::class)
            ->assertForbidden();
    }

    public function test_manager_can_access_products_but_not_settings(): void
    {
        $manager = $this->staffUser(Role::Manager);

        Livewire::actingAs($manager, 'web')
            ->test(ListProducts::class)
            ->assertSuccessful();

        Livewire::actingAs($manager, 'web')
            ->test(ManageSettings::class)
            ->assertForbidden();
    }

    public function test_admin_can_access_products_and_settings(): void
    {
        $admin = $this->staffUser(Role::Admin);

        Livewire::actingAs($admin, 'web')
            ->test(ListProducts::class)
            ->assertSuccessful();

        Livewire::actingAs($admin, 'web')
            ->test(ManageSettings::class)
            ->assertSuccessful();
    }

    public function test_roles_resource_is_admin_only(): void
    {
        $manager = $this->staffUser(Role::Manager);
        $this->actingAs($manager, 'web');

        $this->assertFalse(RoleResource::canAccess());
        $this->assertFalse(PermissionResource::canAccess());

        $admin = $this->staffUser(Role::Admin);
        $this->actingAs($admin, 'web');

        $this->assertTrue(RoleResource::canAccess());
        $this->assertTrue(PermissionResource::canAccess());
    }

    public function test_non_admin_cannot_assign_roles_when_editing_a_user(): void
    {
        $manager = $this->staffUser(Role::Manager);
        $customer = User::factory()->create();
        $role = RoleModel::where('name', Role::Operator->value)->first();

        Livewire::actingAs($manager, 'web')
            ->test(EditUser::class, ['record' => $customer->getKey()])
            ->fillForm([
                'first_name' => 'Ali',
                'last_name' => 'Rezaei',
                'roles' => [$role->getKey()],
            ])
            ->call('save');

        $this->assertFalse($customer->fresh()->hasRole(Role::Operator->value));
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $managerRole = RoleModel::where('name', Role::Manager->value)->first();

        Livewire::actingAs($admin, 'web')
            ->test(EditUser::class, ['record' => $admin->getKey()])
            ->fillForm([
                'first_name' => 'Admin',
                'last_name' => 'User',
                'roles' => [$managerRole->getKey()],
            ])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        $this->assertTrue($admin->fresh()->hasRole(Role::Admin->value));
    }

    public function test_admin_can_demote_fellow_admin_when_another_admin_remains(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $fellowAdmin = $this->staffUser(Role::Admin);
        $managerRole = RoleModel::where('name', Role::Manager->value)->first();

        // Two admins exist; demoting the fellow admin is allowed because the acting admin remains.
        Livewire::actingAs($admin, 'web')
            ->test(EditUser::class, ['record' => $fellowAdmin->getKey()])
            ->fillForm([
                'first_name' => 'Admin',
                'last_name' => 'User',
                'roles' => [$managerRole->getKey()],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($fellowAdmin->fresh()->hasRole(Role::Manager->value));
    }

    public function test_admin_role_cannot_be_deleted(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $adminRole = RoleModel::where('name', Role::Admin->value)->first();

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $adminRole));
    }

    public function test_manager_cannot_delete_a_staff_user(): void
    {
        $manager = $this->staffUser(Role::Manager);
        $staff = $this->staffUser(Role::Operator);

        $this->assertFalse(Gate::forUser($manager)->allows('delete', $staff));
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->staffUser(Role::Admin);

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
    }

    public function test_permission_in_use_cannot_be_deleted(): void
    {
        $admin = $this->staffUser(Role::Admin);
        $permission = PermissionModel::where('name', Permission::OrderView->value)->first();

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $permission));
    }
}
