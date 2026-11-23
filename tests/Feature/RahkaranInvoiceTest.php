<?php

namespace Tests\Feature;

use App\Jobs\SyncRahkaranInvoice;
use App\Models\RahkaranSerial;
use App\Models\Setting;
use App\Services\RahkaranInvoiceService;
use App\Services\RahkaranService;
use App\Services\TapsiOrderProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Bus;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RahkaranInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function enableInvoicing(): void
    {
        Setting::create(['key' => 'send_invoices_to_rahkaran', 'value' => 'true', 'type' => 'boolean', 'category' => 'accounting', 'label' => 'Rahkaran']);
    }

    private function order(array $attributes = [], array $metals = ['gold', 'silver']): Order
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'payment_status' => 'paid', 'rahkaran_customer_id' => 1, 'total_amount' => count($metals) * 100, 'discount_amount' => 0, ...$attributes]);

        foreach ($metals as $metal) {
            $product = Product::create(['name' => $metal, 'slug' => $metal, 'metal_type' => $metal, 'price' => 100, 'rahkaran_part_ref' => 12, 'rahkaran_unit_ref' => 6]);
            $order->items()->create(['product_id' => $product->id, 'product_name' => $metal, 'product_price' => 100, 'quantity' => 1, 'subtotal' => 100]);
        }

        return $order;
    }

    public static function routes(): array
    {
        return [
            'ordinary gold' => [[], 'digipay', 'failed', ['gold'], [6], '1'],
            'paid Digipay' => [[], 'digipay', 'paid', ['gold', 'silver'], [29, 30], '1'],
            'paid Smartis' => [[], 'smartis', 'paid', ['gold', 'silver'], [38, 39], '1'],
            'failed Smartis' => [[], 'smartis', 'failed', ['gold'], [6], '1'],
            'paid wallet' => [[], 'wallet', 'paid', ['gold', 'silver'], [5, 6], '2'],
            'failed wallet' => [[], 'wallet', 'failed', ['gold'], [6], '1'],
            'Tapsi takes precedence' => [['tapsi_order_id' => 'T-1', 'status' => 'completed', 'discount_amount' => 10], 'wallet', 'paid', ['gold', 'silver'], [31, 32], '1'],
        ];
    }

    #[DataProvider('routes')]
    public function test_order_is_split_by_sales_channel_and_metal(array $attributes, string $gatewayName, string $paymentStatus, array $metals, array $areas, string $salesType): void
    {
        $this->enableInvoicing();
        $order = $this->order($attributes, $metals);
        $order->payments()->create(['transaction_id' => 'test-digipay', 'gateway' => $gatewayName, 'status' => $paymentStatus, 'amount' => 200]);
        $gateway = $this->mock(RahkaranService::class);
        $gateway->shouldReceive('isConfigured')->andReturnTrue();
        foreach ($areas as $area) {
            $gateway->shouldReceive('placeInvoice')->once()->withArgs(fn (array $invoice): bool => $invoice['salesAreaId'] === (string) $area
                && $invoice['items'][0]['description'] === (in_array($area, [5, 30, 32, 38], true) ? 'silver' : 'gold')
                && count($invoice['items']) === 1 && $invoice['items'][0]['salesAreaId'] === (string) $area
                && $invoice['netPrice'] === '100' && $invoice['salesTypeId'] === $salesType)->andReturn(1000 + $area);
        }

        $result = app(RahkaranInvoiceService::class)->syncOrder($order);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame($areas, $order->rahkaranInvoices()->orderBy('sales_area_id')->pluck('sales_area_id')->all());
    }

    public function test_retry_only_sends_the_rejected_invoice_and_keeps_the_original_payload(): void
    {
        $this->enableInvoicing();
        $order = $this->order();
        $gateway = $this->mock(RahkaranService::class);
        $gateway->shouldReceive('isConfigured')->andReturnTrue();
        $gateway->shouldReceive('placeInvoice')->once()->withArgs(fn ($payload) => $payload['salesAreaId'] === '5')->andReturn(105);
        $gold = fn ($payload) => $payload['salesAreaId'] === '6' && $payload['netPrice'] === '100';
        $gateway->shouldReceive('placeInvoice')->once()->withArgs($gold)
            ->andThrow(new RequestException(new Response(new \GuzzleHttp\Psr7\Response(422))));
        $gateway->shouldReceive('placeInvoice')->once()->withArgs($gold)->andReturn(106);

        $service = app(RahkaranInvoiceService::class);
        $this->assertFalse($service->syncOrder($order)['success']);
        $this->assertDatabaseHas('rahkaran_invoices', ['sales_area_id' => 5, 'remote_id' => 105]);
        $order->items()->update(['subtotal' => 500]);
        $this->assertTrue($service->syncOrder($order)['success']);
        $this->assertSame('success', $order->refresh()->rahkaran_sync_status);
    }

    public function test_retry_after_serial_rejection_uses_the_existing_invoice_and_group_row(): void
    {
        $this->enableInvoicing();
        config(['rahkaran.tracking_factor_ref' => 9]);
        $order = $this->order();
        $silver = $order->items()->where('product_name', 'silver')->firstOrFail();
        RahkaranSerial::create([
            'product_id' => $silver->product_id,
            'order_item_id' => $silver->id,
            'serial' => 'silver-serial',
            'status' => 'reserved',
        ]);
        $gateway = $this->mock(RahkaranService::class);
        $gateway->shouldReceive('isConfigured')->andReturnTrue();
        $gateway->shouldReceive('placeInvoice')->once()->withArgs(fn ($payload) => $payload['salesAreaId'] === '5')->andReturn(105);
        $gateway->shouldReceive('placeInvoice')->once()->withArgs(fn ($payload) => $payload['salesAreaId'] === '6')->andReturn(106);
        $factors = fn (int $id, array $factors): bool => $id === 105
            && count($factors) === 1
            && $factors[0]['_MasterRef'] === 105
            && $factors[0]['Radif'] === 1
            && $factors[0]['AmelKontrolMojodi'] === 'silver-serial';
        $gateway->shouldReceive('addInvoiceTrackingFactors')->once()->withArgs($factors)
            ->andThrow(new RequestException(new Response(new \GuzzleHttp\Psr7\Response(422))));
        $gateway->shouldReceive('addInvoiceTrackingFactors')->once()->withArgs($factors)->andReturnNull();

        $service = app(RahkaranInvoiceService::class);
        $this->assertFalse($service->syncOrder($order)['success']);
        $this->assertDatabaseHas('rahkaran_invoices', ['sales_area_id' => 5, 'remote_id' => 105, 'serial_status' => 'failed']);
        $this->assertTrue($service->syncOrder($order)['success']);
    }

    public function test_unknown_submission_result_is_not_retried(): void
    {
        $this->enableInvoicing();
        $order = $this->order();
        $gateway = $this->mock(RahkaranService::class);
        $gateway->shouldReceive('isConfigured')->andReturnTrue();
        $gateway->shouldReceive('placeInvoice')->once()->andThrow(new ConnectionException('Connection lost'));

        $service = app(RahkaranInvoiceService::class);
        $this->assertFalse($service->syncOrder($order)['success']);
        $this->assertFalse($service->syncOrder($order)['success']);
        $this->assertDatabaseHas('rahkaran_invoices', ['order_id' => $order->id, 'sales_area_id' => 5, 'status' => 'reconciliation']);
    }

    public function test_legacy_invoice_and_unapproved_services_never_trigger_submission(): void
    {
        $this->enableInvoicing();
        $legacy = $this->order(['rahkaran_invoice_id' => 123], []);
        $withDiscount = $this->order(['discount_amount' => 10]);
        $gateway = $this->mock(RahkaranService::class);
        $gateway->shouldReceive('isConfigured')->andReturnTrue();
        $gateway->shouldNotReceive('placeInvoice');
        $service = app(RahkaranInvoiceService::class);

        $this->assertTrue($service->syncOrder($legacy)['success']);
        $this->assertFalse($service->syncOrder($withDiscount)['success']);
        $this->assertDatabaseCount('rahkaran_invoices', 0);
    }

    public function test_completed_tapsi_import_dispatches_after_items_are_saved(): void
    {
        $this->enableInvoicing();
        Bus::fake([SyncRahkaranInvoice::class]);

        $order = app(TapsiOrderProjector::class)->apply(
            ['id' => 'T-100', 'stateCode' => '9', 'finalPrice' => 100],
            ['data' => ['items' => [['orderItemId' => 'item-1', 'name' => 'Gold', 'price' => 100]]]],
        );

        $this->assertSame('completed', $order->status);
        $this->assertSame(1, $order->items()->count());
        Bus::assertDispatched(SyncRahkaranInvoice::class, fn ($job) => $job->orderId === $order->id && $job->afterCommit);
    }
}
