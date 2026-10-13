<?php

namespace Tests\Feature\Inventory;

use App\Models\Cart;
use App\Models\SerialImportBatch;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Inventory\ProductInventorySettingsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryAdminMySqlConcurrencyTest extends TestCase
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
        $this->seed(RolePermissionSeeder::class);
        config(['database.connections.inventory_peer' => DB::connection()->getConfig(), 'serial_inventory.enabled' => true, 'hesabfa.auto_sync' => false]);
        DB::purge('inventory_peer');
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        DB::disconnect('inventory_peer');
        parent::tearDown();
    }

    private function peer(): Connection
    {
        return DB::connection('inventory_peer');
    }

    private function actor(): User
    {
        return User::factory()->create()->givePermissionTo('product.edit', 'serial.manage', 'product.delete');
    }

    private function item(Product $product): OrderItem
    {
        $order = Order::factory()->create(['serial_inventory_enabled' => true]);

        return OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'product_price' => 100, 'subtotal' => 100]);
    }

    public function test_mode_change_sees_order_committed_after_outer_repeatable_read_snapshot(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 4]);
        $actor = $this->actor();
        DB::beginTransaction();
        DB::table('order_items')->where('product_id', $product->id)->count();
        $order = Order::factory()->make(['user_id' => $actor->id, 'order_number' => 'peer-open-order', 'serial_inventory_enabled' => true]);
        $orderId = $this->peer()->table('orders')->insertGetId($order->getAttributes() + ['created_at' => now(), 'updated_at' => now()]);
        $this->peer()->table('order_items')->insert(['order_id' => $orderId, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'product_price' => 100, 'subtotal' => 100]);

        $this->expectException(ValidationException::class);
        app(ProductInventorySettingsService::class)->update($product, ['inventory_mode' => 'serial', 'track_serials' => true, 'serial_prefix' => 'ton'], $actor, 'switch');
    }

    public function test_mode_guard_sees_order_reopened_between_validation_and_eligibility(): void
    {
        $product = Product::factory()->create();
        $actor = $this->actor();
        $item = $this->item($product);
        $item->order->update(['status' => 'completed']);
        $changed = false;
        DB::listen(function ($query) use ($item, &$changed): void {
            if (! $changed && $query->connectionName !== 'inventory_peer' && str_contains($query->sql, 'count(*)') && str_contains($query->sql, '`serial_prefix`')) {
                $changed = true;
                $this->peer()->table('orders')->where('id', $item->order_id)->update(['status' => 'pending']);
            }
        });
        $this->expectException(ValidationException::class);
        app(ProductInventorySettingsService::class)->update($product, ['inventory_mode' => 'serial', 'track_serials' => true, 'serial_prefix' => 'ton'], $actor, 'switch');
    }

    public function test_prefix_guard_sees_serial_history_committed_after_outer_snapshot(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $actor = $this->actor();
        DB::beginTransaction();
        DB::table('product_serials')->where('product_id', $product->id)->count();
        $this->peer()->table('product_serials')->insert(['product_id' => $product->id, 'serial_code' => 'ton701', 'serial_prefix' => 'ton', 'status' => 'available']);

        $this->expectException(ValidationException::class);
        app(ProductInventorySettingsService::class)->update($product, ['serial_prefix' => 'foo'], $actor, 'transfer');
    }

    public function test_same_transaction_open_order_also_blocks_mode_change(): void
    {
        $product = Product::factory()->create();
        $actor = $this->actor();
        DB::beginTransaction();
        $this->item($product);

        $this->expectException(ValidationException::class);
        app(ProductInventorySettingsService::class)->update($product, ['inventory_mode' => 'serial', 'track_serials' => true, 'serial_prefix' => 'ton'], $actor, 'switch');
    }

    public static function obligations(): array
    {
        return ['serial' => ['serial'], 'manual' => ['manual'], 'production' => ['production']];
    }

    #[DataProvider('obligations')]
    public function test_delete_sees_obligation_committed_after_old_snapshot(string $kind): void
    {
        $product = Product::factory()->create(['stock_quantity' => 4]);
        $item = $this->item($product);
        $order = $item->order;
        $payment = Payment::create(['order_id' => $order->id, 'platform' => 'main', 'transaction_id' => 'peer-payment', 'amount' => 100, 'status' => 'pending']);
        DB::beginTransaction();
        $this->assertFalse($order->hasActiveSerialHoldings());
        match ($kind) {
            'serial' => $this->peer()->table('product_serials')->insert(['product_id' => $product->id, 'serial_code' => 'ton701', 'serial_prefix' => 'ton', 'status' => 'reserved', 'current_order_item_id' => $item->id, 'unit_number' => 1]),
            'manual' => $this->peer()->table('product_stock_reservations')->insert(['product_id' => $product->id, 'order_item_id' => $item->id, 'payment_id' => $payment->id, 'quantity' => 1, 'status' => 'reserved']),
            'production' => $this->peer()->table('order_items')->where('id', $item->id)->update(['made_to_order' => true, 'serial_fulfillment_status' => 'awaiting_production']),
        };
        try {
            $order->delete();
            $this->fail('Deletion must see the new obligation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order', $exception->errors());
        }
        DB::rollBack();
        $this->assertNotSoftDeleted($order);
    }

    public static function histories(): array
    {
        return ['audit' => ['audit'], 'import' => ['import']];
    }

    #[DataProvider('histories')]
    public function test_product_delete_denies_new_history_after_policy_authorization(string $kind): void
    {
        $product = Product::factory()->create();
        $actor = $this->actor();
        $batch = SerialImportBatch::factory()->create();
        DB::beginTransaction();
        $this->assertTrue($actor->can('delete', $product));
        if ($kind === 'audit') {
            $this->peer()->table('product_inventory_audits')->insert(['product_id' => $product->id, 'actor_id' => $actor->id, 'reason' => 'new audit', 'before' => '{}', 'after' => '{}']);
        } else {
            $this->peer()->table('serial_import_rows')->insert(['import_batch_id' => $batch->id, 'row_number' => 1, 'product_id' => $product->id, 'raw_value' => 'ton701', 'normalized_serial' => 'ton701', 'validation_status' => 'valid', 'result_status' => 'pending']);
        }
        try {
            $product->delete();
            $this->fail('Deletion must return a readable history denial.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product', $exception->errors());
        }
        DB::rollBack();
        $this->assertModelExists($product);
    }

    public function test_submit_waits_for_settings_mutex_and_snapshots_fresh_product(): void
    {
        $user = User::factory()->create();
        $actor = $this->actor();
        $product = Product::factory()->create(['stock_quantity' => 4]);
        $method = ShippingMethod::factory()->create(['id' => 1, 'is_active' => true]);
        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1, 'platform' => 'main']);
        $barrier = tempnam(sys_get_temp_dir(), 'inventory-submit-');
        unlink($barrier);
        $worker = <<<'WORKER'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['serial_inventory.enabled' => true, 'hesabfa.auto_sync' => false, 'pulse.enabled' => false]);
