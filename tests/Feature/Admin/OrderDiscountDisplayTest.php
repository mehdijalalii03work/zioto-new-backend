<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Order\Models\Order;
use Tests\TestCase;

class OrderDiscountDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public function test_order_page_shows_applied_discount_code_and_amount(): void
    {
        $viewer = User::factory()->create()
            ->givePermissionTo(Permission::OrderView->value);
        $order = Order::factory()->create([
            'discount_code_snapshot' => 'ZIOTO-TEST',
            'discount_amount' => 250_000,
        ]);

        Livewire::actingAs($viewer, 'web')
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertSeeText('تخفیف اعمال شده')
            ->assertSeeText('ZIOTO-TEST')
            ->assertSeeText('250,000 ریال');
    }

    public function test_order_page_indicates_when_no_discount_was_applied(): void
    {
        $viewer = User::factory()->create()
            ->givePermissionTo(Permission::OrderView->value);
        $order = Order::factory()->create();

        Livewire::actingAs($viewer, 'web')
            ->test(ViewOrder::class, ['record' => $order->getKey()])
            ->assertSeeText('تخفیفی اعمال نشده')
            ->assertSeeText('کد تخفیف')
            ->assertSeeText('مبلغ تخفیف');
    }
}
