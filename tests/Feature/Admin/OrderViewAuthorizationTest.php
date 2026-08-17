<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Order\Models\Order;
use Tests\TestCase;

class OrderViewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function order(): Order
    {
        return Order::factory()->create();
    }

    public function test_view_only_user_cannot_see_order_header_actions(): void
    {
        $viewer = User::factory()->create()
            ->givePermissionTo(Permission::OrderView->value);
        $order = $this->order();

        Livewire::actingAs($viewer, 'web')
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionHidden('changeStatus')
            ->assertActionHidden('syncToHesabfa')
            ->assertActionHidden('edit');
    }

    public function test_admin_sees_order_header_actions(): void
    {
        $admin = User::factory()->create()->assignRole(Role::Admin->value);
        $order = $this->order();

        Livewire::actingAs($admin, 'web')
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionVisible('changeStatus')
            ->assertActionVisible('syncToHesabfa')
            ->assertActionVisible('edit');
    }

    public function test_operator_without_hesabfa_sync_cannot_see_sync_action(): void
    {
        $operator = User::factory()->create()
            ->givePermissionTo(Permission::OrderView->value, Permission::OrderEdit->value);
        $order = $this->order();

        Livewire::actingAs($operator, 'web')
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertActionVisible('changeStatus')
            ->assertActionVisible('edit')
            ->assertActionHidden('syncToHesabfa');
    }
}
