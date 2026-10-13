<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\SiteWarehouse;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\ProductInventoryAudit;
use App\Models\ProductSerial;
use App\Models\ProductStockReservation;
use App\Models\User;
use App\Policies\SerialImportBatchPolicy;
use App\Services\Inventory\ProductInventorySettingsService;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InventoryAdminGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    private function user(string ...$permissions): User
    {
        return User::factory()->create()->givePermissionTo($permissions);
    }

    private function order(Product $product, array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge(['platform' => 'main', 'status' => 'pending', 'payment_status' => 'pending', 'serial_inventory_enabled' => true], $attributes));
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'product_price' => 100, 'quantity' => 2, 'subtotal' => 200]);

        return $order;
    }

    public function test_view_only_warehouse_cannot_forge_capacity_action(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5, 'metal_type' => 'gold', 'form' => 'pelak', 'ayar' => '999', 'status' => 'published', 'visibility' => 'public', 'price_type' => 'fixed']);
        $user = $this->user('serial.view');

        Livewire::actingAs($user, 'web')->test(SiteWarehouse::class)
            ->assertTableActionHidden('updateCapacity', $product)
            ->call('mountAction', 'updateCapacity', [], ['table' => true, 'recordKey' => (string) $product->id])
            ->assertSet('mountedActions', []);

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_capacity_below_reserved_is_rejected_and_success_is_audited(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5, 'metal_type' => 'gold', 'form' => 'pelak', 'ayar' => '999', 'status' => 'published', 'visibility' => 'public', 'price_type' => 'fixed']);
        $order = $this->order($product);
        ProductStockReservation::create(['product_id' => $product->id, 'order_item_id' => $order->items()->first()->id, 'payment_id' => Payment::create(['order_id' => $order->id, 'amount' => 200, 'transaction_id' => 'guard-'.uniqid(), 'status' => 'pending', 'platform' => 'main'])->id, 'quantity' => 2, 'status' => 'reserved', 'reserved_until' => now()->addHour()]);
        $user = $this->user('product.edit', 'serial.manage');
        $service = app(ProductInventorySettingsService::class);

        try {
            $service->update($product, ['stock_quantity' => 1], $user, 'capacity adjustment');
            $this->fail('Reserved capacity must be protected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('stock_quantity', $exception->errors());
        }
        $this->assertSame(5, $product->fresh()->stock_quantity);

        $service->update($product, ['stock_quantity' => 4], $user, 'capacity adjustment');
        $this->assertDatabaseHas('product_inventory_audits', ['product_id' => $product->id, 'actor_id' => $user->id, 'reason' => 'capacity adjustment']);
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_basic_editor_cannot_forge_inventory_settings(): void
    {
        $product = Product::factory()->create();
        $user = $this->user('product.edit');

        $this->expectException(AuthorizationException::class);
        app(ProductInventorySettingsService::class)->update($product, ['inventory_mode' => 'serial', 'track_serials' => true, 'serial_prefix' => 'ton'], $user, 'forged');
    }

    public function test_open_order_prevents_inventory_mode_change_before_reservation(): void
    {
        $product = Product::factory()->create();
        $this->order($product);
        $user = $this->user('product.edit', 'serial.manage');

        $this->expectException(ValidationException::class);
        app(ProductInventorySettingsService::class)->update($product, ['inventory_mode' => 'serial', 'track_serials' => true, 'serial_prefix' => 'ton'], $user, 'switch');
    }

    public function test_serial_inventory_requires_tracking_and_valid_prefix(): void
    {
        $product = Product::factory()->create();
        $user = $this->user('product.edit', 'serial.manage');

        $this->expectException(ValidationException::class);
        app(ProductInventorySettingsService::class)->update($product, ['inventory_mode' => 'serial', 'track_serials' => false], $user, 'switch');
    }

    public function test_direct_delete_refuses_manual_hold(): void
    {
        $product = Product::factory()->create();
        $order = $this->order($product);
        ProductStockReservation::create(['product_id' => $product->id, 'order_item_id' => $order->items()->first()->id, 'payment_id' => Payment::create(['order_id' => $order->id, 'amount' => 200, 'transaction_id' => 'guard-'.uniqid(), 'status' => 'pending', 'platform' => 'main'])->id, 'quantity' => 2, 'status' => 'reserved']);

        $this->expectException(ValidationException::class);
        $order->delete();
    }

    public function test_direct_delete_refuses_awaiting_production_even_without_active_hold(): void
    {
        $product = Product::factory()->create();
        $order = $this->order($product, ['payment_status' => 'paid', 'serial_fulfillment_status' => 'awaiting_production']);
        $order->items()->first()->update(['made_to_order' => true, 'serial_fulfillment_status' => 'awaiting_production']);

        $this->expectException(ValidationException::class);
        $order->delete();
    }

    public function test_editing_order_notes_preserves_financial_total(): void
    {
        $product = Product::factory()->create();
        $order = $this->order($product, ['total_amount' => 200, 'payment_status' => 'paid']);
        $user = $this->user('order.view', 'order.edit');

        Livewire::actingAs($user, 'web')->test(EditOrder::class, ['record' => $order->id])
            ->fillForm(['notes' => 'new note'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('200', $order->fresh()->total_amount);
    }

    public function test_fractional_capacity_is_not_silently_truncated(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5, 'metal_type' => 'gold', 'form' => 'pelak', 'ayar' => '999', 'status' => 'published', 'visibility' => 'public', 'price_type' => 'fixed']);
        $user = $this->user('product.edit', 'serial.manage');
        $this->expectException(ValidationException::class);
        app(ProductInventorySettingsService::class)->update($product, ['stock_quantity' => 3.5], $user, 'capacity');
    }

    public function test_missing_serial_permissions_deny_warehouse_and_import_history_navigation(): void
    {
        Permission::where('name', 'serial.view')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs(User::factory()->create(), 'web');
        $this->assertFalse(SiteWarehouse::canAccess());
        $this->assertFalse((new SerialImportBatchPolicy)->viewAny(auth()->user()));
    }

    public function test_warehouse_and_product_form_reject_capacity_below_live_reservations(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5, 'metal_type' => 'gold', 'form' => 'pelak', 'ayar' => '999', 'status' => 'published', 'visibility' => 'public', 'price_type' => 'fixed']);
        $order = $this->order($product);
        $payment = Payment::create(['order_id' => $order->id, 'amount' => 200, 'transaction_id' => 'form-hold', 'status' => 'pending', 'platform' => 'main']);
        ProductStockReservation::create(['product_id' => $product->id, 'order_item_id' => $order->items()->first()->id, 'payment_id' => $payment->id, 'quantity' => 2, 'status' => 'reserved']);
        $user = $this->user('product.view', 'product.edit', 'product.pricing', 'serial.manage', 'serial.view');

        Livewire::actingAs($user, 'web')->test(SiteWarehouse::class)
            ->callTableAction('updateCapacity', $product, ['stock_quantity' => 1, 'reason' => 'below reservation'])
            ->assertHasFormErrors(['stock_quantity']);
        Livewire::actingAs($user, 'web')->test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['stock_quantity' => 1, 'inventory_reason' => 'below reservation'])->call('save')
            ->assertHasFormErrors(['stock_quantity']);
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('product_inventory_audits', 0);
    }

    public function test_bulk_order_delete_keeps_active_holdings_and_deletes_only_uncommitted_orders(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $order = $this->order($product);
        ProductSerial::create(['product_id' => $product->id, 'serial_code' => 'ton701', 'serial_prefix' => 'ton', 'status' => 'reserved', 'current_order_item_id' => $order->items()->first()->id, 'unit_number' => 1]);
        $free = Order::factory()->create();
        $user = $this->user('order.view', 'order.delete');

        Livewire::actingAs($user, 'web')->test(ListOrders::class)
            ->callTableBulkAction('delete', [$order, $free]);

        $this->assertNotSoftDeleted($order);
        $this->assertSoftDeleted($free);
    }

    public function test_inventory_reason_only_save_does_not_create_audit(): void
    {
        $product = Product::factory()->create();
        $user = $this->user('product.edit');
        app(ProductInventorySettingsService::class)->update($product, ['name' => 'renamed', 'inventory_reason' => 'same settings'], $user, 'same settings');
        $this->assertSame('renamed', $product->fresh()->name);
        $this->assertDatabaseCount('product_inventory_audits', 0);
    }

    public function test_product_delete_policy_explains_manual_history_protection(): void
    {
        $product = Product::factory()->create();
        ProductInventoryAudit::factory()->create(['product_id' => $product->id]);
        $user = $this->user('product.delete');
        $response = Gate::forUser($user)->inspect('delete', $product);
        $this->assertTrue($response->denied());
        $this->assertNotEmpty($response->message());
    }

    public function test_forged_hidden_product_inventory_fields_do_not_change_configuration(): void
    {
        $product = Product::factory()->create(['inventory_mode' => 'manual', 'serial_prefix' => null, 'track_serials' => false, 'metal_type' => 'gold', 'form' => 'pelak', 'ayar' => '999', 'status' => 'published', 'visibility' => 'public', 'price_type' => 'fixed']);
        $user = $this->user('product.view', 'product.edit');
        Livewire::actingAs($user, 'web')->test(EditProduct::class, ['record' => $product->id])
            ->set('data.inventory_mode', 'serial')->set('data.serial_prefix', 'ton')->set('data.track_serials', true)
            ->set('data.stock_quantity', 100)->call('save')->assertHasNoFormErrors();
        $this->assertSame('manual', $product->fresh()->inventory_mode->value);
        $this->assertNull($product->fresh()->serial_prefix);
        $this->assertFalse($product->fresh()->track_serials);
        $this->assertNotSame(100, $product->fresh()->stock_quantity);
    }

    public function test_serial_history_allows_terminal_mode_change_but_protects_mapping(): void
    {
        $product = Product::factory()->serial('ton')->create();
        ProductSerial::create(['product_id' => $product->id, 'serial_code' => 'ton709', 'serial_prefix' => 'ton', 'status' => 'available']);
        $user = $this->user('product.edit', 'serial.manage');
        app(ProductInventorySettingsService::class)->update($product, ['inventory_mode' => 'manual', 'stock_quantity' => 3], $user, 'reconcile manual capacity');
        $this->assertSame('manual', $product->fresh()->inventory_mode->value);
        $this->assertDatabaseCount('product_inventory_audits', 1);
        $this->expectException(ValidationException::class);
        app(ProductInventorySettingsService::class)->update($product, ['serial_prefix' => 'foo'], $user, 'transfer');
    }

    public function test_product_bulk_delete_denies_manual_history_without_sql_error(): void
    {
        $product = Product::factory()->create();
        ProductInventoryAudit::factory()->create(['product_id' => $product->id]);
        $free = Product::factory()->create();
        $user = $this->user('product.view', 'product.delete');
        Livewire::actingAs($user, 'web')->test(ListProducts::class)
            ->callTableBulkAction('delete', [$product, $free]);
        $this->assertModelExists($product);
        $this->assertModelMissing($free);
    }

    public function test_bulk_order_delete_denies_manual_capacity_commitment(): void
    {
        $product = Product::factory()->create();
        $order = $this->order($product);
        $payment = Payment::create(['order_id' => $order->id, 'amount' => 200, 'transaction_id' => 'bulk-manual-hold', 'status' => 'pending', 'platform' => 'main']);
        ProductStockReservation::create(['product_id' => $product->id, 'order_item_id' => $order->items()->first()->id, 'payment_id' => $payment->id, 'quantity' => 2, 'status' => 'reserved']);
        $user = $this->user('order.view', 'order.delete');
        Livewire::actingAs($user, 'web')->test(ListOrders::class)->callTableBulkAction('delete', [$order]);
        $this->assertNotSoftDeleted($order);
    }

    public function test_completed_order_with_unfinished_production_still_blocks_mode_change(): void
    {
        $product = Product::factory()->create();
        $order = $this->order($product, ['status' => 'completed', 'payment_status' => 'paid', 'serial_fulfillment_status' => 'awaiting_production']);
        $order->items()->first()->update(['made_to_order' => true, 'serial_fulfillment_status' => 'awaiting_production']);
        $user = $this->user('product.edit', 'serial.manage');
        $this->expectException(ValidationException::class);
        app(ProductInventorySettingsService::class)->update($product, ['inventory_mode' => 'serial', 'track_serials' => true, 'serial_prefix' => 'ton'], $user, 'switch');
    }
}
