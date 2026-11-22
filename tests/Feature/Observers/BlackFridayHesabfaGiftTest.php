<?php

namespace Tests\Feature\Observers;

use App\Observers\HesabfaObserver;
use App\Services\BlackFridayGiftService;
use App\Services\HesabfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

class BlackFridayHesabfaGiftTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithProductAmount(int $amountInToman, string $createdAt, bool $paidViaParsian = true): Order
    {
        $product = Product::create([
            'name' => 'محصول تست',
            'slug' => 'bf-test-'.uniqid(),
            'sku' => 'BF-'.uniqid(),
            'price' => $amountInToman * 10,
        ]);
        $order = Order::factory()->create([
            'created_at' => Carbon::parse($createdAt, 'Asia/Tehran'),
            'discount_amount' => 2_000_000,
            'total_amount' => $amountInToman * 10 - 2_000_000,
            'payment_method' => 'online',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $amountInToman * 10,
            'quantity' => 1,
            'subtotal' => $amountInToman * 10,
        ]);

        if ($paidViaParsian) {
            $this->recordPayment($order, 'parsian', 'paid');
        }

        return $order->load('items.product');
    }

    private function recordPayment(Order $order, string $gateway, string $status): void
    {
        $order->payments()->create([
            'user_id' => $order->user_id,
            'platform' => $order->platform,
            'transaction_id' => fake()->uuid(),
            'amount' => $order->total_amount,
            'payment_method' => $order->payment_method,
            'gateway' => $gateway,
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }

    public function test_gift_quantities_match_cart_tiers_and_boundaries(): void
    {
        $service = new BlackFridayGiftService;
        foreach ([
            [19_999_999, 1, 0],
            [20_000_000, 0, 1],
            [39_999_999, 0, 1],
            [40_000_000, 1, 1],
            [99_999_999, 1, 1],
            [100_000_000, 1, 1],
            [199_999_999, 1, 1],
            [200_000_000, 1, 2],
            [1_077_460_000, 1, 10],
        ] as [$amount, $halfGram, $oneGram]) {
            $this->assertSame(
                ['half_gram' => $halfGram, 'one_gram' => $oneGram],
                $service->quantitiesForOrder($this->orderWithProductAmount($amount, '2026-11-25 12:00:00')),
                "Unexpected gift for {$amount} Toman"
            );
        }
    }

    public function test_only_orders_created_during_campaign_receive_gifts(): void
    {
        $service = new BlackFridayGiftService;
        foreach ([
            ['2026-11-21 23:59:59', false],
            ['2026-11-22 00:00:00', true],
            ['2026-12-01 23:59:59', true],
            ['2026-12-02 00:00:00', false],
        ] as [$createdAt, $eligible]) {
            $order = $this->orderWithProductAmount(40_000_000, $createdAt);
            $this->assertSame($eligible, $service->quantitiesForOrder($order)['half_gram'] === 1);
        }
    }

    public function test_invoice_gift_rows_are_free_and_preserve_total(): void
    {
        config([
            'hesabfa.black_friday_half_gram_gift_item_code' => 'HALF',
            'hesabfa.black_friday_one_gram_gift_item_code' => 'ONE',
            'hesabfa.discount_item_code' => 'DISCOUNT',
        ]);
        $order = $this->orderWithProductAmount(40_000_000, '2026-11-25 12:00:00');
        $hesabfa = $this->createMock(HesabfaService::class);
        $hesabfa->method('findItemBySku')->willReturnCallback(
            static fn (string $sku): array => ['Code' => in_array($sku, ['HALF', 'ONE'], true) ? $sku : 'PRODUCT']
        );
        $observer = new HesabfaObserver($hesabfa);
        $items = (new \ReflectionMethod(HesabfaObserver::class, 'buildInvoiceItems'))->invoke($observer, $order);

        $this->assertSame(['PRODUCT', 'DISCOUNT', 'HALF', 'ONE'], array_column($items, 'itemCode'));
        $this->assertSame([1, 2, 3, 4], array_column($items, 'rowNumber'));
        foreach (array_slice($items, -2) as $gift) {
            $this->assertSame(1, $gift['quantity']);
            $this->assertSame(0, $gift['unitPrice']);
            $this->assertSame(0, $gift['tax']);
            $this->assertSame(0, $gift['discount']);
        }
        $this->assertSame((int) $order->total_amount, array_sum(array_map(
            static fn (array $item): int => $item['quantity'] * $item['unitPrice'] + $item['tax'] - $item['discount'],
            $items
        )));
    }

    public function test_invoice_skips_gift_rows_and_validation_without_a_paid_parsian_payment(): void
    {
        config([
            'hesabfa.black_friday_half_gram_gift_item_code' => null,
            'hesabfa.black_friday_one_gram_gift_item_code' => null,
            'hesabfa.discount_item_code' => 'DISCOUNT',
        ]);
        $order = $this->orderWithProductAmount(40_000_000, '2026-11-25 12:00:00', paidViaParsian: false);
        $hesabfa = $this->createMock(HesabfaService::class);
        $hesabfa->method('findItemBySku')->willReturn(['Code' => 'PRODUCT']);
        $hesabfa->method('getItemByCode')->with('DISCOUNT')->willReturn(['Code' => 'DISCOUNT']);
        $observer = new HesabfaObserver($hesabfa);
        $validate = new \ReflectionMethod(HesabfaObserver::class, 'preValidateInvoiceItems');
        $build = new \ReflectionMethod(HesabfaObserver::class, 'buildInvoiceItems');

        foreach ([null, ['parsian', 'pending'], ['parsian', 'failed'], ['smartis', 'paid']] as $payment) {
            if ($payment !== null) {
                $this->recordPayment($order, ...$payment);
            }

            $this->assertTrue($validate->invoke($observer, $order)['success']);
            $this->assertSame(['PRODUCT', 'DISCOUNT'], array_column($build->invoke($observer, $order), 'itemCode'));
        }
    }

    public function test_missing_or_unknown_gift_code_blocks_invoice_sync(): void
    {
        $order = $this->orderWithProductAmount(20_000_000, '2026-11-25 12:00:00');
        $hesabfa = $this->createMock(HesabfaService::class);
        $observer = new HesabfaObserver($hesabfa);
        $validate = new \ReflectionMethod(HesabfaObserver::class, 'preValidateInvoiceItems');

        $this->assertFalse($validate->invoke($observer, $order)['success']);
        config(['hesabfa.black_friday_one_gram_gift_item_code' => 'ONE']);
        $hesabfa->method('getItemByCode')->willReturn(null);
        $this->assertFalse($validate->invoke($observer, $order)['success']);
    }
}
