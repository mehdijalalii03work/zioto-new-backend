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
            'hesabfa.discount_item_code' => '1212',
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
        $shippingMethod = ShippingMethod::factory()->create(['id' => 500]);
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

        $this->assertCount(2, $items);
        $this->assertSame(2_065_455, $items[0]['unitPrice']);
        $this->assertSame(6_545, $items[0]['tax']);
        $this->assertSame(-200_000, $items[1]['unitPrice']);
        $this->assertSame('1212', $items[1]['itemCode']);
        $this->assertSame('تخفیف کد SAVE200', $items[1]['description']);
        $this->assertSame(1_872_000, array_sum(array_map(
            static fn (array $item): int => $item['unitPrice'] * $item['quantity'] + $item['tax'] - $item['discount'],
            $items,
        )));
    }

    public function test_installment_fee_and_its_tax_are_distributed_across_products_and_shipping(): void
    {
        config(['hesabfa.shipping_item_code' => 'SHIPPING']);

        $product = Product::create([
            'name' => 'Product',
            'slug' => 'hesabfa-fee-distribution-product',
            'sku' => 'FEE-001',
            'price' => 1_000_000,
        ]);
        $order = Order::factory()->create([
            'total_amount' => 1_144_000,
            'payment_method' => 'installment',
            'notes' => json_encode(['installment_fee' => 44_000]),
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Product',
            'product_price' => 1_000_000,
            'quantity' => 1,
            'subtotal' => 1_000_000,
        ]);
        $shippingMethod = ShippingMethod::factory()->create(['id' => 501]);
        OrderShipping::create([
            'order_id' => $order->id,
            'shipping_method_id' => $shippingMethod->id,
            'shipping_method_name' => 'ارسال',
            'shipping_cost' => 100_000,
            'tax_amount' => 10_000,
        ]);
        $order->load('items.product', 'shipping');

        $service = $this->createMock(HesabfaService::class);
        $service->expects($this->once())
            ->method('findItemBySku')
            ->with('FEE-001')
            ->willReturn(['Code' => 'SKU-FEE-001']);

        $observer = new HesabfaObserver($service);
        $method = new \ReflectionMethod(HesabfaObserver::class, 'buildInvoiceItems');
        $items = $method->invoke($observer, $order);

        $this->assertCount(2, $items);
        $this->assertSame(1_036_364, $items[0]['unitPrice']);
        $this->assertSame(3_636, $items[0]['tax']);
        $this->assertSame(93_636, $items[1]['unitPrice']);
        $this->assertSame(10_364, $items[1]['tax']);
        $this->assertSame(1_144_000, array_sum(array_map(
            static fn (array $item): int => $item['unitPrice'] * $item['quantity'] + $item['tax'] - $item['discount'],
            $items,
        )));
    }

    public function test_installment_invoice_does_not_require_a_separate_fee_item_code(): void
    {
        config(['hesabfa.installment_fee_item_code' => '']);
        $order = Order::factory()->create(['payment_method' => 'installment']);
        $service = $this->createMock(HesabfaService::class);
        $service->expects($this->never())->method('getItemByCode');

        $observer = new HesabfaObserver($service);
        $method = new \ReflectionMethod(HesabfaObserver::class, 'preValidateInvoiceItems');
        $result = $method->invoke($observer, $order);

        $this->assertSame(['success' => true], $result);
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
