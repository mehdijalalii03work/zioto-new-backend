<?php

namespace Tests\Feature\Observers;

use App\Models\OrderShipping;
use App\Models\ShippingMethod;
use App\Observers\HesabfaObserver;
use App\Services\HesabfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

class HesabfaDiscountInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_discount_is_added_as_invoice_row_and_matches_order_total(): void
    {
        config([
            'hesabfa.discount_item_code' => 'DISCOUNT-ITEM',
            'hesabfa.installment_fee_item_code' => 'INSTALLMENT-FEE',
        ]);
        $product = Product::create([
            'name' => 'Product',
            'slug' => 'hesabfa-discount-product',
            'sku' => 'DISC-001',
            'price' => 2_000_000,
        ]);
        $order = Order::factory()->create([
            'total_amount' => 1_872_000,
            'discount_code_snapshot' => 'SAVE200',
            'discount_amount' => 200_000,
            'payment_method' => 'installment',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Product',
            'product_price' => 2_000_000,
            'quantity' => 1,
            'subtotal' => 2_000_000,
        ]);
        $shippingMethod = ShippingMethod::factory()->create();
        OrderShipping::create([
            'order_id' => $order->id,
            'shipping_method_id' => $shippingMethod->id,
            'shipping_method_name' => 'ارسال',
            'shipping_cost' => 0,
        ]);
        $order->load('items.product', 'shipping');

        $service = $this->createMock(HesabfaService::class);
        $service->expects($this->once())
            ->method('findItemBySku')
            ->with('DISC-001')
            ->willReturn(['Code' => 'SKU-001']);

        $observer = new HesabfaObserver($service);
        $method = new \ReflectionMethod(HesabfaObserver::class, 'buildInvoiceItems');
        $items = $method->invoke($observer, $order);

        $this->assertCount(3, $items);
        $this->assertSame(2_000_000, $items[0]['unitPrice']);
        $this->assertSame(-200_000, $items[1]['unitPrice']);
        $this->assertSame('DISCOUNT-ITEM', $items[1]['itemCode']);
        $this->assertSame('تخفیف کد SAVE200', $items[1]['description']);
        $this->assertSame(72_000, $items[2]['unitPrice'] + $items[2]['tax']);
        $this->assertSame(1_872_000, array_sum(array_map(
            static fn (array $item): int => $item['unitPrice'] * $item['quantity'] + $item['tax'] - $item['discount'],
            $items,
        )));
    }

    public function test_invoice_without_discount_has_no_discount_row(): void
    {
        $product = Product::create([
            'name' => 'Product',
            'slug' => 'hesabfa-no-discount-product',
            'sku' => 'NODISC-001',
            'price' => 2_000_000,
        ]);
        $order = Order::factory()->create(['total_amount' => 2_000_000]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Product',
            'product_price' => 2_000_000,
            'quantity' => 1,
            'subtotal' => 2_000_000,
        ]);
        $order->load('items.product');

        $service = $this->createMock(HesabfaService::class);
        $service->expects($this->once())
            ->method('findItemBySku')
            ->with('NODISC-001')
            ->willReturn(['Code' => 'SKU-002']);

        $observer = new HesabfaObserver($service);
        $method = new \ReflectionMethod(HesabfaObserver::class, 'buildInvoiceItems');

        $items = $method->invoke($observer, $order);

        $this->assertCount(1, $items);
        $this->assertSame(2_000_000, $items[0]['unitPrice']);
    }
}
