<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendWelcomeSms;
use App\Services\SmsIrService;
use RuntimeException;
use Tests\TestCase;

class SendWelcomeSmsTest extends TestCase
{
    public function test_failed_sms_send_throws_so_the_queue_can_retry(): void
    {
        $sms = $this->mock(SmsIrService::class, function ($mock): void {
            $mock->shouldReceive('sendWelcomeMessage')
                ->with('09120000000', 'علی تست')
                ->once()
                ->andReturn(false);
        });

        $this->expectException(RuntimeException::class);

        (new SendWelcomeSms('09120000000', 'علی تست'))->handle($sms);
    }
}
