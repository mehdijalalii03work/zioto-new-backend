<?php

namespace App\Jobs;

use App\Services\DigipayPaymentVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Modules\Payment\Models\Payment;
use Throwable;

class VerifyDigipayPayment implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(public int $paymentId, public bool $restart = false) {}

    public function uniqueId(): string
    {
        return (string) $this->paymentId;
    }

    /** @return array<int, WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('digipay-payment-'.$this->paymentId))->expireAfter(60)->releaseAfter(5)];
    }

    public function handle(DigipayPaymentVerificationService $verificationService): void
    {
        $payment = Payment::withoutTenantScope()->find($this->paymentId);
        if (! $payment) {
            return;
        }

        $verificationService->verify($payment, $this->restart);
    }

    public function failed(?Throwable $exception): void
    {
        $payment = Payment::withoutTenantScope()->find($this->paymentId);
        if (! $payment || in_array($payment->status, ['paid', 'failed'], true)) {
            return;
        }

        app(DigipayPaymentVerificationService::class)->markQueueFailure($payment, $exception?->getMessage() ?? 'خطای صف بررسی پرداخت');
    }
}
