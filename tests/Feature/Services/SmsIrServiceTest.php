<?php

namespace Tests\Feature\Services;

use App\Services\SmsIrService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsIrServiceTest extends TestCase
{
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

    public function test_template_send_preserves_existing_success_behavior_for_http_success_response(): void
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

        $this->assertTrue($service->sendVerificationCode('09120000000', '12345'));
        $this->assertTrue($service->recordedLogs[0]['success']);
        $this->assertSame(13, $service->recordedLogs[0]['error_code']);
        $this->assertNull($service->recordedLogs[0]['error_message']);
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
}
