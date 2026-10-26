<?php

namespace App\Jobs;

use App\Enums\OrderSmsType;
use App\Services\OrderSmsNotificationService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class SendOrderSms implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public int $uniqueFor = 86400;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public int $orderId, public OrderSmsType $type)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->orderId.':'.$this->type->value;
    }

    public function handle(OrderSmsNotificationService $notifications): void
    {
        if (! $notifications->send($this->orderId, $this->type)) {
            throw new RuntimeException('SMS.ir failed to send order notification.');
        }
    }
}
