<?php

namespace Tests\Feature\Services;

use App\Services\DigipayPaymentVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Tests\TestCase;

class DigipayPaymentVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_with_expired_verification_deadline_moves_to_needs_review(): void
    {
        $this->travelTo(now()->startOfSecond());

        $order = Order::factory()->create([
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);
        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'digipay-expired-check',
            'provider_id' => 'provider-expired-check',
            'amount' => $order->total_amount,
            'payment_method' => 'online',
            'gateway' => 'digipay',
            'status' => 'verification_pending',
            'verification_context' => [
                'type' => '11',
                'trackingCode' => 'track-expired',
                'providerId' => 'provider-expired-check',
            ],
            'verification_started_at' => now()->subMinutes(5),
            'verification_deadline_at' => now()->subSecond(),
            'verification_attempts' => 5,
        ]);

        app(DigipayPaymentVerificationService::class)->verify($payment);

        $this->assertSame('needs_review', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
    }
}
