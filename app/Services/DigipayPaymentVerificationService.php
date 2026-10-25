<?php

namespace App\Services;

use App\Exceptions\DigipayVerificationPendingException;
use App\Jobs\VerifyDigipayPayment;
use App\Models\Cart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Shetabit\Multipay\Contracts\ReceiptInterface;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Payment as ShetabitPayment;
use Shetabit\Multipay\Request as MultipayRequest;
use Throwable;

class DigipayPaymentVerificationService
{
    private const RETRY_DELAYS = [5, 15, 30, 60, 120];

    public function verify(Payment $payment, bool $restart = false): void
    {
        $payment = Payment::withoutTenantScope()->find($payment->id);

        if (! $payment || $payment->gateway !== 'digipay' || in_array($payment->status, ['paid', 'failed'], true)) {
            return;
        }

        if ($restart) {
            $payment->forceFill([
                'status' => 'verification_pending',
                'verification_started_at' => now(),
                'verification_deadline_at' => now()->addMinutes(5),
                'verification_attempts' => 0,
                'next_verification_at' => null,
            ])->save();
        }

        if (! $payment->verification_context || ! $payment->provider_id) {
            $this->markNeedsReview($payment, 'اطلاعات لازم برای تطبیق پرداخت با دیجی‌پی موجود نیست.');

            return;
        }

        if (! $payment->verification_deadline_at) {
            $payment->forceFill([
                'verification_started_at' => now(),
                'verification_deadline_at' => now()->addMinutes(5),
            ])->save();
        }

        if ($payment->verification_deadline_at->isPast()) {
            $this->markNeedsReview($payment, 'پس از پایان مهلت تلاش مجدد، نتیجه پرداخت از دیجی‌پی دریافت نشد.');

            return;
        }

        $startedAt = microtime(true);
        $attemptNumber = (int) $payment->verification_attempts + 1;

        try {
            $receipt = $this->verifyAtDigipay($payment);
        } catch (DigipayVerificationPendingException $exception) {
            $this->scheduleRetry($payment, $exception->getMessage(), $attemptNumber, $startedAt, $exception->httpStatus, $exception->providerStatus);

            return;
        } catch (InvalidPaymentException $exception) {
            $this->markFailed($payment, $exception->getMessage());
            Log::channel('payment')->warning('Digipay verification definitively failed', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'attempt' => $attemptNumber,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'provider_status' => $exception->getCode(),
                'message' => $exception->getMessage(),
            ]);

            return;
        } catch (Throwable $exception) {
            $this->scheduleRetry($payment, $exception->getMessage(), $attemptNumber, $startedAt);

            return;
        }

