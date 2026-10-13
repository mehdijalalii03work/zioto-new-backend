<?php

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\InventoryMode;
use App\Models\ProductSerial;
use App\Models\SerialImportBatch;
use App\Models\SerialImportRow;
use App\Models\User;
use App\Services\Inventory\SerialInventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * Stage 1 — schema invariants for serial inventory (plan §4).
 *
 * A physical bar keeps its history: products / import rows / orders that
 * own serial history cannot be deleted, serial codes are globally unique,
 * and an available serial never carries an owner.
 */
class SerialLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function makeSerialProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'شمش تست سریالی',
            'slug' => 'serial-test-'.uniqid(),
            'price' => 1000000,
            'stock_quantity' => 0,
            'serial_prefix' => 'tst',
            'inventory_mode' => InventoryMode::Serial->value,
            'track_serials' => true,
        ], $overrides));
    }

    private function makeBatch(): SerialImportBatch
    {
        return SerialImportBatch::create([
            'original_filename' => 'test.xlsx',
            'file_hash' => hash('sha256', uniqid('batch', true)),
            'status' => 'completed',
        ]);
    }

    public function test_product_with_serials_cannot_be_deleted(): void
    {
        $product = $this->makeSerialProduct();

        $serial = ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst00000001',
            'serial_prefix' => 'tst',
            'status' => 'available',
        ]);

        try {
            $product->delete();
            $this->fail('A product with serial history must remain protected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product', $exception->errors());
            $this->assertNotEmpty($exception->errors()['product'][0]);
        }

        $this->assertModelExists($product);
        $this->assertModelExists($serial);
        $this->assertSame($product->id, $serial->fresh()->product_id);
        $this->assertSame('tst00000001', $serial->fresh()->serial_code);
    }

    public function test_serial_code_is_globally_unique(): void
    {
        $product = $this->makeSerialProduct();

        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst00000001',
            'serial_prefix' => 'tst',
            'status' => 'available',
        ]);

        $this->expectException(QueryException::class);

        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst00000001',
            'serial_prefix' => 'tst',
            'status' => 'available',
        ]);
    }

    public function test_import_row_with_serials_cannot_be_deleted(): void
    {
        $product = $this->makeSerialProduct();
        $batch = $this->makeBatch();
        $row = SerialImportRow::create([
            'import_batch_id' => $batch->id,
            'row_number' => 2,
            'raw_value' => 'tst00000001',
            'normalized_serial' => 'tst00000001',
            'validation_status' => 'valid',
            'result_status' => 'imported',
        ]);

        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst00000001',
            'serial_prefix' => 'tst',
            'status' => 'available',
            'import_row_id' => $row->id,
        ]);

        $this->expectException(QueryException::class);

        $row->delete();
    }

    public function test_available_serial_has_no_owner(): void
    {
        $product = $this->makeSerialProduct();

        $serial = ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst00000001',
            'serial_prefix' => 'tst',
            'status' => 'available',
        ]);

        $this->assertNull($serial->current_order_item_id);
        $this->assertNull($serial->unit_number);
    }

    public function test_owner_unit_is_unique_per_order_item(): void
    {
        $product = $this->makeSerialProduct();
        $order = OrderFactory::new()->create(['platform' => 'main']);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => 2,
            'subtotal' => 2000000,
        ]);

        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst00000001',
            'serial_prefix' => 'tst',
            'status' => 'allocated',
            'current_order_item_id' => $item->id,
            'unit_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst00000002',
            'serial_prefix' => 'tst',
            'status' => 'allocated',
            'current_order_item_id' => $item->id,
            'unit_number' => 1,
        ]);
    }

    public function test_serial_code_preserves_leading_zeros(): void
    {
        $product = $this->makeSerialProduct();

        $serial = ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst00700123',
            'serial_prefix' => 'tst',
            'status' => 'available',
        ]);

        $this->assertSame('tst00700123', $serial->fresh()->serial_code);
    }

    public function test_refund_without_receipt_keeps_shipped_serials(): void
    {
        $product = $this->makeSerialProduct();
        $order = OrderFactory::new()->create([
            'platform' => 'main',
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'serial_inventory_enabled' => true,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => 1,
            'subtotal' => 1000000,
        ]);

        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst70000001',
            'serial_prefix' => 'tst',
            'status' => 'available',
        ]);

        $service = app(SerialInventoryService::class);
        $service->reserveForPayment($order->id, 'main');
        $service->finalizePaidOrder($order->id, 'main');

        $actor = User::factory()->create();
        $service->markShipped($order->id, 'main', $actor->id);

        // A refund alone (no physical receipt) never frees shipped bars.
        $order->update(['payment_status' => 'refunded']);

        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'shipped')->count());
        $this->assertSame('shipped', $order->fresh()->serial_fulfillment_status->value);
    }

    public function test_cancelled_order_keeps_allocated_until_manual_release(): void
    {
        $product = $this->makeSerialProduct();
        $order = OrderFactory::new()->create([
            'platform' => 'main',
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'serial_inventory_enabled' => true,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => 1,
            'subtotal' => 1000000,
        ]);

        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst70000002',
            'serial_prefix' => 'tst',
            'status' => 'available',
        ]);

        $service = app(SerialInventoryService::class);
        $service->reserveForPayment($order->id, 'main');
        $service->finalizePaidOrder($order->id, 'main');

        $order->update(['status' => 'cancelled']);

        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'allocated')->count());

        $actor = User::factory()->create();
        $service->releaseBeforeShipment($order->id, 'main', $actor->id, 'لغو سفارش');

        $this->assertSame(1, ProductSerial::where('product_id', $product->id)->where('status', 'available')->count());
    }

    public function test_order_with_serial_history_cannot_be_deleted(): void
    {
        $product = $this->makeSerialProduct();
        $order = OrderFactory::new()->create(['platform' => 'main']);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => 1,
            'subtotal' => 1000000,
        ]);

        ProductSerial::create([
            'product_id' => $product->id,
            'serial_code' => 'tst00000001',
            'serial_prefix' => 'tst',
            'status' => 'shipped',
            'current_order_item_id' => $item->id,
            'unit_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        $item->delete();
    }
}
