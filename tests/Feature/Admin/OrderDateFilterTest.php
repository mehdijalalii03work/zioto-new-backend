<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Order\Models\Order;
use Morilog\Jalali\Jalalian;
use Tests\TestCase;

class OrderDateFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole(Role::Admin->value);
    }

    public function test_orders_table_has_jalali_created_at_filter(): void
    {
        $this->actingAs($this->admin(), 'web');

        Livewire::actingAs(User::first(), 'web')
            ->test(ListOrders::class)
            ->assertTableFilterExists('created_at');
    }

    public function test_created_at_filter_with_gregorian_range(): void
    {
        $admin = $this->admin();

        $old = Order::factory()->create([
            'status' => 'confirmed',
            'created_at' => Carbon::parse('2025-01-10 10:00:00'),
        ]);
        $middle = Order::factory()->create([
            'status' => 'confirmed',
            'created_at' => Carbon::parse('2025-03-15 10:00:00'),
        ]);
        $new = Order::factory()->create([
            'status' => 'confirmed',
            'created_at' => Carbon::parse('2025-06-20 10:00:00'),
        ]);

        Livewire::actingAs($admin, 'web')
            ->test(ListOrders::class)
            ->filterTable('created_at', [
                'from' => '2025-03-01',
                'until' => '2025-03-31',
            ])
            ->assertCanSeeTableRecords([$middle])
            ->assertCanNotSeeTableRecords([$old, $new]);
    }

    public function test_created_at_filter_with_jalali_range(): void
    {
        $admin = $this->admin();

        $targetDay = Carbon::parse('2025-03-15 10:00:00');

        $inside = Order::factory()->create([
            'status' => 'confirmed',
            'created_at' => $targetDay,
        ]);
        $outside = Order::factory()->create([
            'status' => 'confirmed',
            'created_at' => Carbon::parse('2025-06-20 10:00:00'),
        ]);

        $jalaliDay = Jalalian::fromCarbon($targetDay)->format('Y/m/d');

        Livewire::actingAs($admin, 'web')
            ->test(ListOrders::class)
            ->filterTable('created_at', [
                'from' => $jalaliDay,
                'until' => $jalaliDay,
            ])
            ->assertCanSeeTableRecords([$inside])
            ->assertCanNotSeeTableRecords([$outside]);
    }

    public function test_parse_filter_date_supports_both_calendars(): void
    {
        $this->assertTrue(
            OrdersTable::parseFilterDate('2025-03-15')?->isSameDay(Carbon::parse('2025-03-15'))
        );

        $jalali = Jalalian::fromCarbon(Carbon::parse('2025-03-15'))->format('Y/m/d');

        $this->assertTrue(
            OrdersTable::parseFilterDate($jalali)?->isSameDay(Carbon::parse('2025-03-15'))
        );

        $this->assertNull(OrdersTable::parseFilterDate(null));
        $this->assertNull(OrdersTable::parseFilterDate('not-a-date'));
    }
}