Illuminate\Support\Facades\DB::listen(function ($query) use ($argv) {
    if (str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, '`products`') && ! str_contains(strtolower($query->sql), 'for update')) { touch($argv[1]); }
});
$request = Illuminate\Http\Request::create('/api/orders', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$argv[2], 'HTTP_X_PLATFORM' => 'main'], json_encode(['shipping_method_id' => (int) $argv[3]]));
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)]);
WORKER;
        DB::beginTransaction();
        Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
        $process = new Process([PHP_BINARY, '-d', 'memory_limit=512M', '-r', $worker, $barrier, $user->api_token, (string) $method->id], base_path(), ['APP_ENV' => 'testing']);
        $process->setTimeout(25);
        try {
            $process->start();
            $deadline = microtime(true) + 10;
            while (! file_exists($barrier)) {
                if (! $process->isRunning() || microtime(true) > $deadline) {
                    $this->fail('Submit worker did not reach product read: '.$process->getErrorOutput().$process->getOutput());
                }
                usleep(1000);
            }
            app(ProductInventorySettingsService::class)->update($product, ['inventory_mode' => 'serial', 'track_serials' => true, 'serial_prefix' => 'ton'], $actor, 'switch before submit');
            DB::table('product_serials')->insert(['product_id' => $product->id, 'serial_code' => 'ton701', 'serial_prefix' => 'ton', 'status' => 'available']);
            DB::commit();
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $response = json_decode($process->getOutput(), true);
            $this->assertSame(201, $response['status'], $process->getOutput());
            $item = OrderItem::where('product_id', $product->id)->firstOrFail();
            $this->assertSame('serial', $item->inventory_mode->value);
            $this->assertTrue($item->serial_tracking_required);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($process->isRunning()) {
                $process->stop();
            }
            if (file_exists($barrier)) {
                unlink($barrier);
            }
        }
    }
}
