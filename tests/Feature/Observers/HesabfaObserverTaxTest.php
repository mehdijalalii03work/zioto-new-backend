<?php

namespace Tests\Feature\Observers;

use App\Models\Setting;
use App\Observers\HesabfaObserver;
use App\Services\HesabfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * Verifies that silver invoice rows in the Hesabfa sync carry the tax for
 * the whole row (per-unit tax × quantity), so the invoice total matches the
 * amount the customer actually paid.
 */
class HesabfaObserverTaxTest extends TestCase
{
    use RefreshDatabase;

    private function buildProductItems(Order $order, string $itemCode = '000001'): array
    {
        $service = $this->createMock(HesabfaService::class);
        $service->method('findItemBySku')->willReturn(['Code' => $itemCode]);

        $observer = new HesabfaObserver($service);

        $method = new \ReflectionMethod(HesabfaObserver::class, 'buildProductItems');

        return $method->invoke($observer, $order, 1);
    }

    private function makeOrderWithItem(string $sku, ?string $metalType, string $productName, int $unitPrice, int $quantity): Order
    {
        $product = Product::create([
            'name' => $productName,
            'slug' => 'product-'.uniqid(),
            'sku' => $sku,
            'metal_type' => $metalType,
        ]);

        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $productName,
            'product_price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => $unitPrice * $quantity,
        ]);
        $order->load('items.product');

        return $order;
    }

    public function test_silver_item_tax_is_multiplied_by_quantity(): void
    {
        Setting::create(['key' => 'tax_silver', 'value' => '10', 'type' => 'number', 'category' => 'tax', 'label' => 'درصد مالیات نقره']);

        $order = $this->makeOrderWithItem('SILVER-001', 'silver', 'شمش نقره', 1100000, 2);

        $items = $this->buildProductItems($order);

        $this->assertCount(1, $items);
        $this->assertSame(1000000, $items[0]['unitPrice']);
        $this->assertSame(2, $items[0]['quantity']);
        $this->assertSame(200000, $items[0]['tax']);
    }

    public function test_single_silver_item_tax_is_not_multiplied(): void
    {
        Setting::create(['key' => 'tax_silver', 'value' => '10', 'type' => 'number', 'category' => 'tax', 'label' => 'درصد مالیات نقره']);

        $order = $this->makeOrderWithItem('SILVER-002', 'silver', 'شمش نقره', 1100000, 1);

        $items = $this->buildProductItems($order);

        $this->assertCount(1, $items);
        $this->assertSame(1000000, $items[0]['unitPrice']);
        $this->assertSame(100000, $items[0]['tax']);
    }

    public function test_gold_item_has_no_tax(): void
    {
        $order = $this->makeOrderWithItem('GOLD-001', 'gold', 'شمش طلا', 1100000, 3);

        $items = $this->buildProductItems($order);

        $this->assertCount(1, $items);
        $this->assertSame(1100000, $items[0]['unitPrice']);
        $this->assertSame(0, $items[0]['tax']);
    }

    public function test_silver_tax_rate_from_settings(): void
    {
        Setting::create(['key' => 'tax_silver', 'value' => '20', 'type' => 'number', 'category' => 'tax', 'label' => 'درصد مالیات نقره']);

        $order = $this->makeOrderWithItem('SILVER-003', 'silver', 'شمش نقره', 1200000, 2);

        $items = $this->buildProductItems($order);

        $this->assertCount(1, $items);
        $this->assertSame(1000000, $items[0]['unitPrice']);
        $this->assertSame(400000, $items[0]['tax']);
    }
}
