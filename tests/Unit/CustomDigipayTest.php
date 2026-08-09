<?php

namespace Tests\Unit;

use App\Payment\Drivers\CustomDigipay;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Invoice;
use Shetabit\Multipay\Request;
use Tests\TestCase;

class CustomDigipayTest extends TestCase
{
    private array $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $config = require dirname(__DIR__, 2).'/config/payment.php';
        $this->settings = $config['drivers']['digipay'];
    }

    protected function tearDown(): void
    {
        Request::overwrite('input', null);
        Mockery::close();
        parent::tearDown();
    }

    private function makeDriver(Invoice $invoice, array $responses): array
    {
        $handler = new MockHandler($responses);
        $requests = new \ArrayObject;

        $countingHandler = function ($request, $options) use ($handler, $requests) {
            $requests[] = $request;

            return $handler($request, $options);
        };

        $client = new Client(['handler' => $countingHandler]);

        $driver = Mockery::mock(CustomDigipay::class)->makePartial();

        (new \ReflectionProperty(CustomDigipay::class, 'client'))->setValue($driver, $client);
        (new \ReflectionProperty(CustomDigipay::class, 'settings'))->setValue($driver, (object) $this->settings);
        (new \ReflectionProperty(CustomDigipay::class, 'invoice'))->setValue($driver, $invoice);
        (new \ReflectionProperty(CustomDigipay::class, 'oauthToken'))->setValue($driver, 'test-token');

        return [$driver, $requests];
    }

    private function mockCallbackInput(array $inputs): void
    {
        Request::overwrite('input', fn (string $name) => $inputs[$name] ?? null);
    }

    public function test_verify_sends_tracking_code_and_provider_id_in_json_body(): void
    {
        $invoice = (new Invoice)->amount(100000);

        $response = new Response(200, [], json_encode([
            'result' => ['status' => 0],
            'trackingCode' => 'TC-123',
        ]));

        [$driver, $requests] = $this->makeDriver($invoice, [$response]);

        $this->mockCallbackInput([
            'type' => '11',
            'trackingCode' => 'TC-123',
            'providerId' => 'provider-456',
        ]);

        $receipt = $driver->verify();

        $this->assertSame('TC-123', $receipt->getReferenceId());

        $sentRequest = $requests[count($requests) - 1];
        $sentUrl = (string) $sentRequest->getUri();

        // body-based verify: trackingCode must NOT be in the URL path
        $this->assertSame('https://api.mydigipay.com/digipay/api/purchases/verify?type=11', $sentUrl);

        $sentBody = json_decode($sentRequest->getBody()->getContents(), true);

        $this->assertSame('TC-123', $sentBody['trackingCode']);
        $this->assertSame('provider-456', $sentBody['providerId']);
    }

    public function test_verify_falls_back_to_invoice_uuid_when_provider_id_missing(): void
    {
        $invoice = (new Invoice)->amount(100000)->detail(['phone' => '09123456789']);

        $response = new Response(200, [], json_encode([
            'result' => ['status' => 0],
            'trackingCode' => 'TC-123',
        ]));

        [$driver, $requests] = $this->makeDriver($invoice, [$response]);

        $this->mockCallbackInput([
            'type' => '11',
            'trackingCode' => 'TC-123',
            'providerId' => null,
        ]);

        $driver->verify();

        $sentBody = json_decode($requests[0]->getBody()->getContents(), true);

        $this->assertSame($invoice->getUuid(), $sentBody['providerId']);
    }

    public function test_verify_throws_when_status_is_not_zero(): void
    {
        $invoice = (new Invoice)->amount(100000);

        $response = new Response(200, [], json_encode([
            'result' => ['status' => 1001, 'message' => 'خطا در تایید تراکنش'],
        ]));

        [$driver, $handler] = $this->makeDriver($invoice, [$response]);

        $this->mockCallbackInput([
            'type' => '11',
            'trackingCode' => 'TC-123',
            'providerId' => 'provider-456',
        ]);

        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('خطا در تایید تراکنش');

        $driver->verify();
    }

    public function test_verify_throws_on_non_200_status(): void
    {
        $invoice = (new Invoice)->amount(100000);

        $response = new Response(500, [], json_encode([
            'result' => ['message' => 'خطای داخلی سرور'],
        ]));

        [$driver, $handler] = $this->makeDriver($invoice, [$response]);

        $this->mockCallbackInput([
            'type' => '11',
            'trackingCode' => 'TC-123',
            'providerId' => 'provider-456',
        ]);

        $this->expectException(InvalidPaymentException::class);

        $driver->verify();
    }

    public function test_verify_throws_when_result_status_is_missing(): void
    {
        $invoice = (new Invoice)->amount(100000);

        $response = new Response(200, [], json_encode([
            'trackingCode' => 'TC-123',
        ]));

        [$driver, $handler] = $this->makeDriver($invoice, [$response]);

        $this->mockCallbackInput([
            'type' => '11',
            'trackingCode' => 'TC-123',
            'providerId' => 'provider-456',
        ]);

        $this->expectException(InvalidPaymentException::class);

        $driver->verify();
    }

    public function test_verify_retries_on_pending_status_until_success(): void
    {
        $invoice = (new Invoice)->amount(100000);

        $responses = [
            new Response(200, [], json_encode(['result' => ['status' => 9011]])),
            new Response(200, [], json_encode([
                'result' => ['status' => 0],
                'trackingCode' => 'TC-123',
            ])),
        ];

        [$driver, $requests] = $this->makeDriver($invoice, $responses);

        (new \ReflectionProperty(CustomDigipay::class, 'verifyRetryDelay'))->setValue($driver, 0);

        $this->mockCallbackInput([
            'type' => '11',
            'trackingCode' => 'TC-123',
            'providerId' => 'provider-456',
        ]);

        $receipt = $driver->verify();

        $this->assertSame('TC-123', $receipt->getReferenceId());
        $this->assertCount(2, $requests);
    }

    public function test_verify_throws_after_max_pending_retries(): void
    {
        $invoice = (new Invoice)->amount(100000);

        $responses = array_map(
            fn () => new Response(200, [], json_encode([
                'result' => ['status' => 9011, 'message' => 'در انتظار پرداخت'],
            ])),
            range(1, 4)
        );

        [$driver, $handler] = $this->makeDriver($invoice, $responses);

        (new \ReflectionProperty(CustomDigipay::class, 'verifyRetryDelay'))->setValue($driver, 0);

        $this->mockCallbackInput([
            'type' => '11',
            'trackingCode' => 'TC-123',
            'providerId' => 'provider-456',
        ]);

        $this->expectException(InvalidPaymentException::class);
        $this->expectExceptionMessage('در انتظار پرداخت');

        $driver->verify();
    }
}
