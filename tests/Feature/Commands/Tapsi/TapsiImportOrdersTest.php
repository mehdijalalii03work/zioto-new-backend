<?php

namespace Tests\Feature\Commands\Tapsi;

use App\Models\Setting;
use App\Models\User;
use App\Support\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Tests\TestCase;

class TapsiImportOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tapsi.order_detail_delay' => 0]);
    }

    private function fakeTapsiApi(array $ordersList, array $orderDetails = []): void
    {
        Http::fake([
            '*vendorgw.tapsi.shop*' => function ($request) use ($ordersList, $orderDetails) {
                $url = $request->url();

                // GET /orders/{orderId} — order details
                if ($request->method() === 'GET' && preg_match('#/orders/(\d+)$#', $url, $m)) {
                    $orderId = $m[1];

                    return Http::response($orderDetails[$orderId] ?? [
                        'success' => true,
                        'data' => [
                            'order' => ['orderNumber' => 'TS-'.$orderId],
                            'items' => [],
                            'shipments' => [],
                        ],
                    ], 200);
                }

                // POST /orders — order list
                return Http::response([
                    'success' => true,
                    'data' => [
                        'pageNumber' => 0,
                        'pageSize' => 20,
                        'totalItems' => count($ordersList),
                        'items' => $ordersList,
                    ],
                ], 200);
            },
        ]);
    }

    private function enableTapsi(): void
    {
        Setting::updateOrCreate(
            ['key' => 'tapsi_shop_outgoing_auth_token'],
            ['value' => 'test-token', 'type' => 'string', 'category' => 'tapsi', 'label' => 'Tapsi Token']
        );
    }

    public function test_command_fails_when_tapsi_disabled(): void
    {
        config(['tapsi.enabled' => false]);

        $this->artisan('tapsi:import-orders')
            ->assertExitCode(1);
    }

    public function test_imports_completed_orders(): void
    {
        config(['tapsi.enabled' => true]);
        $this->enableTapsi();

        $ordersList = [
            [
                'id' => '100001',
                'orderNumber' => 'TS-001',
                'finalPrice' => 5000000,
                'serviceFee' => 25000,
                'voucherTotalFee' => 0,
                'createdOn' => '2025-06-01T10:00:00Z',
                'customerMobile' => '09121234567',
                'customerFullName' => 'علی رضایی',
                'receiverFullName' => 'علی رضایی',
                'receiverMobile' => '09121234567',
                'deliveryAddress' => 'تهران، خیابان ولیعصر',
                'shipmentOrderBundleNumbers' => ['BUNDLE-001'],
                'stateTitle' => 'تحویل شده',
                'deliveryMethod' => '1',
            ],
        ];

        $orderDetails = [
            '100001' => [
                'success' => true,
                'data' => [
                    'order' => ['orderNumber' => 'TS-001'],
                    'items' => [
                        [
                            'orderItemId' => '200001',
                            'sku' => 'gold-bar-1g',
                            'name' => 'شمش طلا ۱ گرم',
                            'price' => 4800000,
                            'finalPrice' => 5000000,
                            'quantity' => 1,
                            'customerMobile' => '09121234567',
                        ],
                    ],
                    'shipments' => [
                        ['number' => 'SHIP-001', 'status' => 'delivered'],
                    ],
                ],
            ],
        ];

        $this->fakeTapsiApi($ordersList, $orderDetails);

        $this->artisan('tapsi:import-orders --from=2025-01-01 --to=2025-12-31')
            ->assertExitCode(0);

        $order = Order::withoutTenantScope()->where('tapsi_order_id', '100001')->first();

        $this->assertNotNull($order);
        $this->assertEquals(Platform::TAPSI, $order->platform);
        $this->assertEquals('completed', $order->status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('TS-001', $order->order_number);
        $this->assertEquals(5000000, $order->total_amount); // raw rial from Tapsi API
        $this->assertEquals('100001', $order->tapsi_order_id);
        $this->assertEquals('BUNDLE-001', $order->tapsi_shipment_bundle);
    }

    public function test_creates_order_items(): void
    {
        config(['tapsi.enabled' => true]);
        $this->enableTapsi();

        $ordersList = [
            ['id' => '100002', 'orderNumber' => 'TS-002', 'finalPrice' => 3000000, 'serviceFee' => 0, 'voucherTotalFee' => 0, 'createdOn' => '2025-06-01T10:00:00Z', 'customerMobile' => '09129876543', 'shipmentOrderBundleNumbers' => [], 'stateTitle' => 'تحویل شده'],
        ];

        $orderDetails = [
            '100002' => [
                'success' => true,
                'data' => [
                    'order' => ['orderNumber' => 'TS-002'],
                    'items' => [
                        ['orderItemId' => '200002', 'sku' => 'silver-bar-5g', 'name' => 'شمش نقره ۵ گرم', 'price' => 2000000, 'finalPrice' => 1500000, 'quantity' => 1],
                        ['orderItemId' => '200003', 'sku' => 'silver-bar-10g', 'name' => 'شمش نقره ۱۰ گرم', 'price' => 1000000, 'finalPrice' => 1500000, 'quantity' => 1],
                    ],
                    'shipments' => [],
                ],
            ],
        ];

        $this->fakeTapsiApi($ordersList, $orderDetails);

        $this->artisan('tapsi:import-orders --from=2025-01-01 --to=2025-12-31')
            ->assertExitCode(0);

        $order = Order::withoutTenantScope()->where('tapsi_order_id', '100002')->first();
        $this->assertNotNull($order);
        $this->assertCount(2, $order->items);
        $this->assertEquals('شمش نقره ۵ گرم', $order->items[0]->product_name);
        $this->assertEquals(1, $order->items[0]->quantity);
    }

    public function test_skips_duplicate_orders(): void
    {
        config(['tapsi.enabled' => true]);
        $this->enableTapsi();

        // Pre-create an order with this tapsi_order_id
        Order::withoutTenantScope()->create([
            'platform' => Platform::TAPSI,
            'order_number' => 'TS-EXISTING',
            'status' => 'completed',
            'total_amount' => 100000,
            'payment_status' => 'paid',
            'tapsi_order_id' => '100003',
        ]);

        $ordersList = [
            ['id' => '100003', 'orderNumber' => 'TS-EXISTING', 'finalPrice' => 2000000, 'serviceFee' => 0, 'voucherTotalFee' => 0, 'createdOn' => '2025-06-01T10:00:00Z', 'customerMobile' => '09120000000', 'shipmentOrderBundleNumbers' => [], 'stateTitle' => 'تحویل شده'],
        ];

        $this->fakeTapsiApi($ordersList, []);

        $this->artisan('tapsi:import-orders --from=2025-01-01 --to=2025-12-31')
            ->assertExitCode(0);

        $this->assertEquals(1, Order::withoutTenantScope()->where('tapsi_order_id', '100003')->count());
    }

    public function test_dry_run_does_not_persist(): void
    {
        config(['tapsi.enabled' => true]);
        $this->enableTapsi();

        $ordersList = [
            ['id' => '100004', 'orderNumber' => 'TS-004', 'finalPrice' => 1000000, 'serviceFee' => 0, 'voucherTotalFee' => 0, 'createdOn' => '2025-06-01T10:00:00Z', 'customerMobile' => '09121111111', 'shipmentOrderBundleNumbers' => [], 'stateTitle' => 'تحویل شده'],
        ];

        $this->fakeTapsiApi($ordersList, []);

        $this->artisan('tapsi:import-orders --from=2025-01-01 --to=2025-12-31 --dry-run')
            ->assertExitCode(0);

        $this->assertNull(Order::withoutTenantScope()->where('tapsi_order_id', '100004')->first());
    }

    public function test_links_to_local_product_via_tapsi_product_id(): void
    {
        config(['tapsi.enabled' => true]);
        $this->enableTapsi();

        $product = Product::withoutTenantScope()->firstOrCreate(
            ['tapsi_product_id' => 'tapsi-gold-1g'],
            ['name' => 'شمش طلا ۱ گرم', 'sku' => 'local-gold-1g', 'slug' => 'local-gold-1g', 'price' => 50000000, 'platform' => 'main']
        );

        $ordersList = [
            ['id' => '100005', 'orderNumber' => 'TS-005', 'finalPrice' => 5000000, 'serviceFee' => 0, 'voucherTotalFee' => 0, 'createdOn' => '2025-06-01T10:00:00Z', 'customerMobile' => '09122222222', 'shipmentOrderBundleNumbers' => [], 'stateTitle' => 'تحویل شده'],
        ];

        $orderDetails = [
            '100005' => [
                'success' => true,
                'data' => [
                    'order' => ['orderNumber' => 'TS-005'],
                    'items' => [
                        ['orderItemId' => '200005', 'sku' => 'tapsi-gold-1g', 'name' => 'شمش طلا', 'price' => 5000000, 'finalPrice' => 5000000, 'quantity' => 1],
                    ],
                    'shipments' => [],
                ],
            ],
        ];

        $this->fakeTapsiApi($ordersList, $orderDetails);

        $this->artisan('tapsi:import-orders --from=2025-01-01 --to=2025-12-31')
            ->assertExitCode(0);

        $order = Order::withoutTenantScope()->where('tapsi_order_id', '100005')->first();
        $this->assertNotNull($order);
        $this->assertEquals($product->id, $order->items[0]->product_id);
    }

    public function test_price_conversion_rial_to_toman(): void
    {
        config(['tapsi.enabled' => true]);
        $this->enableTapsi();

        $ordersList = [
            ['id' => '100006', 'orderNumber' => 'TS-006', 'finalPrice' => 5250000, 'serviceFee' => 250000, 'voucherTotalFee' => 150000, 'createdOn' => '2025-06-01T10:00:00Z', 'customerMobile' => '09123333333', 'shipmentOrderBundleNumbers' => [], 'stateTitle' => 'تحویل شده'],
        ];

        $this->fakeTapsiApi($ordersList, []);

        $this->artisan('tapsi:import-orders --from=2025-01-01 --to=2025-12-31')
            ->assertExitCode(0);

        $order = Order::withoutTenantScope()->where('tapsi_order_id', '100006')->first();
        $this->assertNotNull($order);
        $this->assertEquals(5250000, $order->total_amount); // raw rial from Tapsi API
        $this->assertEquals(250000, $order->tapsi_service_fee); // raw rial from Tapsi API
        $this->assertEquals(150000, $order->tapsi_voucher_fee); // raw rial from Tapsi API
    }

    public function test_creates_user_for_tapsi_order(): void
    {
        config(['tapsi.enabled' => true]);
        $this->enableTapsi();

        $ordersList = [
            ['id' => '100007', 'orderNumber' => 'TS-007', 'finalPrice' => 1000000, 'serviceFee' => 0, 'voucherTotalFee' => 0, 'createdOn' => '2025-06-01T10:00:00Z', 'customerMobile' => '09124444444', 'customerFullName' => 'محمد محمدی', 'customerNationalCode' => '1234567890', 'receiverFullName' => 'محمد محمدی', 'shipmentOrderBundleNumbers' => [], 'stateTitle' => 'تحویل شده'],
        ];

        $this->fakeTapsiApi($ordersList, []);

        $this->artisan('tapsi:import-orders --from=2025-01-01 --to=2025-12-31')
            ->assertExitCode(0);

        $order = Order::withoutTenantScope()->where('tapsi_order_id', '100007')->first();
        $this->assertNotNull($order);
        $this->assertNotNull($order->user_id);

        $user = User::withoutTenantScope()->find($order->user_id);
        $this->assertEquals(Platform::TAPSI, $user->platform);
        $this->assertEquals('09124444444', $user->phone);
        $this->assertEquals('محمد محمدی', $user->name);
    }

    public function test_preserves_original_created_at(): void
    {
        config(['tapsi.enabled' => true]);
        $this->enableTapsi();

        $ordersList = [
            ['id' => '100008', 'orderNumber' => 'TS-008', 'finalPrice' => 1000000, 'serviceFee' => 0, 'voucherTotalFee' => 0, 'createdOn' => '2025-03-15T14:30:00Z', 'customerMobile' => '09125555555', 'shipmentOrderBundleNumbers' => [], 'stateTitle' => 'تحویل شده'],
        ];

        $this->fakeTapsiApi($ordersList, []);

        $this->artisan('tapsi:import-orders --from=2025-01-01 --to=2025-12-31')
            ->assertExitCode(0);

        $order = Order::withoutTenantScope()->where('tapsi_order_id', '100008')->first();
        $this->assertNotNull($order);
        $this->assertEquals('2025-03-15', $order->created_at->format('Y-m-d'));
    }
}
