<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Reports\TotalInvoiceReport;
use App\Filament\Pages\Reports\WeightSalesReport;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Tests\TestCase;

class ReportGatewayFilterTest extends TestCase
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

    private function completedOrderWithGateway(string $gateway, int $amount = 100000): Order
    {
        $order = Order::factory()->create([
            'status' => 'completed',
            'total_amount' => $amount,
        ]);

        Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'txn-'.uniqid(),
            'amount' => $amount,
            'payment_method' => 'online',
            'gateway' => $gateway,
            'status' => 'success',
        ]);

        $product = Product::create([
            'name' => 'شمش طلا',
            'slug' => 'report-gateway-'.uniqid(),
            'price' => $amount,
            'stock_quantity' => 10,
            'metal_type' => 'gold',
            'form' => 'shammesh',
            'weight' => 1,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $amount,
            'quantity' => 1,
            'subtotal' => $amount,
        ]);

        return $order;
    }

    public function test_report_pages_offer_a_gateway_filter_defaulting_to_all_gateways(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin, 'web')
            ->test(TotalInvoiceReport::class)
            ->assertSee('درگاه پرداخت')
            ->assertSet('gateway', null)
            ->call('loadReport')
            ->assertSet('gateway', null);

        Livewire::actingAs($admin, 'web')
            ->test(WeightSalesReport::class)
            ->assertSee('درگاه پرداخت')
            ->assertSet('gateway', null)
            ->call('loadReport')
            ->assertSet('gateway', null);
    }

    public function test_total_invoice_report_includes_every_gateway_by_default(): void
    {
        $this->completedOrderWithGateway('parsian', 100000);
        $this->completedOrderWithGateway('digipay', 250000);

        $report = Livewire::actingAs($this->admin(), 'web')
            ->test(TotalInvoiceReport::class)
            ->call('loadReport')
            ->get('report');

        $this->assertCount(1, $report);
        $this->assertSame(2, $report[0]['invoice_count']);
        $this->assertSame(350000, (int) $report[0]['net_amount']);
    }

    public function test_total_invoice_report_filters_orders_by_selected_gateway(): void
    {
        $this->completedOrderWithGateway('parsian', 100000);
        $this->completedOrderWithGateway('digipay', 250000);

        $report = Livewire::actingAs($this->admin(), 'web')
            ->test(TotalInvoiceReport::class)
            ->set('gateway', 'digipay')
            ->call('loadReport')
            ->get('report');

        $this->assertCount(1, $report);
        $this->assertSame(1, $report[0]['invoice_count']);
        $this->assertSame(250000, (int) $report[0]['net_amount']);
    }

    public function test_total_invoice_report_is_empty_for_a_gateway_without_orders(): void
    {
        $this->completedOrderWithGateway('parsian');

        $report = Livewire::actingAs($this->admin(), 'web')
            ->test(TotalInvoiceReport::class)
            ->set('gateway', 'kamanlend')
            ->call('loadReport')
            ->get('report');

        $this->assertCount(0, $report);
    }

    public function test_weight_sales_report_filters_orders_by_selected_gateway(): void
    {
        $this->completedOrderWithGateway('parsian', 100000);
        $this->completedOrderWithGateway('smartis', 250000);

        $report = Livewire::actingAs($this->admin(), 'web')
            ->test(WeightSalesReport::class)
            ->set('gateway', 'smartis')
            ->call('loadReport')
            ->get('report');

        $this->assertCount(1, $report);
        $this->assertSame(250000, (int) $report[0]['total_rial']);
    }

    public function test_weight_sales_report_includes_every_gateway_by_default(): void
    {
        $this->completedOrderWithGateway('parsian', 100000);
        $this->completedOrderWithGateway('smartis', 250000);

        $report = Livewire::actingAs($this->admin(), 'web')
            ->test(WeightSalesReport::class)
            ->call('loadReport')
            ->get('report');

        $this->assertCount(1, $report);
        $this->assertSame(350000, (int) $report[0]['total_rial']);
    }
}
