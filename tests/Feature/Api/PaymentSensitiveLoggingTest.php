<?php

namespace Tests\Feature\Api;

use App\Payment\Drivers\Kamanlend\Kamanlend;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Modules\Order\Models\Order;
use Shetabit\Multipay\Invoice;
use Tests\TestCase;

class PaymentSensitiveLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_does_not_log_supplied_tokens_or_customer_data(): void
    {
        $order = Order::factory()->create();
        $logged = [];
        $channel = Mockery::mock();
        $channel->shouldReceive('info', 'warning')->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
            $logged[] = [$message, $context];
        });
        Log::shouldReceive('channel')->with('payment')->andReturn($channel);

        $response = $this->post('/api/payment/callback/'.$order->id.'/parsian', [
            'Token' => 'secret-callback-token',
            'nationalCode' => 'secret-national-code',
            'phone' => 'secret-phone',
        ]);

        $response->assertRedirect();
        $this->assertCount(2, $logged);
        $this->assertSame((string) $order->id, $logged[0][1]['order_id']);
        $this->assertSame('parsian', $logged[0][1]['gateway']);
        $this->assertStringNotContainsString('secret-callback-token', json_encode($logged));
        $this->assertStringNotContainsString('secret-national-code', json_encode($logged));
        $this->assertStringNotContainsString('secret-phone', json_encode($logged));
    }

    public function test_kamanlend_purchase_keeps_provider_response_but_redacts_logs(): void
    {
        $logged = [];
        $channel = Mockery::mock();
        $channel->shouldReceive('info')->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
            $logged[] = [$message, $context];
        });
        Log::shouldReceive('channel')->with('payment')->andReturn($channel);

        $handler = new MockHandler([
            new Response(200, [], json_encode([
                'success' => true,
                'result' => ['token' => 'secret-provider-token', 'gatewayUrl' => 'https://gateway.example/pay'],
            ])),
        ]);
        $invoice = (new Invoice)->amount(100000)->detail([
            'nationalCode' => 'secret-national-code',
            'description' => 'Test order',
        ]);
        $driver = new Kamanlend($invoice, [
            'apiRegisterPaymentUrl' => 'https://gateway.example/register',
            'apiGetPaymentStateUrl' => 'https://gateway.example/state',
            'terminalCode' => 'terminal-code',
            'terminalSecret' => 'secret-merchant-credential',
            'callbackUrl' => 'https://shop.example/callback',
        ]);
        (new \ReflectionProperty(Kamanlend::class, 'client'))->setValue($driver, new Client(['handler' => $handler]));

        $this->assertSame('secret-provider-token', $driver->purchase());
        $this->assertSame(0, $handler->count());
        $this->assertCount(3, $logged);
        $this->assertSame('https://gateway.example/register', $logged[0][1]['url']);
        $this->assertStringNotContainsString('secret-merchant-credential', json_encode($logged));
        $this->assertStringNotContainsString('secret-national-code', json_encode($logged));
        $this->assertStringNotContainsString('secret-provider-token', json_encode($logged));
    }

    public function test_unknown_bare_callback_token_is_not_logged(): void
    {
        $logged = [];
        $channel = Mockery::mock();
        $channel->shouldReceive('info', 'warning')->andReturnUsing(function (string $message, array $context = []) use (&$logged): void {
            $logged[] = [$message, $context];
        });
        Log::shouldReceive('channel')->with('payment')->andReturn($channel);

        $response = $this->get('/api/payment/callback?tv=secret-provider-token&phone=secret-phone');

        $response->assertRedirect();
        $this->assertCount(2, $logged);
        $this->assertStringNotContainsString('secret-provider-token', json_encode($logged));
        $this->assertStringNotContainsString('secret-phone', json_encode($logged));
    }
}
