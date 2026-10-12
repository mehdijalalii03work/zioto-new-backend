<?php

namespace Tests\Feature\Inventory;

use App\Models\ProductSerial;
use App\Models\ProductStockReservation;
use Illuminate\Support\Facades\DB;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SerialMySqlConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('SERIAL_INVENTORY_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Opt in with SERIAL_INVENTORY_MYSQL_TESTS=1 and a disposable *_test database.');
        }

        $database = DB::connection()->getDatabaseName();

        if (DB::getDriverName() !== 'mysql' || ! is_string($database) || ! str_ends_with($database, '_test') || ! app()->environment('testing')) {
            $this->fail('Refusing to migrate a database that is not an explicitly selected MySQL test database.');
        }

        config(['pulse.storage.database.connection' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'hesabfa.auto_sync' => false]);
        DB::purge('sqlite');

        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
    }

    public function test_parallel_reserve_grants_one_serial_winner(): void
    {
        $product = Product::factory()->serial('ton')->create();
        ProductSerial::create(['product_id' => $product->id, 'serial_code' => 'ton70000001', 'serial_prefix' => 'ton', 'status' => 'available']);
        $orders = $this->orders($product);

        $results = $this->race($orders, 'reserve');

        sort($results);
        $this->assertSame(['ok', 'shortage'], $results);
        $this->assertSame(1, ProductSerial::where('status', 'reserved')->count());
    }

    public function test_parallel_reserve_uses_current_free_rows_when_capacity_suffices_for_both(): void
    {
        $product = Product::factory()->serial('ton')->create();
        foreach (['ton70000001', 'ton70000002'] as $code) {
            ProductSerial::create(['product_id' => $product->id, 'serial_code' => $code, 'serial_prefix' => 'ton', 'status' => 'available']);
        }
        $orders = $this->orders($product);

        $results = $this->race($orders, 'reserve');

        $this->assertSame(['ok', 'ok'], $results);
        $this->assertSame(2, ProductSerial::where('status', 'reserved')->count());
    }

    public function test_parallel_manual_reserve_grants_one_capacity_winner(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 1]);
        $orders = $this->orders($product);

        $results = $this->race($orders, 'reserve');

        sort($results);
        $this->assertSame(['ok', 'shortage'], $results);
        $this->assertSame(1, ProductStockReservation::where('status', 'reserved')->count());
    }

    public function test_parallel_finalize_consumes_manual_capacity_once(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3]);
        $orders = $this->orders($product);
        $order = $orders[0];
        Order::withoutTenantScope()->whereKey($order['order'])->update(['payment_status' => 'paid']);

        $results = $this->race([$order, $order], 'finalize');

        $this->assertSame(['ok', 'ok'], $results);
        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->assertSame(1, ProductStockReservation::where('status', 'consumed')->count());
    }

    public function test_parallel_different_paid_orders_do_not_oversell_manual_capacity(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 1]);
        $orders = $this->orders($product);
        Order::withoutTenantScope()->whereIn('id', array_column($orders, 'order'))->update(['payment_status' => 'paid']);

        $this->race($orders, 'finalize');

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(1, ProductStockReservation::where('status', 'consumed')->count());
        $this->assertSame(1, Order::withoutTenantScope()->where('serial_fulfillment_status', 'needs_review')->count());
    }

    /** @return list<array{order: int, payment: int}> */
    private function orders(Product $product): array
    {
        $orders = [];

        for ($n = 0; $n < 2; $n++) {
            $order = OrderFactory::new()->create(['platform' => 'main', 'serial_inventory_enabled' => true, 'payment_status' => 'pending', 'status' => 'pending']);
            OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'product_price' => 1000000, 'quantity' => 1, 'subtotal' => 1000000, 'inventory_mode' => $product->inventory_mode->value, 'serial_tracking_required' => $product->track_serials, 'made_to_order' => false]);
            $payment = Payment::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'platform' => 'main', 'transaction_id' => 'race-'.$n, 'gateway' => 'parsian', 'amount' => 1000000, 'status' => 'paid']);
            $orders[] = ['order' => $order->id, 'payment' => $payment->id];
        }

        return $orders;
    }

    /** @param list<array{order: int, payment: int}> $orders
     * @return list<string>
     */
    private function race(array $orders, string $operation): array
    {
        $barrier = tempnam(sys_get_temp_dir(), 'serial-race-');
        unlink($barrier);
        $worker = <<<'WORKER'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || Illuminate\Support\Facades\DB::getDriverName() !== 'mysql' || ! str_ends_with(Illuminate\Support\Facades\DB::connection()->getDatabaseName(), '_test')) { exit(2); }
config(['hesabfa.auto_sync' => false, 'mail.default' => 'array', 'broadcasting.default' => 'null', 'pulse.enabled' => false, 'telescope.enabled' => false, 'nightwatch.enabled' => false]);
touch($argv[1].'.ready-'.$argv[5]);
Illuminate\Support\Facades\DB::listen(function ($query) use ($argv) {
    if (str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, '`products`') && ! str_contains(strtolower($query->sql), 'for update')) { touch($argv[1].'.snapshot-'.$argv[5]); }
});
$deadline = microtime(true) + 10;
while (! file_exists($argv[1])) { if (microtime(true) > $deadline) { exit(3); } usleep(1000); }
try {
    $inventory = $app->make(App\Services\Inventory\SerialInventoryService::class);
    if ($argv[4] === 'reserve') { $inventory->reserveForPayment((int) $argv[2], 'main', (int) $argv[3]); }
    else { $inventory->finalizePaidOrder((int) $argv[2], 'main'); }
    echo 'ok';
} catch (App\Services\Inventory\SerialStockShortageException) { echo 'shortage'; }
WORKER;
        $processes = [];
        $productId = OrderItem::where('order_id', $orders[0]['order'])->value('product_id');
        $forceProductContention = count(array_unique(array_column($orders, 'order'))) > 1;

        if ($forceProductContention) {
            DB::beginTransaction();
            Product::whereKey($productId)->lockForUpdate()->firstOrFail();
        }

        try {
            foreach ($orders as $index => $order) {
                $process = new Process([PHP_BINARY, '-d', 'memory_limit=512M', '-r', $worker, $barrier, (string) $order['order'], (string) $order['payment'], $operation, (string) $index], base_path(), ['APP_ENV' => 'testing']);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 15;

            foreach (array_keys($orders) as $index) {
                while (! file_exists($barrier.'.ready-'.$index)) {
                    if (microtime(true) >= $deadline) {
                        $this->fail('Worker did not reach the shared start barrier.');
                    }
                    usleep(1000);
                }
            }

            touch($barrier);

            foreach ($forceProductContention ? array_keys($orders) : [] as $index) {
                while (! file_exists($barrier.'.snapshot-'.$index)) {
                    if (microtime(true) >= $deadline) {
                        $this->fail('Worker did not read its product before waiting for the shared product lock.');
                    }
                    usleep(1000);
                }
            }

            if ($forceProductContention) {
                DB::rollBack();
            }

            $results = [];

            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $results[] = $process->getOutput();
            }

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }

            foreach (array_keys($orders) as $index) {
                if (file_exists($barrier.'.snapshot-'.$index)) {
                    unlink($barrier.'.snapshot-'.$index);
                }
                if (file_exists($barrier.'.ready-'.$index)) {
                    unlink($barrier.'.ready-'.$index);
                }
            }

            if (file_exists($barrier)) {
                unlink($barrier);
            }
        }
    }
}
