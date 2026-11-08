<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Reports\WeightSalesReport;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

class WeightSalesReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public function test_report_includes_item_labor_cost_in_daily_total_and_green_column(): void
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);
        $product = Product::create([
            'name' => 'شمش طلا',
            'slug' => 'gold-bar-report-test',
            'metal_type' => 'gold',
            'form' => 'shammesh',
            'weight' => 2,
            'price' => 100000,
        ]);
        $order = Order::factory()->create([
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 120000,
            'quantity' => 2,
            'subtotal' => 240000,
            'labor_cost' => 10000,
        ]);

        $component = Livewire::actingAs($user, 'web')
            ->test(WeightSalesReport::class)
            ->call('loadReport')
            ->assertSee('اجرت (ریال)')
            ->assertSee('200,000')
            ->assertSeeHtml('background-color: greenyellow');

        $this->assertSame(200000.0, (float) $component->get('report')->first()['labor_rial']);
    }

    public function test_daily_total_uses_final_order_amount_including_charges(): void
    {
        $product = Product::create([
            'name' => 'شمش طلا',
            'slug' => 'gold-bar-final-total-test',
            'metal_type' => 'gold',
            'form' => 'shammesh',
            'weight' => 1,
            'price' => 1000000,
        ]);
        $order = Order::factory()->create([
            'status' => 'completed',
            'total_amount' => 1140000,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => 1,
            'subtotal' => 1000000,
        ]);

        $component = Livewire::actingAs(User::factory()->create()->assignRole(Role::Admin->value), 'web')
            ->test(WeightSalesReport::class)
            ->call('loadReport')
            ->assertSee('مبلغ نهایی سفارش‌ها (ریال)');

        $row = $component->get('report')->first();

        $this->assertSame(1140000, (int) $row['total_rial']);
        $this->assertSame(1000000, (int) $row['bar_rial']);
    }
}
