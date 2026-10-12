<?php

namespace Tests\Feature\Inventory;

use App\Models\ProductSerial;
use App\Services\Inventory\SerialInventoryService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

class SerialCandidateBoundsTest extends TestCase
{
    use RefreshDatabase;

    private function warehouseOrder(): Order
    {
        $product = Product::factory()->serial('ton')->create();
        $order = OrderFactory::new()->create(['platform' => 'main', 'payment_status' => 'paid', 'status' => 'confirmed', 'serial_inventory_enabled' => true]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'product_price' => 1000000, 'quantity' => 1, 'subtotal' => 1000000, 'inventory_mode' => 'serial', 'serial_tracking_required' => true, 'made_to_order' => false]);

        for ($offset = 0; $offset < 50000; $offset += 1000) {
            $rows = [];

            for ($number = $offset; $number < $offset + 1000; $number++) {
                $rows[] = ['product_id' => $product->id, 'serial_code' => 'ton'.(70000000 + $number), 'serial_prefix' => 'ton', 'status' => 'available', 'created_at' => now(), 'updated_at' => now()];
            }

            DB::table('product_serials')->insert($rows);
        }

        return $order;
    }

    public function test_one_unit_reservation_hydrates_one_candidate_in_fifty_thousand_bar_warehouse(): void
    {
        $order = $this->warehouseOrder();
        $hydrated = 0;
        ProductSerial::retrieved(function () use (&$hydrated): void {
            $hydrated++;
        });

        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main');

        $this->assertSame(1, $hydrated);
        $this->assertDatabaseCount('product_serials', 50000);
        $this->assertSame(1, ProductSerial::where('status', 'reserved')->count());
    }

    public function test_one_unit_direct_finalization_hydrates_one_candidate_in_large_warehouse(): void
    {
        $order = $this->warehouseOrder();
        $hydrated = 0;
        ProductSerial::retrieved(function () use (&$hydrated): void {
            $hydrated++;
        });

        app(SerialInventoryService::class)->finalizePaidOrder($order->id, 'main');

        $this->assertSame(1, $hydrated);
        $this->assertSame(1, ProductSerial::where('status', 'allocated')->count());
    }

    public function test_repeated_finalization_queries_no_free_warehouse_stock_when_deficit_is_zero(): void
    {
        $order = $this->warehouseOrder();
        $service = app(SerialInventoryService::class);
        $service->reserveForPayment($order->id, 'main');
        $service->finalizePaidOrder($order->id, 'main');
        $freeQueries = [];
        $hydrated = 0;
        ProductSerial::retrieved(function () use (&$hydrated): void {
            $hydrated++;
        });
        DB::listen(function (QueryExecuted $query) use (&$freeQueries): void {
            if (str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'product_serials') && in_array('available', $query->bindings, true)) {
                $freeQueries[] = $query->sql;
            }
        });

        $service->finalizePaidOrder($order->id, 'main');

        $this->assertSame([], $freeQueries);
        $this->assertSame(0, $hydrated);
        $this->assertSame(1, ProductSerial::where('status', 'allocated')->count());
    }

    public function test_satisfied_product_leaves_candidate_batches_before_later_product_is_selected(): void
    {
        $order = $this->warehouseOrder();
        $later = Product::factory()->serial('tfv')->create();
        ProductSerial::create(['product_id' => $later->id, 'serial_code' => 'tfv70000001', 'serial_prefix' => 'tfv', 'status' => 'available']);
        $order->items()->create(['product_id' => $later->id, 'product_name' => $later->name, 'product_price' => 1000000, 'quantity' => 1, 'subtotal' => 1000000, 'inventory_mode' => 'serial', 'serial_tracking_required' => true, 'made_to_order' => false]);
        $hydrated = 0;
        ProductSerial::retrieved(function () use (&$hydrated): void {
            $hydrated++;
        });

        app(SerialInventoryService::class)->finalizePaidOrder($order->id, 'main');

        $this->assertLessThanOrEqual(4, $hydrated);
        $this->assertSame(1, ProductSerial::where('product_id', $later->id)->where('status', 'allocated')->count());
        $this->assertSame(2, ProductSerial::where('status', 'allocated')->count());
    }
}
