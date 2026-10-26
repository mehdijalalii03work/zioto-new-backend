<?php

namespace App\Jobs;

use App\Services\SmsIrService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class SendWelcomeSms implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public string $mobile, public string $name)
    {
        $this->afterCommit();
    }

    public function handle(SmsIrService $sms): void
    {
        if (! $sms->sendWelcomeMessage($this->mobile, $this->name)) {
            throw new RuntimeException('SMS.ir failed to send the welcome message.');
        }
    }
}
