<?php

namespace Tests\Feature\Inventory;

use App\Models\ProductSerial;
use App\Models\ProductStockReservation;
use App\Models\SerialEvent;
use App\Models\User;
use App\Services\Inventory\SerialInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * Stage 4 — finalize, shipment, return (plan §5.1, §5.4).
 */
class SerialAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSerialProduct(string $prefix = 'ton'): Product
    {
        return Product::factory()->serial($prefix)->create([
            'name' => 'شمش '.$prefix,
            'slug' => 'serial-'.$prefix.'-'.uniqid(),
        ]);
    }

    private function makeManualProduct(int $stock = 5, bool $madeToOrder = false, bool $trackSerials = false): Product
    {
        return Product::factory()->create([
            'name' => 'شمش دستی',
            'slug' => 'manual-'.uniqid(),
            'stock_quantity' => $stock,
            'made_to_order' => $madeToOrder,
            'track_serials' => $trackSerials,
        ]);
    }

    private function makePaidOrder(string $platform = 'main'): Order
    {
        return OrderFactory::new()->create([
            'platform' => $platform,
            'payment_status' => 'paid',
            'serial_inventory_enabled' => true,
        ]);
    }

    private function addItem(Order $order, Product $product, int $quantity): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => $quantity,
            'subtotal' => 1000000 * $quantity,
        ]);
    }

    private function addSerials(Product $product, int $count, string $prefix = 'ton', int $start = 1): void
    {
        for ($i = $start; $i < $start + $count; $i++) {
            ProductSerial::create([
                'product_id' => $product->id,
                'serial_code' => $prefix.str_pad((string) (70000000 + $i), 8, '0', STR_PAD_LEFT),
                'serial_prefix' => $prefix,
                'status' => 'available',
            ]);
        }
    }

    private function makePayment(Order $order, string $status = 'pending'): Payment
    {
        return Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'platform' => $order->platform,
            'transaction_id' => 'txn-'.uniqid(),
            'amount' => 1000000,
            'status' => $status,
        ]);
    }

    private function service(): SerialInventoryService
    {
        return app(SerialInventoryService::class);
    }

    public function test_finalize_converts_reserved_to_allocated_without_extra_consumption(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makePaidOrder();
        $item = $this->addItem($order, $product, 2);

        $this->service()->reserveForPayment($order->id, 'main');
        $this->service()->finalizePaidOrder($order->id, 'main');

        $this->assertSame(2, ProductSerial::where('current_order_item_id', $item->id)->where('status', 'allocated')->count());
        $this->assertSame(0, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
        $this->assertSame('allocated', $order->fresh()->serial_fulfillment_status->value);
        $this->assertSame(2, SerialEvent::where('order_id', $order->id)->where('event_type', 'allocated')->count());
    }

    public function test_finalize_replaces_released_reservation_from_free_stock(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 3);
        $order = $this->makePaidOrder();
        $item = $this->addItem($order, $product, 2);

        $this->service()->reserveForPayment($order->id, 'main');

        // One reservation is lost before verify; free stock must fill the gap.
        $lost = ProductSerial::where('current_order_item_id', $item->id)->orderBy('unit_number')->firstOrFail();
        $lost->update(['status' => 'available', 'current_order_item_id' => null, 'unit_number' => null]);

        $this->service()->finalizePaidOrder($order->id, 'main');

        $this->assertSame(2, ProductSerial::where('current_order_item_id', $item->id)->where('status', 'allocated')->count());
        $this->assertSame('allocated', $order->fresh()->serial_fulfillment_status->value);
    }

    public function test_finalize_shortage_keeps_paid_and_marks_needs_review(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makePaidOrder();
        $item = $this->addItem($order, $product, 2);

        $this->service()->reserveForPayment($order->id, 'main');

        // A reserved bar is damaged before verify; nothing free remains.
        ProductSerial::where('current_order_item_id', $item->id)->orderBy('unit_number')->firstOrFail()
            ->update(['status' => 'damaged']);

        $this->service()->finalizePaidOrder($order->id, 'main');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('needs_review', $order->fresh()->serial_fulfillment_status->value);
        $this->assertSame(1, ProductSerial::where('current_order_item_id', $item->id)->where('status', 'reserved')->count());
        $this->assertSame(0, ProductSerial::where('current_order_item_id', $item->id)->where('status', 'allocated')->count());
    }

    public function test_finalize_is_idempotent(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makePaidOrder();
        $item = $this->addItem($order, $product, 2);

        $this->service()->reserveForPayment($order->id, 'main');
        $this->service()->finalizePaidOrder($order->id, 'main');
        $this->service()->finalizePaidOrder($order->id, 'main');

        $this->assertSame(2, ProductSerial::where('current_order_item_id', $item->id)->where('status', 'allocated')->count());
        $this->assertSame(2, SerialEvent::where('order_id', $order->id)->where('event_type', 'allocated')->count());
    }

    public function test_finalize_requires_paid_order(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = OrderFactory::new()->create(['platform' => 'main', 'payment_status' => 'pending', 'serial_inventory_enabled' => true]);
        $this->addItem($order, $product, 1);

        $this->service()->reserveForPayment($order->id, 'main');

        $this->expectException(\LogicException::class);

        $this->service()->finalizePaidOrder($order->id, 'main');
    }

    public function test_manual_finalize_consumes_capacity_exactly_once(): void
    {
        $product = $this->makeManualProduct(5);
        $order = $this->makePaidOrder();
        $item = $this->addItem($order, $product, 2);
        $payment = $this->makePayment($order);

        $this->service()->reserveForPayment($order->id, 'main', $payment->id);
        $this->service()->finalizePaidOrder($order->id, 'main');
        $this->service()->finalizePaidOrder($order->id, 'main');

        $this->assertSame('consumed', ProductStockReservation::where('order_item_id', $item->id)->firstOrFail()->status->value);
        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
    }

    public function test_made_to_order_becomes_awaiting_production(): void
    {
        $product = $this->makeManualProduct(5, true);
        $order = $this->makePaidOrder();
        $item = $this->addItem($order, $product, 1);
        $payment = $this->makePayment($order);

        $this->service()->reserveForPayment($order->id, 'main', $payment->id);
        $this->service()->finalizePaidOrder($order->id, 'main');

        $this->assertSame('awaiting_production', $order->fresh()->serial_fulfillment_status->value);
        $this->assertSame(4, (int) $product->fresh()->stock_quantity);
    }

    public function test_release_before_shipment_frees_allocated_serials(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makePaidOrder();
        $item = $this->addItem($order, $product, 2);
        $actor = User::factory()->create();

        $this->service()->reserveForPayment($order->id, 'main');
        $this->service()->finalizePaidOrder($order->id, 'main');
        $this->service()->releaseBeforeShipment($order->id, 'main', $actor->id, 'لغو توسط مشتری');

        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
        $this->assertSame(2, SerialEvent::where('order_id', $order->id)->where('event_type', 'allocation_released')->count());
        $this->assertSame('not_required', $order->fresh()->serial_fulfillment_status->value);
    }

    public function test_mark_shipped_requires_full_allocation(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makePaidOrder();
        $this->addItem($order, $product, 2);
        $actor = User::factory()->create();

        $this->service()->reserveForPayment($order->id, 'main');

        try {
            $this->service()->markShipped($order->id, 'main', $actor->id);
            $this->fail('Expected LogicException for partial allocation.');
        } catch (\LogicException) {
            // Expected.
        }

        $this->service()->finalizePaidOrder($order->id, 'main');
        $this->service()->markShipped($order->id, 'main', $actor->id);

        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'shipped')->count());
        $this->assertSame('shipped', $order->fresh()->serial_fulfillment_status->value);
    }

    public function test_return_and_restock_cycle_preserves_history(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $firstOrder = $this->makePaidOrder();
        $this->addItem($firstOrder, $product, 1);
        $actor = User::factory()->create();

        $this->service()->reserveForPayment($firstOrder->id, 'main');
        $this->service()->finalizePaidOrder($firstOrder->id, 'main');
        $this->service()->markShipped($firstOrder->id, 'main', $actor->id);

        $serial = ProductSerial::where('product_id', $product->id)->firstOrFail();

        $this->service()->receiveReturn($serial->id, $actor->id, 'مرجوعی مشتری');

        $this->assertSame('returned', $serial->fresh()->status->value);
        $this->assertNotNull($serial->fresh()->current_order_item_id);

        $this->service()->restockReturnedSerial($serial->id, $actor->id, 'تأیید QC');

        $restocked = $serial->fresh();

        $this->assertSame('available', $restocked->status->value);
        $this->assertNull($restocked->current_order_item_id);

        // The bar is sellable again with both orders kept in history.
        $secondOrder = $this->makePaidOrder();
        $secondItem = $this->addItem($secondOrder, $product, 1);
        $this->service()->reserveForPayment($secondOrder->id, 'main');

        $this->assertSame($secondItem->id, $restocked->fresh()->current_order_item_id);
        $this->assertSame(6, SerialEvent::where('product_serial_id', $serial->id)->count());
    }
}
