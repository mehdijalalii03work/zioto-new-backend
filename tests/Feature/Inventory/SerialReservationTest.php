<?php

namespace Tests\Feature\Inventory;

use App\Models\ProductSerial;
use App\Models\ProductStockReservation;
use App\Models\SerialEvent;
use App\Services\Inventory\SerialInventoryService;
use App\Services\Inventory\SerialStockShortageException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * Stage 4 — reservation before the gateway (plan §5.3).
 */
class SerialReservationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSerialProduct(string $prefix = 'ton'): Product
    {
        return Product::factory()->serial($prefix)->create([
            'name' => 'شمش '.$prefix,
            'slug' => 'serial-'.$prefix.'-'.uniqid(),
        ]);
    }

    private function makeManualProduct(int $stock = 5): Product
    {
        return Product::factory()->create([
            'name' => 'شمش دستی',
            'slug' => 'manual-'.uniqid(),
            'stock_quantity' => $stock,
            'made_to_order' => true,
        ]);
    }

    private function makeOrder(string $platform = 'main', bool $serialEnabled = true): Order
    {
        return OrderFactory::new()->create([
            'platform' => $platform,
            'serial_inventory_enabled' => $serialEnabled,
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

    private function addSerials(Product $product, int $count, string $prefix = 'ton'): void
    {
        for ($i = 1; $i <= $count; $i++) {
            ProductSerial::create([
                'product_id' => $product->id,
                'serial_code' => $prefix.str_pad((string) (70000000 + $i), 8, '0', STR_PAD_LEFT),
                'serial_prefix' => $prefix,
                'status' => 'available',
            ]);
        }
    }

    private function makePayment(Order $order): Payment
    {
        return Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'platform' => $order->platform,
            'transaction_id' => 'txn-'.uniqid(),
            'amount' => 1000000,
            'status' => 'initiating',
        ]);
    }

    private function service(): SerialInventoryService
    {
        return app(SerialInventoryService::class);
    }

    public function test_reserves_three_serials_fifo_with_units(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 5);
        $order = $this->makeOrder();
        $item = $this->addItem($order, $product, 3);

        $this->service()->reserveForPayment($order->id, 'main');

        $owned = ProductSerial::where('current_order_item_id', $item->id)->orderBy('unit_number')->get();

        $this->assertCount(3, $owned);
        $this->assertSame([1, 2, 3], $owned->pluck('unit_number')->all());
        $this->assertSame(
            ProductSerial::where('product_id', $product->id)->orderBy('id')->limit(3)->pluck('id')->all(),
            $owned->pluck('id')->all(),
        );
        $this->assertTrue($owned->every(fn ($serial): bool => $serial->status->value === 'reserved'));
        $this->assertSame('reserved', $order->fresh()->serial_fulfillment_status->value);
        $this->assertSame(3, SerialEvent::where('order_id', $order->id)->where('event_type', 'reserved')->count());
    }

    public function test_repeat_reserve_is_idempotent(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 3);
        $order = $this->makeOrder();
        $this->addItem($order, $product, 3);

        $this->service()->reserveForPayment($order->id, 'main');
        $this->service()->reserveForPayment($order->id, 'main');

        $this->assertSame(3, ProductSerial::where('product_id', $product->id)->where('status', 'reserved')->count());
        $this->assertSame(3, SerialEvent::where('order_id', $order->id)->count());
    }

    public function test_shortage_is_all_or_nothing_across_products(): void
    {
        $productA = $this->makeSerialProduct('ton');
        $productB = Product::factory()->serial('tfv')->create(['name' => 'شمش tfv', 'slug' => 'serial-tfv-'.uniqid()]);
        $this->addSerials($productA, 2, 'ton');

        $order = $this->makeOrder();
        $this->addItem($order, $productA, 2);
        $this->addItem($order, $productB, 1);

        try {
            $this->service()->reserveForPayment($order->id, 'main');
            $this->fail('Expected SerialStockShortageException.');
        } catch (SerialStockShortageException $e) {
            $this->assertSame($productB->id, $e->getProductId());
        }

        $this->assertSame(2, ProductSerial::where('product_id', $productA->id)->where('status', 'available')->count());
        $this->assertSame(0, SerialEvent::where('order_id', $order->id)->count());
    }

    public function test_tapsi_order_is_ignored(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makeOrder('tapsi');
        $this->addItem($order, $product, 2);

        $this->service()->reserveForPayment($order->id, 'tapsi');

        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
        $this->assertSame(0, SerialEvent::count());
    }

    public function test_legacy_order_without_flag_is_ignored(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makeOrder('main', false);
        $this->addItem($order, $product, 2);

        $this->service()->reserveForPayment($order->id, 'main');

        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
    }

    public function test_expired_reservation_is_released(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makeOrder();
        $item = $this->addItem($order, $product, 2);

        $this->service()->reserveForPayment($order->id, 'main');

        ProductSerial::where('current_order_item_id', $item->id)->update(['reserved_until' => now()->subMinute()]);

        $this->service()->releaseExpiredReservation($order->id, 'main');

        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
        $this->assertSame(2, SerialEvent::where('order_id', $order->id)->where('event_type', 'reservation_released')->count());
        $this->assertSame('not_required', $order->fresh()->serial_fulfillment_status->value);
    }

    public function test_expiry_of_paid_order_finalizes_instead_of_releasing(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 2);
        $order = $this->makeOrder();
        $item = $this->addItem($order, $product, 2);

        $this->service()->reserveForPayment($order->id, 'main');
        $order->update(['payment_status' => 'paid']);
        ProductSerial::where('current_order_item_id', $item->id)->update(['reserved_until' => now()->subMinute()]);

        $this->service()->releaseExpiredReservation($order->id, 'main');

        $this->assertSame(2, ProductSerial::where('product_id', $product->id)->where('status', 'allocated')->count());
    }

    public function test_manual_reserve_creates_reservation_without_consuming_capacity(): void
    {
        $product = $this->makeManualProduct(5);
        $order = $this->makeOrder();
        $item = $this->addItem($order, $product, 2);
        $payment = $this->makePayment($order);

        $this->service()->reserveForPayment($order->id, 'main', $payment->id);

        $reservation = ProductStockReservation::where('order_item_id', $item->id)->firstOrFail();

        $this->assertSame('reserved', $reservation->status->value);
        $this->assertSame(2, $reservation->quantity);
        $this->assertSame(5, (int) $product->fresh()->stock_quantity);
    }

    public function test_manual_reserve_requires_payment(): void
    {
        $product = $this->makeManualProduct();
        $order = $this->makeOrder();
        $this->addItem($order, $product, 1);

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->reserveForPayment($order->id, 'main');
    }

    public function test_expiry_command_releases_expired_orders(): void
    {
        $product = $this->makeSerialProduct();
        $this->addSerials($product, 1);
        $order = $this->makeOrder();
        $item = $this->addItem($order, $product, 1);

        $this->service()->reserveForPayment($order->id, 'main');
        ProductSerial::where('current_order_item_id', $item->id)->update(['reserved_until' => now()->subMinute()]);

        $this->artisan('serials:release-expired')->assertSuccessful();

        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
    }
}
