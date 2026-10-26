<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\SmsIr\SmsIrApiLogs;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Tests\TestCase;

class SmsIrApiLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public function test_only_admins_can_access_sms_ir_logs_page(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        $operator = User::factory()->create()->assignRole(Role::Operator->value);

        $this->actingAs($admin, 'web');
        $this->assertTrue(SmsIrApiLogs::canAccess());

        $this->actingAs($operator, 'web');
        $this->assertFalse(SmsIrApiLogs::canAccess());
    }

    public function test_page_renders_empty_state(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        $page = new class extends SmsIrApiLogs
        {
            protected function newLogsQuery()
            {
                $builder = \Mockery::mock();
                $builder->shouldReceive('where')->andReturnSelf();
                $builder->shouldReceive('paginate')->andReturn(new LengthAwarePaginator([], 0, 20));

                return $builder;
            }
        };

        Livewire::actingAs($admin, 'web')
            ->test($page::class)
            ->assertSuccessful()
            ->assertSee('لاگی یافت نشد');
    }
}
