<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Payment as ShetabitPayment;
use Shetabit\Multipay\Receipt;
use Tests\TestCase;

class PaymentControllerCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeOrderWithPendingPayment(string $gateway, int $amount = 100000): array
    {
        $order = Order::factory()->create([
            'total_amount' => $amount,
            'payment_status' => null,
            'status' => 'awaiting_payment',
        ]);

        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'test-txn-'.uniqid(),
            'amount' => $amount,
            'payment_method' => 'online',
            'gateway' => $gateway,
            'status' => 'pending',
            'description' => 'در انتظار پرداخت',
        ]);

        return [$order, $payment];
    }

    public function test_parsian_non_zero_status_marks_failed_without_calling_verify(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('parsian');

        $response = $this->post("/api/payment/callback/{$order->id}/parsian", [
            'Token' => $payment->transaction_id,
            'OrderId' => '123456',
            'status' => '-138',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/payment-failed', $response->headers->get('Location'));

        $payment->refresh();
        $order->refresh();

        $this->assertSame('failed', $payment->status);
        $this->assertSame('failed', $order->payment_status);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_generic_verify_exception_marks_failed_not_pending(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('digipay');

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andThrow(
            new \RuntimeException('Undefined property: stdClass::$Message')
        );

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'result' => 'CANCEL',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/payment-failed', $response->headers->get('Location'));

        $payment->refresh();
        $order->refresh();

        $this->assertSame('failed', $payment->status);
        $this->assertNotSame('pending', $payment->status);
        $this->assertSame('failed', $order->payment_status);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_successful_verify_marks_order_paid(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('digipay');

        $receipt = Mockery::mock(Receipt::class);
        $receipt->shouldReceive('getReferenceId')->andReturn('REF-123');
        $receipt->shouldReceive('getDetails')->andReturn([]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andReturn($receipt);

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'result' => 'SUCCESS',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/confirm?order_id='.$order->id, $response->headers->get('Location'));

        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($payment->paid_at);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function test_post_verify_failure_still_persists_paid_status(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('digipay');

        Order::updated(function () {
            throw new \RuntimeException('Simulated Hesabfa/observer failure (e.g. permission denied on log file)');
        });

        $receipt = Mockery::mock(Receipt::class);
        $receipt->shouldReceive('getReferenceId')->andReturn('REF-456');
        $receipt->shouldReceive('getDetails')->andReturn([]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andReturn($receipt);

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'result' => 'SUCCESS',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/confirm?order_id='.$order->id, $response->headers->get('Location'));

        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status, 'پرداخت واقعاً موفق بوده و نباید failed شود فقط چون observer خطا داده.');
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
    }


    public function test_callback_without_pending_payment_but_already_paid_redirects_to_confirm(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('parsian');
        $payment->update(['status' => 'paid']);

        $response = $this->post("/api/payment/callback/{$order->id}/parsian", [
            'status' => '0',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/confirm?order_id='.$order->id, $response->headers->get('Location'));
    }
}
