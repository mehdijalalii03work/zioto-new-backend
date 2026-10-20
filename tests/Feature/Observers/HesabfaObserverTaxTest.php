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

    private function buildProductItems(Order $order, string|array $hesabfaItem = '000001'): array
    {
        $service = $this->createMock(HesabfaService::class);
        $item = is_array($hesabfaItem) ? $hesabfaItem : ['Code' => $hesabfaItem];
        $service->method('findItemBySku')->willReturn($item);

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

    public function test_pack_product_is_sent_as_single_items_with_expanded_quantity(): void
    {
        $product = Product::create([
            'name' => 'شمش نقره زیوتو ۱ گرم (پک ۵ عددی)',
            'slug' => 'silver-pack-'.uniqid(),
            'sku' => 'SILVER-PACK-001',
            'metal_type' => 'silver',
            'pack_size' => 5,
        ]);

        $order = Order::factory()->create();
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => 5000000,
            'quantity' => 2,
            'subtotal' => 10000000,
        ]);
        $order->load('items.product');

        $items = $this->buildProductItems($order, ['Code' => '000001', 'Unit' => 'عدد']);

        $this->assertCount(1, $items);
        $this->assertSame('عدد', $items[0]['unit']);
        $this->assertSame(10, $items[0]['quantity']);
        $this->assertSame(909091, $items[0]['unitPrice']);
        $this->assertSame(9090910, $items[0]['unitPrice'] * $items[0]['quantity']);
        $this->assertSame(909090, $items[0]['tax']);
    }
}
