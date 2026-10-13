<?php

namespace Tests\Feature\Inventory;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use Tests\TestCase;

class SerialMySqlCheckConstraintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('SERIAL_INVENTORY_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Requires explicit disposable MySQL *_test opt-in.');
        }
        if (! app()->environment('testing') || DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_test')) {
            $this->fail('Unsafe test database.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    }

    public function test_invalid_serial_states_and_ownership_writes_are_rejected_by_mysql(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $order = Order::factory()->create();
        $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'product_price' => 100, 'subtotal' => 100]);
        $invalid = [
            ['status' => 'imaginary'],
            ['status' => 'available', 'current_order_item_id' => $item->id, 'unit_number' => 1],
            ['status' => 'reserved'],
            ['status' => 'allocated', 'current_order_item_id' => $item->id, 'unit_number' => null],
            ['status' => 'shipped', 'current_order_item_id' => $item->id, 'unit_number' => 0],
            ['status' => 'returned', 'current_order_item_id' => null, 'unit_number' => 1],
            ['status' => 'damaged', 'current_order_item_id' => $item->id, 'unit_number' => null],
            ['status' => 'lost', 'current_order_item_id' => $item->id, 'unit_number' => 0],
        ];
        foreach ($invalid as $index => $state) {
            try {
                DB::table('product_serials')->insert($state + ['product_id' => $product->id, 'serial_code' => 'ton70'.$index, 'serial_prefix' => 'ton']);
                $this->fail('MySQL accepted invalid inventory state '.$index);
            } catch (QueryException $exception) {
                $this->assertSame(3819, $exception->errorInfo[1]);
            }
        }
        $this->assertSame(0, DB::table('product_serials')->count());
    }

    public function test_constraint_accepts_coherent_quarantined_states_and_rejects_bad_updates(): void
    {
        $product = Product::factory()->serial('ton')->create();
        foreach (['available', 'returned', 'damaged', 'lost'] as $index => $status) {
            DB::table('product_serials')->insert(['product_id' => $product->id, 'serial_code' => 'ton800'.$index, 'serial_prefix' => 'ton', 'status' => $status]);
        }
        $this->assertSame(4, DB::table('product_serials')->count());
        $this->expectException(QueryException::class);
        DB::table('product_serials')->where('status', 'available')->update(['status' => 'reserved']);
    }
}