        $this->markPaid($payment, $receipt);
        Log::channel('payment')->info('Digipay verification succeeded', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'provider_status' => 0,
            'http_status' => 200,
            'attempt' => $attemptNumber,
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);
    }

    private function verifyAtDigipay(Payment $payment): ReceiptInterface
    {
        $context = $payment->verification_context;
        MultipayRequest::overwrite('input', static fn (string $name) => $context[$name] ?? null);

        try {
            return (new ShetabitPayment(config('payment')))
                ->via('digipay')
                ->amount((int) $payment->amount)
                ->transactionId($payment->transaction_id)
                ->verify();
        } finally {
            MultipayRequest::overwrite('input', static fn (string $name) => $_REQUEST[$name] ?? null);
        }
    }

    private function scheduleRetry(Payment $payment, string $message, int $attemptNumber, float $startedAt, ?int $httpStatus = null, ?int $providerStatus = null): void
    {
        $payment->refresh();
        if (in_array($payment->status, ['paid', 'failed'], true)) {
            return;
        }

        $delay = self::RETRY_DELAYS[$attemptNumber - 1] ?? null;
        $remainingSeconds = now()->diffInSeconds($payment->verification_deadline_at, false);
        if ($remainingSeconds <= 0) {
            $this->markNeedsReview($payment, $message);

            return;
        }

        $delay = $delay ?? $remainingSeconds;
        $payment->forceFill([
            'status' => 'verification_pending',
            'verification_attempts' => $attemptNumber,
            'next_verification_at' => now()->addSeconds(min($delay, $remainingSeconds)),
            'gateway_response' => [
                'verification' => [
                    'last_error' => mb_substr($message, 0, 500),
                    'http_status' => $httpStatus,
                    'attempt' => $attemptNumber,
                ],
            ],
        ])->save();

        Log::channel('payment')->warning('Digipay verification remains unresolved', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'provider_status' => $providerStatus,
            'http_status' => $httpStatus,
            'attempt' => $attemptNumber,
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        VerifyDigipayPayment::dispatch($payment->id)->delay(now()->addSeconds(min($delay, $remainingSeconds)));
    }

    public function markQueueFailure(Payment $payment, string $message): void
    {
        $this->markNeedsReview($payment, $message);
    }

    private function markNeedsReview(Payment $payment, string $message): void
    {
        $payment->forceFill([
            'status' => 'needs_review',
            'next_verification_at' => null,
            'gateway_response' => ['verification' => ['last_error' => mb_substr($message, 0, 500)]],
        ])->save();

        Log::channel('payment')->critical('Digipay payment requires manual review', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
        ]);
    }

    private function markFailed(Payment $payment, string $message): void
    {
        DB::transaction(function () use ($payment, $message): void {
            $lockedPayment = Payment::withoutTenantScope()->lockForUpdate()->find($payment->id);
            if (! $lockedPayment || in_array($lockedPayment->status, ['paid', 'failed'], true)) {
                return;
            }

            $lockedPayment->forceFill([
                'status' => 'failed',
                'next_verification_at' => null,
                'gateway_response' => ['verification' => ['error' => mb_substr($message, 0, 500)]],
            ])->save();

            $order = Order::withoutTenantScope()->lockForUpdate()->find($lockedPayment->order_id);
            if (! $order || $order->payment_status === 'paid') {
                return;
            }

            $hasUnresolvedPayment = Payment::withoutTenantScope()
                ->where('order_id', $order->id)
                ->whereIn('status', ['pending', 'processing', 'verification_pending', 'needs_review'])
                ->exists();

            if ($hasUnresolvedPayment) {
                $order->update(['payment_status' => 'pending']);
            } else {
                $order->update(['payment_status' => 'failed']);
                app(DiscountCodeService::class)->releaseUsage($order->id);
            }

            $order->addNote("پرداخت دیجی‌پی ناموفق بود: {$message}", 'payment');
        });
    }

    private function markPaid(Payment $payment, ReceiptInterface $receipt): void
    {
        try {
            DB::transaction(function () use ($payment, $receipt): void {
                $lockedPayment = Payment::withoutTenantScope()->lockForUpdate()->find($payment->id);
                $order = Order::withoutTenantScope()->lockForUpdate()->find($payment->order_id);

                if (! $lockedPayment || ! $order || $lockedPayment->status === 'paid') {
                    return;
                }

                $lockedPayment->forceFill([
                    'status' => 'paid',
                    'gateway_response' => [
                        'reference_id' => $receipt->getReferenceId(),
                        'details' => $receipt->getDetails(),
                    ],
                    'paid_at' => now(),
                    'next_verification_at' => null,
                ])->save();

                $wasOrderAlreadyPaid = $order->payment_status === 'paid';
                if (! $wasOrderAlreadyPaid) {
                    $order->update(['payment_status' => 'paid', 'status' => 'confirmed']);
                }
                app(DiscountCodeService::class)->redeemUsage($order->id);

                $note = $wasOrderAlreadyPaid
                    ? 'یک پرداخت دیجی‌پی دیگر نیز برای این سفارش موفق شد و نیازمند بررسی مالی است. کد رهگیری: '.$receipt->getReferenceId()
                    : 'پرداخت دیجی‌پی با موفقیت تایید شد. کد رهگیری: '.$receipt->getReferenceId();
                $order->addNote($note, 'payment', true);
                Cart::where('user_id', $order->user_id)->delete();
            });
        } catch (Throwable $exception) {
            Log::channel('payment')->critical('Digipay verified but order post-processing failed; preserving paid state', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'message' => $exception->getMessage(),
            ]);

            try {
                $lockedPayment = Payment::withoutTenantScope()->find($payment->id);
                $order = Order::withoutTenantScope()->find($payment->order_id);

                if ($lockedPayment && $lockedPayment->status !== 'paid') {
                    $lockedPayment->forceFill([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'gateway_response' => [
                            'reference_id' => $receipt->getReferenceId(),
                            'details' => $receipt->getDetails(),
                        ],
                    ])->saveQuietly();
                }

                if ($order && $order->payment_status !== 'paid') {
                    $order->forceFill(['payment_status' => 'paid', 'status' => 'confirmed'])->saveQuietly();
                }

                app(DiscountCodeService::class)->redeemUsage($payment->order_id);
                Cart::where('user_id', $order?->user_id)->delete();
            } catch (Throwable $fallbackException) {
                Log::channel('payment')->critical('Unable to preserve verified DigiPay payment state', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'message' => $fallbackException->getMessage(),
                ]);
            }
        }
    }
}
