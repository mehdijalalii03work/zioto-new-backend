<?php

namespace Tests\Feature\Inventory;

use App\Models\ProductSerial;
use App\Payment\Drivers\CustomDigipay;
use App\Payment\Drivers\Nopay\Nopay;
use App\Services\Inventory\SerialInventoryService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Database\Factories\OrderFactory;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Shetabit\Multipay\Invoice;
use Tests\TestCase;

class GatewayOutcomeSafetyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{Order, Payment} */
    private function order(string $gateway = 'digipay'): array
    {
        $product = Product::factory()->serial('ton')->create(['is_nopay' => true]);
        $order = OrderFactory::new()->create(['platform' => 'main', 'status' => 'pending', 'payment_status' => 'pending', 'serial_inventory_enabled' => true]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'product_price' => 1000000, 'quantity' => 1, 'subtotal' => 1000000, 'inventory_mode' => 'serial', 'serial_tracking_required' => true, 'made_to_order' => false]);
        ProductSerial::create(['product_id' => $product->id, 'serial_code' => 'ton70000001', 'serial_prefix' => 'ton', 'status' => 'available']);
        $payment = Payment::create(['user_id' => $order->user_id, 'order_id' => $order->id, 'platform' => 'main', 'transaction_id' => 'outcome-token', 'amount' => $order->total_amount, 'gateway' => $gateway, 'status' => 'pending']);
        app(SerialInventoryService::class)->reserveForPayment($order->id, 'main', $payment->id);

        return [$order, $payment];
    }

    public function test_real_digipay_http_503_preserves_inventory_and_successful_retry(): void
    {
        [$order, $payment] = $this->order();
        config(['payment.map.digipay' => HttpDigipay::class]);
        HttpDigipay::$responses = [new Response(503, [], '{"result":{"status":9011,"message":"unavailable"}}')];

        $this->post("/api/payment/callback/{$order->id}/digipay", ['Token' => $payment->transaction_id])->assertRedirect();

        $this->assertSame('processing', $payment->fresh()->status);
        $this->assertSame(1, ProductSerial::where('status', 'reserved')->count());
        $this->assertSame(503, $payment->fresh()->gateway_response['http_status']);
        $this->assertSame(9011, $payment->fresh()->gateway_response['provider_code']);
        HttpDigipay::$responses = [new Response(200, [], '{"result":{"status":0},"trackingCode":"RECOVERED"}')];

        $this->post("/api/payment/callback/{$order->id}/digipay", ['Token' => $payment->transaction_id])->assertRedirect();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(1, ProductSerial::where('status', 'allocated')->count());
    }

    public function test_real_digipay_pending_after_retries_remains_retryable(): void
    {
        [$order, $payment] = $this->order();
        config(['payment.map.digipay' => HttpDigipay::class]);
        HttpDigipay::$responses = array_map(fn () => new Response(200, [], '{"result":{"status":9011,"message":"pending"}}'), range(1, 4));

        $this->post("/api/payment/callback/{$order->id}/digipay", ['Token' => $payment->transaction_id])->assertRedirect();

        $this->assertSame('processing', $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->gateway_response['verification_retryable']);
        $this->assertSame(9011, $payment->fresh()->gateway_response['provider_code']);
        $this->assertSame(1, ProductSerial::where('status', 'reserved')->count());
        HttpDigipay::$responses = [new Response(200, [], '{"result":{"status":0},"trackingCode":"PENDING-RECOVERED"}')];

        $this->post("/api/payment/callback/{$order->id}/digipay", ['Token' => $payment->transaction_id])->assertRedirect();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(1, ProductSerial::where('status', 'allocated')->count());
    }

    public function test_real_nopay_malformed_purchase_retains_initiating_claim(): void
    {
        config(['serial_inventory.enabled' => true, 'payment.map.nopay' => HttpNopay::class, 'payment.drivers.nopay.privateKey' => base64_encode(str_repeat('a', 16)), 'payment.drivers.nopay.publicKey' => base64_encode(str_repeat('b', 16))]);
        [$order, $old] = $this->order('nopay');
        $old->update(['status' => 'failed']);
        app(SerialInventoryService::class)->releaseUnpaidHoldings($order->id, 'main');
        HttpNopay::$responses = [new Response(200, [], 'not-json')];

        $this->postJson('/api/payment/init', ['order_id' => $order->id, 'gateway' => 'nopay'])->assertStatus(500);
        $this->travel(21)->minutes();
        app(SerialInventoryService::class)->releaseExpiredReservation($order->id, 'main');

        $this->assertSame(1, Payment::where('status', 'initiating')->count());
        $this->assertSame(1, ProductSerial::where('status', 'reserved')->count());
        $this->assertSame('needs_review', $order->fresh()->serial_fulfillment_status->value);
    }

    public function test_real_nopay_missing_approval_and_explicit_false_remain_uncertain(): void
    {
        config(['payment.map.nopay' => HttpNopay::class, 'payment.drivers.nopay.privateKey' => base64_encode(str_repeat('a', 16)), 'payment.drivers.nopay.publicKey' => base64_encode(str_repeat('b', 16))]);
        [$order, $payment] = $this->order('nopay');
        HttpNopay::$responses = [new Response(200, [], '{"Result":{"entity":{"OrderID":"123"}}}')];

        $this->post("/api/payment/callback/{$order->id}/nopay", ['Token' => $payment->transaction_id])->assertRedirect();

        $this->assertSame('processing', $payment->fresh()->status);
        $this->assertSame(['OrderID' => '123'], $payment->fresh()->gateway_response['provider_response']['Result']['entity']);
        HttpNopay::$responses = [new Response(200, [], '{"Result":{"entity":{"IsApproved":false,"OrderID":"123"}}}')];

        $this->post("/api/payment/callback/{$order->id}/nopay", ['Token' => $payment->transaction_id])->assertRedirect();

        $this->assertFalse($payment->fresh()->gateway_response['provider_response']['Result']['entity']['IsApproved']);
        $this->assertSame('processing', $payment->fresh()->status);
        $this->assertSame(1, ProductSerial::where('status', 'reserved')->count());
    }

    public function test_mismatched_callback_gateway_keeps_attempt_and_hold(): void
    {
        [$order, $payment] = $this->order();

        $this->post("/api/payment/callback/{$order->id}/parsian", ['Token' => $payment->transaction_id, 'status' => '1'])->assertRedirect();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(1, ProductSerial::where('status', 'reserved')->count());
    }

    public function test_forged_parsian_failure_status_cannot_bypass_server_verification(): void
    {
        [$order, $payment] = $this->order('parsian');
        config(['payment.map.parsian' => HttpDigipay::class]);
        HttpDigipay::$responses = [new Response(200, [], '{"result":{"status":0},"trackingCode":"BANK-SUCCESS"}')];

        $this->post("/api/payment/callback/{$order->id}/parsian", ['Token' => $payment->transaction_id, 'status' => '-138'])->assertRedirect();

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(1, ProductSerial::where('status', 'allocated')->count());
    }
}

/** Real Digipay verification; only outbound HTTP and OAuth setup are isolated. */
class HttpDigipay extends CustomDigipay
{
    public static array $responses = [];

    public function __construct(Invoice $invoice, $settings)
    {
        $this->invoice($invoice);
        $this->settings = (object) $settings;
        $this->client = new Client(['handler' => new MockHandler(self::$responses)]);
        $this->oauthToken = 'test-oauth';
        (new \ReflectionProperty(CustomDigipay::class, 'verifyRetryDelay'))->setValue($this, 0);
    }
}

/** Real Nopay purchase/verification with isolated outbound HTTP. */
class HttpNopay extends Nopay
{
    public static array $responses = [];

    public function __construct(Invoice $invoice, $settings)
    {
        parent::__construct($invoice, $settings);
        $this->client = new Client(['handler' => new MockHandler(self::$responses)]);
    }
}
