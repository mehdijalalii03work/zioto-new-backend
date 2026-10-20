<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Filament\Pages\Reports\DuelReport;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Tests\TestCase;

class DuelReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public function test_duel_report_page_exists(): void
    {
        $this->assertTrue(class_exists(DuelReport::class));
    }

    public function test_duel_report_separates_parsian_site_tapsi_and_digipay_orders(): void
    {
        $this->createOrder('main', 'parsian', 100000);
        $this->createOrder('main', 'digipay', 200000);
        $this->createOrder('main', 'smartis', 500000);
        $this->createOrder('tapsi', 'parsian', 300000);
        $this->createOrder('tapsi', 'digipay', 400000);

        $component = Livewire::actingAs(User::factory()->create()->assignRole(Role::Admin->value), 'web')
            ->test(DuelReport::class)
            ->call('loadReport')
            ->assertSee('فروش نقدی از سایت زیوتو')
            ->assertSee('فروش اقساطی دیجی‌پی')
            ->assertSee('فروش تپسی‌شاپ');

        $reports = $component->get('reports');

        $this->assertSame(100000, (int) $reports['site']->first()['total_rial']);
        $this->assertSame(700000, (int) $reports['tapsi']->first()['total_rial']);
        $this->assertSame(200000, (int) $reports['digipay']->first()['total_rial']);
    }

    public function test_digipay_order_with_multiple_payments_is_excluded_from_the_site_table(): void
    {
        $order = $this->createOrder('main', 'parsian', 100000);

        Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'duel-digipay-'.uniqid(),
            'amount' => 100000,
            'payment_method' => 'online',
            'gateway' => 'digipay',
            'status' => 'success',
        ]);

        $reports = Livewire::actingAs(User::factory()->create()->assignRole(Role::Admin->value), 'web')
            ->test(DuelReport::class)
            ->call('loadReport')
            ->get('reports');

        $this->assertCount(0, $reports['site']);
        $this->assertSame(100000, (int) $reports['digipay']->first()['total_rial']);
    }

    private function createOrder(string $platform, string $gateway, int $amount): Order
    {
        $order = Order::factory()->create([
            'platform' => $platform,
            'status' => 'completed',
            'total_amount' => $amount,
        ]);

        Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'duel-'.uniqid(),
            'amount' => $amount,
            'payment_method' => 'online',
            'gateway' => $gateway,
            'status' => 'success',
        ]);

        $product = Product::create([
            'name' => 'شمش طلا '.uniqid(),
            'slug' => 'duel-report-'.uniqid(),
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
}
