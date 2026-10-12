<?php

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\SerialFulfillmentStatus;
use App\Enums\Inventory\SerialStatus;
use App\Jobs\ReconcilePaidSerialOrder;
use App\Models\ProductSerial;
use App\Models\ProductStockReservation;
use App\Models\SerialEvent;
use App\Services\Inventory\AvailabilityCheck;
use App\Services\Inventory\SerialInventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Models\OrderItem;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Tests\TestCase;

class InventoryCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private function setupOrder(Product $product, int $lines = 1): array
    {
        $order = OrderFactory::new()->create(['platform' => 'main', 'serial_inventory_enabled' => true, 'payment_status' => 'pending', 'status' => 'pending']);
        for ($n = 0; $n < $lines; $n++) {
            OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'product_price' => 1000000, 'quantity' => 1, 'subtotal' => 1000000, 'inventory_mode' => $product->inventory_mode->value, 'serial_tracking_required' => $product->track_serials, 'made_to_order' => $product->made_to_order, 'production_lead_time_text' => $product->production_lead_time_text]);
        }
        $payment = Payment::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'platform' => 'main', 'transaction_id' => uniqid('probe'), 'amount' => 1000000, 'status' => 'pending', 'gateway' => 'parsian']);

        return [$order, $payment];
    }

    private function serials(Product $product, int $count): void
    {
        for ($n = 1; $n <= $count; $n++) {
            ProductSerial::create(['product_id' => $product->id, 'serial_code' => 'ton'.(90000000 + $n), 'serial_prefix' => 'ton', 'status' => 'available']);
        }
    }

    public function test_duplicate_product_lines_must_own_distinct_reserved_serials(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $this->serials($product, 2);
        [$order,$payment] = $this->setupOrder($product, 2);
        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main', $payment->id);
        $this->assertSame(2, ProductSerial::where('status', 'reserved')->count());
    }

    public function test_processing_payment_must_keep_expired_hold(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $this->serials($product, 1);
        [$order,$payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $payment->update(['status' => 'processing']);
        $this->travel(21)->minutes();
        $inventory->releaseExpiredReservation($order->id, 'main');
        $this->assertSame(SerialStatus::Reserved, ProductSerial::first()->status);
    }

    public function test_late_manual_payment_must_consume_capacity_after_expiry(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        [$order,$payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $this->travel(21)->minutes();
        $inventory->releaseExpiredReservation($order->id, 'main');
        $order->update(['payment_status' => 'paid']);
        $payment->update(['status' => 'paid']);
        $inventory->finalizePaidOrder($order->id, 'main');
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_recovery_must_consume_paid_manual_capacity(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        [$order,$payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $order->update(['payment_status' => 'paid']);
        $payment->update(['status' => 'paid']);
        (new ReconcilePaidSerialOrder($order->id, 'main'))->handle($inventory);
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_shipped_order_must_be_complete(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $this->serials($product, 1);
        [$order,$payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $order->update(['payment_status' => 'paid']);
        $inventory->finalizePaidOrder($order->id, 'main');
        $inventory->markShipped($order->id, 'main', $order->user_id);
        $this->assertTrue($inventory->isSeriallyComplete($order->fresh()));
    }

    public function test_recovery_must_not_reallocate_qc_return_to_old_order(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $this->serials($product, 1);
        [$order,$payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $order->update(['payment_status' => 'paid']);
        $inventory->finalizePaidOrder($order->id, 'main');
        $inventory->markShipped($order->id, 'main', $order->user_id);
        $inventory->receiveReturn(ProductSerial::first()->id, $order->user_id, 'return');
        $inventory->restockReturnedSerial(ProductSerial::first()->id, $order->user_id, 'qc');
        (new ReconcilePaidSerialOrder($order->id, 'main'))->handle($inventory);
        $this->assertSame(SerialStatus::Available, ProductSerial::first()->status);
    }

    public function test_tracked_made_to_order_must_wait_for_production(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3, 'made_to_order' => true, 'track_serials' => true, 'serial_prefix' => 'ton']);
        [$order,$payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $order->update(['payment_status' => 'paid']);
        $inventory->finalizePaidOrder($order->id, 'main');
        $this->assertSame(SerialFulfillmentStatus::AwaitingProduction, $order->fresh()->serial_fulfillment_status);
    }

    public function test_successful_shortage_recovery_must_clear_needs_review(): void
    {
        $product = Product::factory()->serial('ton')->create();
        [$order,$payment] = $this->setupOrder($product);
        $order->update(['payment_status' => 'paid']);
        $inventory = app(SerialInventoryService::class);
        $inventory->finalizePaidOrder($order->id, 'main');
        $this->serials($product, 1);
        $inventory->finalizePaidOrder($order->id, 'main');
        $this->assertSame(SerialFulfillmentStatus::Allocated, $order->fresh()->serial_fulfillment_status);
    }

    public function test_fresh_reservation_must_not_be_cancelled_by_order_age(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $this->serials($product, 1);
        [$order,$payment] = $this->setupOrder($product);
        $order->forceFill(['created_at' => now()->subMinutes(21)])->save();
        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main', $payment->id);
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_unpaid_manual_order_must_not_ship(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        [$order,$payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $this->expectException(LogicException::class);
        $inventory->markShipped($order->id, 'main', $order->user_id);
    }

    public function test_duplicate_product_lines_finalize_with_distinct_serials_without_reservation(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $this->serials($product, 2);
        [$order] = $this->setupOrder($product, 2);
        $order->update(['payment_status' => 'paid']);

        app(SerialInventoryService::class)->finalizePaidOrder($order->id, 'main');

        $this->assertSame(2, ProductSerial::where('status', 'allocated')->count());
        $this->assertSame(2, ProductSerial::where('status', 'allocated')->distinct()->count('current_order_item_id'));
    }

    public function test_cancelled_paid_order_is_not_recovered(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $this->serials($product, 1);
        [$order] = $this->setupOrder($product);
        $order->update(['payment_status' => 'paid', 'status' => 'cancelled']);

        (new ReconcilePaidSerialOrder($order->id, 'main'))->handle(app(SerialInventoryService::class));

        $this->assertSame(SerialStatus::Available, ProductSerial::first()->status);
    }

    public function test_production_completion_allocates_bars_without_consuming_capacity_again(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3, 'made_to_order' => true, 'track_serials' => true]);
        [$order, $payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $order->update(['payment_status' => 'paid']);
        $inventory->finalizePaidOrder($order->id, 'main');
        $this->serials($product, 1);

        $inventory->completeProduction($order->id, 'main', $order->user_id, 'produced');
        $inventory->completeProduction($order->id, 'main', $order->user_id, 'produced');
        $inventory->markShipped($order->id, 'main', $order->user_id);
        $inventory->markShipped($order->id, 'main', $order->user_id);

        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->assertSame(SerialStatus::Shipped, ProductSerial::first()->status);
    }

    public function test_untracked_production_requires_explicit_completion_before_shipping(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3, 'made_to_order' => true, 'track_serials' => false]);
        [$order, $payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $order->update(['payment_status' => 'paid']);
        $inventory->finalizePaidOrder($order->id, 'main');

        $this->expectException(LogicException::class);
        $inventory->markShipped($order->id, 'main', $order->user_id);
    }

    public function test_late_manual_payment_shortage_remains_review_without_consumption(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 1]);
        [$order, $payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $this->travel(21)->minutes();
        $inventory->releaseExpiredReservation($order->id, 'main');
        $product->update(['stock_quantity' => 0]);
        $order->update(['payment_status' => 'paid']);

        $inventory->finalizePaidOrder($order->id, 'main');

        $this->assertSame(SerialFulfillmentStatus::NeedsReview, $order->fresh()->serial_fulfillment_status);
        $this->assertFalse($inventory->isSeriallyComplete($order->fresh()));
    }

    public function test_retry_of_same_manual_payment_after_expiry_reacquires_capacity(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        [$order, $payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $this->travel(21)->minutes();
        $inventory->releaseExpiredReservation($order->id, 'main');

        $inventory->reserveForPayment($order->id, 'main', $payment->id);

        $this->assertDatabaseHas('product_stock_reservations', ['payment_id' => $payment->id, 'status' => 'reserved']);
    }

    public function test_serial_group_with_shortage_allocates_nothing(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $this->serials($product, 1);
        [$order] = $this->setupOrder($product, 2);
        $order->update(['payment_status' => 'paid']);

        app(SerialInventoryService::class)->finalizePaidOrder($order->id, 'main');

        $this->assertSame(SerialStatus::Available, ProductSerial::first()->status);
        $this->assertSame(SerialFulfillmentStatus::NeedsReview, $order->fresh()->serial_fulfillment_status);
    }

    public function test_snapshot_retains_production_requirement_after_product_edit(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3, 'made_to_order' => true]);
        [$order, $payment] = $this->setupOrder($product);
        $product->update(['made_to_order' => false]);
        $order->update(['payment_status' => 'paid']);

        app(SerialInventoryService::class)->finalizePaidOrder($order->id, 'main');

        $this->assertSame(SerialFulfillmentStatus::AwaitingProduction, $order->fresh()->serial_fulfillment_status);
    }

    public function test_paid_order_does_not_release_holdings(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $this->serials($product, 1);
        [$order, $payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $order->update(['payment_status' => 'paid']);

        $inventory->releaseUnpaidHoldings($order->id, 'main', $payment->id);

        $this->assertSame(SerialStatus::Reserved, ProductSerial::first()->status);
    }

    public function test_completed_production_cannot_reopen_a_shipped_order(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3, 'made_to_order' => true, 'track_serials' => true]);
        [$order, $payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $order->update(['payment_status' => 'paid']);
        $inventory->finalizePaidOrder($order->id, 'main');
        $this->serials($product, 1);
        $inventory->completeProduction($order->id, 'main', $order->user_id, 'produced');
        $inventory->markShipped($order->id, 'main', $order->user_id);

        $inventory->completeProduction($order->id, 'main', $order->user_id, 'duplicate');

        $this->assertSame(SerialFulfillmentStatus::Shipped, $order->fresh()->serial_fulfillment_status);
        $this->assertSame(SerialStatus::Shipped, ProductSerial::first()->status);
    }

    public function test_recorded_paid_payment_never_loses_expired_hold_if_order_is_stale(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $this->serials($product, 1);
        [$order, $payment] = $this->setupOrder($product);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $payment->update(['status' => 'paid']);
        $this->travel(21)->minutes();

        $inventory->releaseExpiredReservation($order->id, 'main');

        $this->assertSame(SerialStatus::Reserved, ProductSerial::first()->status);
        $this->assertSame(SerialFulfillmentStatus::NeedsReview, $order->fresh()->serial_fulfillment_status);
    }

    public function test_duplicate_manual_lines_consume_shared_product_capacity_once_each(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        [$order, $payment] = $this->setupOrder($product, 2);
        $inventory = app(SerialInventoryService::class);
        $inventory->reserveForPayment($order->id, 'main', $payment->id);
        $order->update(['payment_status' => 'paid']);

        $inventory->finalizePaidOrder($order->id, 'main');
        $inventory->finalizePaidOrder($order->id, 'main');

        $this->assertSame(1, $product->fresh()->stock_quantity);
        $this->assertSame(2, ProductStockReservation::where('status', 'consumed')->count());
    }

    public function test_mysql_lock_timeout_is_retried_without_duplicate_reservation_events(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $this->serials($product, 1);
        [$order, $payment] = $this->setupOrder($product);
        $timeout = new \PDOException('Lock wait timeout');
        $timeout->errorInfo = ['HY000', 1205, 'Lock wait timeout'];
        $attempts = 0;
        $connection = DB::connection();
        DB::partialMock()->shouldReceive('transaction')->andReturnUsing(function ($callback) use (&$attempts, $timeout, $connection) {
            if (++$attempts < 3) {
                throw new QueryException('mysql', 'select for update', [], $timeout);
            }

            return $connection->transaction($callback);
        });

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main', $payment->id);

        $this->assertSame(SerialStatus::Reserved, ProductSerial::first()->status);
        $this->assertSame(1, SerialEvent::where('event_type', 'reserved')->count());
    }

    public function test_existing_order_availability_uses_mode_snapshot_after_product_edit(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        [$order] = $this->setupOrder($product);
        $product->update(['inventory_mode' => 'serial', 'track_serials' => true]);

        $result = AvailabilityCheck::check([['product' => $product, 'product_id' => $product->id, 'quantity' => 1]], 'main', $order);

        $this->assertTrue($result['valid']);
    }

    public function test_shipment_history_prevents_reallocation_after_qc_resale_and_stale_status(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $this->serials($product, 2);
        [$old] = $this->setupOrder($product);
        $old->update(['payment_status' => 'paid']);
        $service = app(SerialInventoryService::class);
        $service->finalizePaidOrder($old->id, 'main');
        $original = ProductSerial::where('status', 'allocated')->firstOrFail();
        $service->markShipped($old->id, 'main', $old->user_id);
        $service->receiveReturn($original->id, $old->user_id, 'return');
        $service->restockReturnedSerial($original->id, $old->user_id, 'qc');
        [$buyer] = $this->setupOrder($product);
        $buyer->update(['payment_status' => 'paid']);
        $service->finalizePaidOrder($buyer->id, 'main');
        $old->update(['serial_fulfillment_status' => 'needs_review']);

        (new ReconcilePaidSerialOrder($old->id, 'main'))->handle($service);
        $service->finalizePaidOrder($old->id, 'main');

        $this->assertSame($buyer->items()->first()->id, $original->fresh()->current_order_item_id);
        $this->assertSame(1, ProductSerial::where('status', 'available')->count());
        $this->assertTrue($service->isSeriallyComplete($old->fresh()));
    }
}
