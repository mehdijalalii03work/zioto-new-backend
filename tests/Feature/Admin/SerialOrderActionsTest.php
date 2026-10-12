<?php

namespace Tests\Feature\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Resources\Orders\Exports\OrderExcelExport;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\ProductSerial;
use App\Models\SerialEvent;
use App\Models\User;
use App\Services\Inventory\SerialInventoryService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Tests\TestCase;

/**
 * Stage 7 — order serial display, actions, guards (plan §8).
 */
class SerialOrderActionsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    private function staffUser(Role $role): User
    {
        return User::factory()->create()->assignRole($role->value);
    }

    private function userWith(Permission ...$permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission->value);
        }

        return $user;
    }

    private function makeSerialProduct(): Product
    {
        return Product::factory()->serial('ton')->create([
            'name' => 'شمش طلا',
            'slug' => 'serial-gold-'.uniqid(),
        ]);
    }

    private function addSerials(Product $product, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            ProductSerial::create([
                'product_id' => $product->id,
                'serial_code' => 'ton'.str_pad((string) (70000000 + $i), 8, '0', STR_PAD_LEFT),
                'serial_prefix' => 'ton',
                'status' => 'available',
            ]);
        }
    }

    private function makePaidOrder(Product $product, int $quantity): Order
    {
        $order = OrderFactory::new()->create([
            'platform' => 'main',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'serial_inventory_enabled' => true,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => $quantity,
            'subtotal' => 1000000 * $quantity,
        ]);

        return $order;
    }

    public function test_operator_with_order_view_sees_allocated_serials(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makePaidOrder($product, 2);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');
        app(SerialInventoryService::class)->finalizePaidOrder($order->id, 'main');

        $operator = $this->staffUser(Role::Operator);

        $this->actingAs($operator, 'web')
            ->get(URL::route('filament.admin.resources.orders.view', ['record' => $order->id]))
            ->assertOk()
            ->assertSee('ton70000001', escape: false)
            ->assertSee('ton70000002', escape: false);
    }

    public function test_ship_action_is_hidden_without_permission_and_works_with_it(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makePaidOrder($product, 1);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');
        app(SerialInventoryService::class)->finalizePaidOrder($order->id, 'main');

        $withoutPermission = $this->userWith(Permission::OrderView, Permission::OrderEdit);

        Livewire::actingAs($withoutPermission, 'web')
            ->test(ViewOrder::class, ['record' => $order->id])
            ->assertActionHidden('markShipped');

        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'allocated')->count());

        $shipper = $this->userWith(Permission::OrderView, Permission::SerialShip);

        Livewire::actingAs($shipper, 'web')
            ->test(ViewOrder::class, ['record' => $order->id])
            ->assertActionVisible('markShipped')
            ->callAction('markShipped');

        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'shipped')->count());
        $this->assertSame('shipped', $order->fresh()->serial_fulfillment_status->value);
    }

    public function test_release_action_frees_allocated_serials(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makePaidOrder($product, 2);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');
        app(SerialInventoryService::class)->finalizePaidOrder($order->id, 'main');

        $manager = $this->userWith(Permission::OrderView, Permission::SerialManage);

        Livewire::actingAs($manager, 'web')
            ->test(ViewOrder::class, ['record' => $order->id])
            ->callAction('releaseHoldings', data: ['reason' => 'لغو توسط مشتری']);

        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
    }

    public function test_finalize_retry_is_idempotent_from_panel(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makePaidOrder($product, 1);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');

        $manager = $this->userWith(Permission::OrderView, Permission::SerialManage);

        $page = Livewire::actingAs($manager, 'web')
            ->test(ViewOrder::class, ['record' => $order->id]);

        $page->callAction('retryFinalize');
        $page->callAction('retryFinalize');

        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'allocated')->count());
        $this->assertSame(1, SerialEvent::where('order_id', $order->id)->where('event_type', 'allocated')->count());
    }

    public function test_export_contains_serial_codes(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makePaidOrder($product, 2);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');
        app(SerialInventoryService::class)->finalizePaidOrder($order->id, 'main');

        $orders = Order::where('id', $order->id)->get();

        $response = app(OrderExcelExport::class)->export($orders);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'orderexport').'.xlsx';
        $this->tempFiles[] = $path;
        file_put_contents($path, $content);

        $reader = new XlsxReader;
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($path);
        $sheet = $workbook->getSheetByName('خروجی اکسل سایت');
        $this->assertNotNull($sheet);

        $dump = '';
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($sheet->getColumnIterator('A', $sheet->getHighestColumn()) as $column) {
                $dump .= ' '.$sheet->getCell($column->getColumnIndex().$row->getRowIndex())->getValue();
            }
        }

        $this->assertStringContainsString('سریال‌ها', $dump);
        $this->assertStringContainsString('ton70000001', $dump);
        $this->assertStringContainsString('ton70000002', $dump);

        $workbook->disconnectWorksheets();
        unset($workbook);
    }

    public function test_edit_save_does_not_mutate_items(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makePaidOrder($product, 1);

        $editor = $this->userWith(Permission::OrderView, Permission::OrderEdit);

        Livewire::actingAs($editor, 'web')
            ->test(EditOrder::class, ['record' => $order->id])
            ->fillForm(['notes' => 'یادداشت تازه'])
            ->call('save')
            ->assertHasNoFormErrors();

        $item = $order->items()->firstOrFail();

        $this->assertSame($product->id, (int) $item->product_id);
        $this->assertSame(1, (int) $item->quantity);
    }

    public function test_delete_is_blocked_with_active_holdings(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makePaidOrder($product, 1);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');

        $deleter = $this->userWith(Permission::OrderView, Permission::OrderDelete);

        $this->assertFalse($deleter->can('delete', $order));

        app(SerialInventoryService::class)->releaseUnpaidHoldings($order->id, 'main');

        $this->assertTrue($deleter->fresh()->can('delete', $order->fresh()));
    }

    public function test_completed_status_change_does_not_ship(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makePaidOrder($product, 1);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');
        app(SerialInventoryService::class)->finalizePaidOrder($order->id, 'main');

        $editor = $this->userWith(Permission::OrderView, Permission::OrderEdit);

        Livewire::actingAs($editor, 'web')
            ->test(ViewOrder::class, ['record' => $order->id])
            ->callAction('changeStatus', data: ['status' => 'completed']);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'allocated')->count());
        $this->assertSame('allocated', $order->fresh()->serial_fulfillment_status->value);
    }
}
