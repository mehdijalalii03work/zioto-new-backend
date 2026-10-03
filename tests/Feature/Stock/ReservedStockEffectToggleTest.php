<?php

namespace Tests\Feature\Stock;

use App\Http\Controllers\Api\PaymentController;
use App\Models\Setting;
use App\Services\HesabfaService;
use App\Services\StockSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * The `ignore_reserved_stock` setting must neutralise the *effect* of the
 * reservation without touching the reservation *writes*: orders keep adding
 * to `hesabfa_reserved_stock` on confirm and keep subtracting from it on
 * completion/cancellation, but available stock is then decided by the stock
 * received from Hesabfa alone.
 */
class ReservedStockEffectToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The Hesabfa invoice sync observer would try to reach the API otherwise.
        config(['hesabfa.auto_sync' => false]);
    }

    private function setIgnoreReservedStock(bool $value): void
    {
        Setting::updateOrCreate(['key' => 'ignore_reserved_stock'], [
            'value' => $value ? 'true' : 'false',
            'type' => 'boolean',
            'category' => 'hesabfa',
            'label' => 'نادیده گرفتن رزرو موجودی',
        ]);
    }

    private function makeProduct(int $physical, float $reserved = 0, float $manualReserved = 0): Product
    {
        return Product::create([
            'name' => 'شمش طلا ۱۸ عیار',
            'slug' => 'product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'stock_quantity' => $physical,
            'hesabfa_physical_stock' => $physical,
            'hesabfa_reserved_stock' => $reserved,
            'hesabfa_manual_reserved' => $manualReserved,
        ]);
    }

    private function makeConfirmedOrder(Product $product, int $quantity): Order
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 1000000,
            'quantity' => $quantity,
            'subtotal' => 1000000 * $quantity,
        ]);

        return $order;
    }

    private function reservedStock(Product $product): float
    {
        return (float) $product->fresh()->hesabfa_reserved_stock;
    }

    public function test_reservation_is_still_recorded_while_its_effect_is_ignored(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);
        $this->setIgnoreReservedStock(true);

        $product = $this->makeProduct(physical: 10);
        $order = $this->makeConfirmedOrder($product, quantity: 3);

        $order->update(['status' => 'confirmed']);

        $this->assertSame(3.0, $this->reservedStock($product));
    }

    public function test_reservation_is_still_released_when_the_order_completes(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);
        $this->setIgnoreReservedStock(true);

        $product = $this->makeProduct(physical: 10);
        $order = $this->makeConfirmedOrder($product, quantity: 3);

        $order->update(['status' => 'confirmed']);
        $order->update(['status' => 'completed']);

        $this->assertSame(0.0, $this->reservedStock($product));
    }

    public function test_reservation_is_still_released_when_the_order_is_cancelled(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);
        $this->setIgnoreReservedStock(true);

        $product = $this->makeProduct(physical: 10);
        $order = $this->makeConfirmedOrder($product, quantity: 2);

        $order->update(['status' => 'confirmed']);
        $order->update(['status' => 'cancelled']);

        $this->assertSame(0.0, $this->reservedStock($product));
    }

    public function test_sellable_stock_ignores_reserved_when_the_toggle_is_on(): void
    {
        $this->setIgnoreReservedStock(true);

        $product = $this->makeProduct(physical: 10, reserved: 4);

        $this->assertSame(10, $product->sellable_stock);
    }

    public function test_sellable_stock_subtracts_reserved_when_the_toggle_is_off(): void
    {
        $this->setIgnoreReservedStock(false);

        $product = $this->makeProduct(physical: 10, reserved: 4);

        $this->assertSame(6, $product->sellable_stock);
    }

    public function test_manual_reservation_still_applies_when_the_toggle_is_on(): void
    {
        $this->setIgnoreReservedStock(true);

        $product = $this->makeProduct(physical: 10, reserved: 4, manualReserved: 3);

        $this->assertSame(7, $product->sellable_stock);
    }

    public function test_sellable_stock_never_goes_below_zero(): void
    {
        $this->setIgnoreReservedStock(false);

        $product = $this->makeProduct(physical: 2, reserved: 5);

        $this->assertSame(0, $product->sellable_stock);
    }

    public function test_missing_setting_defaults_to_ignoring_the_reservation(): void
    {
        Setting::where('key', 'ignore_reserved_stock')->delete();

        $product = $this->makeProduct(physical: 10, reserved: 4);

        $this->assertTrue(Product::ignoresReservedStock());
        $this->assertSame(10, $product->sellable_stock);
    }

    public function test_stock_sync_writes_the_full_physical_stock_when_the_toggle_is_on(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);
        $this->setIgnoreReservedStock(true);

        $product = $this->makeProduct(physical: 10, reserved: 4);

        $this->stockSyncService()->updateStockByItemCode($product->sku, 10);

        $product->refresh();

        $this->assertSame(10, $product->stock_quantity);
        $this->assertSame(10.0, (float) $product->hesabfa_physical_stock);
        $this->assertSame(4.0, (float) $product->hesabfa_reserved_stock);
    }

    public function test_stock_sync_subtracts_reserved_when_the_toggle_is_off(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);
        $this->setIgnoreReservedStock(false);

        $product = $this->makeProduct(physical: 10, reserved: 4);

        $this->stockSyncService()->updateStockByItemCode($product->sku, 10);

        $this->assertSame(6, $product->fresh()->stock_quantity);
    }

    public function test_stock_sync_still_subtracts_manual_reserved_when_the_toggle_is_on(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);
        $this->setIgnoreReservedStock(true);

        $product = $this->makeProduct(physical: 10, reserved: 4, manualReserved: 2);

        $this->stockSyncService()->updateStockByItemCode($product->sku, 10);

        $this->assertSame(8, $product->fresh()->stock_quantity);
    }

    public function test_order_stock_validation_accepts_an_order_that_only_the_reserved_stock_would_block(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);
        $this->setIgnoreReservedStock(true);

        $product = $this->makeProduct(physical: 5, reserved: 5);
        $order = $this->makeConfirmedOrder($product, quantity: 5);

        $this->assertTrue($this->validateOrderStock($order)['valid']);
    }

    public function test_order_stock_validation_blocks_an_order_beyond_the_physical_stock(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);
        $this->setIgnoreReservedStock(true);

        $product = $this->makeProduct(physical: 5, reserved: 0);
        $order = $this->makeConfirmedOrder($product, quantity: 6);

        $this->assertFalse($this->validateOrderStock($order)['valid']);
    }

    public function test_order_stock_validation_subtracts_reserved_when_the_toggle_is_off(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);
        $this->setIgnoreReservedStock(false);

        $product = $this->makeProduct(physical: 5, reserved: 3);
        $order = $this->makeConfirmedOrder($product, quantity: 4);

        $this->assertFalse($this->validateOrderStock($order)['valid']);
    }

    private function stockSyncService(): StockSyncService
    {
        return new StockSyncService($this->createMock(HesabfaService::class));
    }

    /**
     * @return array{valid: bool, message?: string}
     */
    private function validateOrderStock(Order $order): array
    {
        $controller = app(PaymentController::class);
        $method = new \ReflectionMethod($controller, 'validateOrderStock');

        return $method->invoke($controller, $order->fresh()->load('items.product'));
    }
}
