<?php

namespace Tests\Feature\Inventory;

use App\Models\ProductSerial;
use App\Services\Inventory\SerialInventoryService;
use App\Services\Inventory\SerialStockShortageException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * Stage 4 — concurrency guards (plan §5.2).
 *
 * SQLite cannot prove pessimistic locking, so true parallel reservation is
 * covered by a MySQL-only test below. The sqlite-safe tests pin the
 * sequential contract: one bar, two orders → exactly one reservation.
 */
class SerialConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeSerialProduct(string $prefix = 'ton'): Product
    {
        return Product::factory()->serial($prefix)->create([
            'name' => 'شمش '.$prefix,
            'slug' => 'serial-'.$prefix.'-'.uniqid(),
        ]);
    }

    private function makePaidOrder(): Order
    {
        return OrderFactory::new()->create([
            'platform' => 'main',
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

    public function test_second_order_for_last_serial_fails_without_side_effects(): void
    {
        $product = $this->makeSerialProduct();
        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'ton70000001',
            'serial_prefix' => 'ton',
            'status' => 'available',
        ]);

        $firstOrder = $this->makePaidOrder();
        $this->addItem($firstOrder, $product, 1);

        $secondOrder = $this->makePaidOrder();
        $this->addItem($secondOrder, $product, 1);

        app(SerialInventoryService::class)->reserveForPayment($firstOrder->id, 'main');

        try {
            app(SerialInventoryService::class)->reserveForPayment($secondOrder->id, 'main');
            $this->fail('Expected SerialStockShortageException for the second order.');
        } catch (SerialStockShortageException) {
            // Expected: the single bar is already reserved.
        }

        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'reserved')->count());
        $this->assertSame(0, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
    }

    public function test_repeated_finalize_keeps_exact_count(): void
    {
        $product = $this->makeSerialProduct();
        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'ton70000001',
            'serial_prefix' => 'ton',
            'status' => 'available',
        ]);

        $order = $this->makePaidOrder();
        $item = $this->addItem($order, $product, 1);

        $service = app(SerialInventoryService::class);
        $service->reserveForPayment($order->id, 'main');
        $service->finalizePaidOrder($order->id, 'main');
        $service->finalizePaidOrder($order->id, 'main');

        $this->assertSame(1, ProductSerial::where('current_order_item_id', $item->id)->where('status', 'allocated')->count());
    }

    public function test_products_are_locked_in_stable_id_order(): void
    {
        $productA = $this->makeSerialProduct('ton');
        $productB = Product::factory()->serial('tfv')->create(['name' => 'شمش tfv', 'slug' => 'serial-tfv-'.uniqid()]);

        foreach ([$productA, $productB] as $product) {
            ProductSerial::create([
                'product_id' => $product->id,
                'serial_code' => $product->serial_prefix.'70000001',
                'serial_prefix' => $product->serial_prefix,
                'status' => 'available',
            ]);
        }

        $order = $this->makePaidOrder();
        // Deliberately reversed insertion order; locking must still be by id.
        $this->addItem($order, $productB, 1);
        $this->addItem($order, $productA, 1);

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');

        $this->assertSame(2, ProductSerial::where('status', 'reserved')->count());
    }

    /**
     * Sequential behavior complements the independent-process tests in
     * SerialMySqlConcurrencyTest.
     */
    public function test_repeated_reserve_for_last_serial_grants_single_winner(): void
    {
        $product = $this->makeSerialProduct();
        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'ton70000001',
            'serial_prefix' => 'ton',
            'status' => 'available',
        ]);

        $firstOrder = $this->makePaidOrder();
        $this->addItem($firstOrder, $product, 1);

        $secondOrder = $this->makePaidOrder();
        $this->addItem($secondOrder, $product, 1);

        app(SerialInventoryService::class)->reserveForPayment($firstOrder->id, 'main');

        $this->expectException(SerialStockShortageException::class);

        app(SerialInventoryService::class)->reserveForPayment($secondOrder->id, 'main');
    }
}
