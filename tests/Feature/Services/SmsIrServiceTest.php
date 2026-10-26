<?php

namespace Tests\Feature\Services;

use App\Services\SmsIrService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SmsIrServiceTest extends TestCase
{
    public function test_pickup_order_confirmation_sends_the_pickup_template_parameters(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.sms.ir/v1/send/verify' => Http::response(['status' => 1], 200)]);
        $service = $this->smsServiceWithoutMongoLogs();

        $this->assertTrue($service->sendPickupOrderConfirmation('09120000000', 'Sara', '00123', 123456, '1404/01/01'));

        Http::assertSent(fn (Request $request): bool => $request['templateId'] === 196926
            && $request['parameters'] === [
                ['name' => 'Name', 'value' => 'Sara'],
                ['name' => 'CODE', 'value' => '00123'],
                ['name' => 'PRICE', 'value' => '123456'],
                ['name' => 'DATE', 'value' => '1404/01/01'],
            ]
        );
    }

    public function test_shipping_order_confirmation_sends_the_shipping_template_parameters(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.sms.ir/v1/send/verify' => Http::response(['status' => 1], 200)]);
        $service = $this->smsServiceWithoutMongoLogs();

        $this->assertTrue($service->sendShippingOrderConfirmation('09120000000', 'Sara', '00124', 765432, '1404/01/01'));

        Http::assertSent(fn (Request $request): bool => $request['templateId'] === 832720
            && $request['parameters'] === [
                ['name' => 'Name', 'value' => 'Sara'],
                ['name' => 'CODE', 'value' => '00124'],
                ['name' => 'AMOUNT', 'value' => '765432'],
                ['name' => 'TIME', 'value' => '1404/01/01'],
            ]
        );
    }

    public function test_order_received_health_message_sends_name_to_the_requested_template(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.sms.ir/v1/send/verify' => Http::response(['status' => 1], 200)]);
        $service = $this->smsServiceWithoutMongoLogs();

        $this->assertTrue($service->sendOrderReceivedHealthMessage('09120000000', 'Sara'));

        Http::assertSent(fn (Request $request): bool => $request['templateId'] === 990301
            && $request['parameters'] === [['name' => 'NAME', 'value' => 'Sara']]
        );
    }

    public function test_welcome_message_uses_the_requested_sms_ir_template_and_name_parameter(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.sms.ir/v1/send/verify' => Http::response(['status' => 1, 'data' => ['messageId' => 'welcome-1']], 200)]);

        $service = new class extends SmsIrService
        {
            public array $recordedLogs = [];

            protected function storeApiLog(array $attributes): void
            {
                $this->recordedLogs[] = $attributes;
            }
        };

        $this->assertTrue($service->sendWelcomeMessage('09120000000', 'علی تست'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.sms.ir/v1/send/verify'
            && $request['mobile'] === '09120000000'
            && $request['templateId'] === 331254
            && $request['parameters'] === [['name' => 'NAME', 'value' => 'علی تست']]
        );
    }

    public function test_welcome_message_returns_false_for_http_and_connection_failures(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.sms.ir/v1/send/verify' => Http::sequence()
                ->push(['status' => 1, 'message' => 'Unavailable'], 503)
                ->pushFailedConnection(),
        ]);

        $service = new class extends SmsIrService
        {
            public array $recordedLogs = [];

            protected function storeApiLog(array $attributes): void
            {
                $this->recordedLogs[] = $attributes;
            }
        };

        $this->assertFalse($service->sendWelcomeMessage('09120000000', 'علی تست'));
        $this->assertFalse($service->sendWelcomeMessage('09120000000', 'علی تست'));
        $this->assertFalse($service->recordedLogs[0]['success']);
        $this->assertSame(503, $service->recordedLogs[0]['response_status']);
        $this->assertFalse($service->recordedLogs[1]['success']);
        $this->assertNull($service->recordedLogs[1]['response_status']);
    }

    public function test_welcome_message_returns_false_for_sms_ir_application_errors(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.sms.ir/v1/send/verify' => Http::response([
            'status' => 2,
            'code' => 13,
            'message' => 'Invalid template',
        ], 200)]);

        $service = new class extends SmsIrService
        {
            public array $recordedLogs = [];

            protected function storeApiLog(array $attributes): void
            {
                $this->recordedLogs[] = $attributes;
            }
        };

        $this->assertFalse($service->sendWelcomeMessage('09120000000', 'علی تست'));
        $this->assertFalse($service->recordedLogs[0]['success']);
        $this->assertSame(13, $service->recordedLogs[0]['error_code']);
    }

    public function test_log_failure_does_not_turn_an_accepted_welcome_sms_into_a_retry(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.sms.ir/v1/send/verify' => Http::response(['status' => 1], 200)]);
        Log::shouldReceive('warning')->once()->andThrow(new \RuntimeException('Logger unavailable'));

        $service = new class extends SmsIrService
        {
            protected function storeApiLog(array $attributes): void
            {
                throw new \RuntimeException('MongoDB unavailable');
            }
        };

        $this->assertTrue($service->sendWelcomeMessage('09120000000', 'علی تست'));
    }

    public function test_template_send_records_masked_request_and_successful_response(): void
    {
        config()->set('sms.smsir.template_id', 123);
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 1, 'data' => ['messageId' => 'abc']], 200)]);

        $service = new class extends SmsIrService
        {
            public array $recordedLogs = [];

            protected function storeApiLog(array $attributes): void
            {
                $this->recordedLogs[] = $attributes;
            }
        };

        $this->assertTrue($service->sendVerificationCode('09120000000', '12345'));

        Http::assertSent(fn (Request $request): bool => $request['parameters'][0]['value'] === '12345');
        $this->assertSame('*****', $service->recordedLogs[0]['request_body']['parameters'][0]['value']);
        $this->assertSame(['status' => 1, 'data' => ['messageId' => 'abc']], $service->recordedLogs[0]['response_body']);
        $this->assertTrue($service->recordedLogs[0]['success']);
        $this->assertSame(200, $service->recordedLogs[0]['response_status']);
    }

    public function test_template_send_treats_sms_ir_application_errors_as_failed_sends(): void
    {
        config()->set('sms.smsir.template_id', 123);
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 2, 'code' => 13, 'message' => 'Invalid template'], 200)]);

        $service = new class extends SmsIrService
        {
            public array $recordedLogs = [];

            protected function storeApiLog(array $attributes): void
            {
                $this->recordedLogs[] = $attributes;
            }
        };

        $this->assertFalse($service->sendVerificationCode('09120000000', '12345'));
        $this->assertFalse($service->recordedLogs[0]['success']);
        $this->assertSame(13, $service->recordedLogs[0]['error_code']);
        $this->assertSame('Invalid template', $service->recordedLogs[0]['error_message']);
    }

    public function test_template_send_still_returns_provider_failure_when_error_logging_fails(): void
    {
        config()->set('sms.smsir.template_id', 123);
        Http::preventStrayRequests();
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 2, 'code' => 13, 'message' => 'Invalid template'], 200)]);
        Log::shouldReceive('error')->once()->andThrow(new \RuntimeException('Logger unavailable'));

        $this->assertFalse($this->smsServiceWithoutMongoLogs()->sendVerificationCode('09120000000', '12345'));
    }

    public function test_log_storage_failure_does_not_change_successful_send_result(): void
    {
        config()->set('sms.smsir.template_id', 123);
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 1], 200)]);

        $service = new class extends SmsIrService
        {
            protected function storeApiLog(array $attributes): void
            {
                throw new \RuntimeException('MongoDB is unavailable');
            }
        };

        $this->assertTrue($service->sendVerificationCode('09120000000', '12345'));
    }

    public function test_http_error_response_still_returns_false(): void
    {
        config()->set('sms.smsir.template_id', 123);
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 1], 500)]);

        $service = new class extends SmsIrService
        {
            public array $recordedLogs = [];

            protected function storeApiLog(array $attributes): void
            {
                $this->recordedLogs[] = $attributes;
            }
        };

        $this->assertFalse($service->sendVerificationCode('09120000000', '12345'));
        $this->assertFalse($service->recordedLogs[0]['success']);
    }

    private function smsServiceWithoutMongoLogs(): SmsIrService
    {
        return new class extends SmsIrService
        {
            protected function storeApiLog(array $attributes): void {}
        };
    }
}
