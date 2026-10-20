<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Reports\TotalInvoiceReport;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

class TotalInvoiceReportProductFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public function test_report_separates_plaque_from_gold_and_silver_shammesh_and_saachmeh(): void
    {
        $order = Order::factory()->create([
            'status' => 'completed',
            'total_amount' => 1000,
        ]);

        $this->addItem($order, 'gold', 'shammesh', 100);
        $this->addItem($order, 'gold', 'saachmeh', 200);
        $this->addItem($order, 'gold', 'pelak', 300);
        $this->addItem($order, 'silver', 'saachmeh', 150);
        $this->addItem($order, 'silver', 'pelak', 250);

        $report = Livewire::actingAs(User::factory()->create()->assignRole(Role::Admin->value), 'web')
            ->test(TotalInvoiceReport::class)
            ->call('loadReport')
            ->get('report');

        $this->assertCount(1, $report);
        $this->assertSame(1, $report[0]['gold_count']);
        $this->assertSame(300.0, $report[0]['gold_amount']);
        $this->assertSame(1, $report[0]['silver_count']);
        $this->assertSame(150.0, $report[0]['silver_amount']);
        $this->assertSame(1, $report[0]['plaque_count']);
        $this->assertSame(550.0, $report[0]['plaque_amount']);
    }

    public function test_report_renders_plaque_columns(): void
    {
        $order = Order::factory()->create(['status' => 'completed']);
        $this->addItem($order, 'gold', 'pelak', 100);

        Livewire::actingAs(User::factory()->create()->assignRole(Role::Admin->value), 'web')
            ->test(TotalInvoiceReport::class)
            ->call('loadReport')
            ->assertSee('تعداد پلاک')
            ->assertSee('ریال پلاک');
    }

    private function addItem(Order $order, string $metalType, string $form, int $subtotal): void
    {
        $product = Product::create([
            'name' => "{$metalType}-{$form}-".uniqid(),
            'slug' => "{$metalType}-{$form}-".uniqid(),
            'price' => $subtotal,
            'stock_quantity' => 10,
            'metal_type' => $metalType,
            'form' => $form,
            'weight' => 1,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $subtotal,
            'quantity' => 1,
            'subtotal' => $subtotal,
        ]);
    }
}
