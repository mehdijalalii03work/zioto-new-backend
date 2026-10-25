<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\PaymentController;
use App\Jobs\VerifyDigipayPayment;
use App\Services\DiscountCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
            'payment_status' => 'pending',
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

    #[RunInSeparateProcess]
    public function test_generic_verify_exception_on_other_gateway_marks_failed(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('parsian');

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andThrow(
            new \RuntimeException('Undefined property: stdClass::$Message')
        );

        $response = $this->post("/api/payment/callback/{$order->id}/parsian", [
            'status' => '0',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/payment-failed', $response->headers->get('Location'));

        $payment->refresh();
        $order->refresh();

        $this->assertSame('failed', $payment->status);
        $this->assertNotSame('pending', $payment->status);
        $this->assertSame('failed', $order->payment_status);
    }

    #[RunInSeparateProcess]
    public function test_digipay_transient_verify_exception_keeps_payment_pending_and_queues_retry(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('digipay');
        $payment->update(['provider_id' => 'provider-123']);
        Queue::fake();

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andThrow(new \RuntimeException('Connection timed out'));

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'result' => 'SUCCESS',
            'type' => '11',
            'trackingCode' => 'track-456',
            'providerId' => 'provider-123',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/confirm?order_id='.$order->id, $response->headers->get('Location'));
        $this->assertSame('verification_pending', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        Queue::assertPushed(VerifyDigipayPayment::class);
    }

    #[RunInSeparateProcess]
    public function test_digipay_callback_without_provider_id_does_not_guess_latest_payment(): void
    {
        [$order, $firstPayment] = $this->makeOrderWithPendingPayment('digipay');
        $secondPayment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'second-digipay-txn',
            'amount' => $order->total_amount,
            'payment_method' => 'online',
            'gateway' => 'digipay',
            'status' => 'pending',
            'description' => 'در انتظار پرداخت',
        ]);

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'result' => 'SUCCESS',
            'type' => '11',
            'trackingCode' => 'track-456',
        ]);

        $response->assertRedirect();
        $this->assertSame('needs_review', $firstPayment->fresh()->status);
        $this->assertSame('needs_review', $secondPayment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    #[RunInSeparateProcess]
    public function test_digipay_callback_matches_a_single_legacy_attempt_without_provider_id(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('digipay');
        Queue::fake();

        $receipt = Mockery::mock(Receipt::class);
        $receipt->shouldReceive('getReferenceId')->andReturn('LEGACY-REF');
        $receipt->shouldReceive('getDetails')->andReturn([]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andReturn($receipt);

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'providerId' => 'legacy-provider-id',
            'type' => '11',
            'trackingCode' => 'legacy-tracking-code',
        ]);

        $response->assertRedirect();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('legacy-provider-id', $payment->fresh()->provider_id);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_digipay_callback_does_not_guess_between_multiple_legacy_attempts(): void
    {
        [$order, $firstPayment] = $this->makeOrderWithPendingPayment('digipay');
        $secondPayment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'second-legacy-attempt',
            'amount' => $order->total_amount,
            'payment_method' => 'online',
            'gateway' => 'digipay',
            'status' => 'pending',
            'description' => 'در انتظار پرداخت',
        ]);

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'providerId' => 'ambiguous-provider-id',
            'type' => '11',
            'trackingCode' => 'ambiguous-tracking-code',
        ]);

        $response->assertRedirect();
        $this->assertSame('needs_review', $firstPayment->fresh()->status);
        $this->assertSame('needs_review', $secondPayment->fresh()->status);
        $this->assertNull($firstPayment->fresh()->provider_id);
        $this->assertNull($secondPayment->fresh()->provider_id);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_digipay_callback_does_not_attach_provider_id_already_used_by_another_order(): void
    {
        [$order, $legacyPayment] = $this->makeOrderWithPendingPayment('digipay');
        [$otherOrder] = $this->makeOrderWithPendingPayment('digipay');
        $existingPayment = Payment::where('order_id', $otherOrder->id)->firstOrFail();
        $existingPayment->update(['provider_id' => 'already-used-provider-id']);

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'providerId' => 'already-used-provider-id',
            'type' => '11',
            'trackingCode' => 'unmatched-tracking-code',
        ]);

        $response->assertRedirect();
        $this->assertSame('needs_review', $legacyPayment->fresh()->status);
        $this->assertNull($legacyPayment->fresh()->provider_id);
        $this->assertSame('already-used-provider-id', $existingPayment->fresh()->provider_id);
    }

    public function test_digipay_callback_with_incomplete_verification_context_is_sent_to_manual_review(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('digipay');
        $payment->update(['provider_id' => 'incomplete-context-provider']);
        Queue::fake();

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'providerId' => 'incomplete-context-provider',
            'type' => '11',
        ]);

        $response->assertRedirect();
        $this->assertSame('needs_review', $payment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        Queue::assertNothingPushed();
    }

    #[RunInSeparateProcess]
    public function test_digipay_callback_verifies_second_attempt_after_order_is_paid(): void
    {
        [$order, $firstPayment] = $this->makeOrderWithPendingPayment('digipay');
        $order->update(['payment_status' => 'paid', 'status' => 'confirmed']);
        $firstPayment->update(['provider_id' => 'already-paid-provider', 'status' => 'paid']);
        $secondPayment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'second-digipay-attempt',
            'provider_id' => 'second-provider-id',
            'amount' => $order->total_amount,
            'payment_method' => 'online',
            'gateway' => 'digipay',
            'status' => 'pending',
            'description' => 'در انتظار پرداخت',
        ]);

        $receipt = Mockery::mock(Receipt::class);
        $receipt->shouldReceive('getReferenceId')->andReturn('SECOND-REF');
        $receipt->shouldReceive('getDetails')->andReturn([]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andReturn($receipt);

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'providerId' => 'second-provider-id',
            'type' => '11',
            'trackingCode' => 'second-tracking-code',
        ]);

        $response->assertRedirect();
        $this->assertSame('paid', $secondPayment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    #[RunInSeparateProcess]
    public function test_definitive_digipay_failure_marks_order_failed_and_releases_discount_reservation(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('digipay');
        $providerId = 'provider-terminal-failure';
        $payment->update(['provider_id' => $providerId]);
        $discountCodeId = DB::table('discount_codes')->insertGetId([
            'code' => 'DIGIPAYFAIL',
            'discount_amount' => 1000,
            'platforms' => json_encode(['main']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $order->update([
            'discount_code_id' => $discountCodeId,
            'discount_code_snapshot' => 'DIGIPAYFAIL',
            'discount_amount' => 1000,
        ]);
        app(DiscountCodeService::class)->reserveUsage($order);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andThrow(new InvalidPaymentException('declined', 9007));

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'providerId' => $providerId,
            'type' => '11',
            'trackingCode' => 'tracking-declined',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/payment-failed', $response->headers->get('Location'));
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertDatabaseHas('discount_code_usages', [
            'order_id' => $order->id,
            'status' => 'released',
        ]);
    }

    #[RunInSeparateProcess]
    public function test_definitive_failure_keeps_discount_reserved_while_another_attempt_is_pending(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('digipay');
        $payment->update(['provider_id' => 'failed-provider-id']);
        $otherPayment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'still-pending-attempt',
            'provider_id' => 'still-pending-provider',
            'amount' => $order->total_amount,
            'payment_method' => 'online',
            'gateway' => 'digipay',
            'status' => 'pending',
            'description' => 'در انتظار پرداخت',
        ]);
        $discountCodeId = DB::table('discount_codes')->insertGetId([
            'code' => 'KEEPRESERVED',
            'discount_amount' => 1000,
            'platforms' => json_encode(['main']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $order->update([
            'discount_code_id' => $discountCodeId,
            'discount_code_snapshot' => 'KEEPRESERVED',
            'discount_amount' => 1000,
        ]);
        app(DiscountCodeService::class)->reserveUsage($order);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andThrow(new InvalidPaymentException('declined', 9007));

        $response = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'providerId' => 'failed-provider-id',
            'type' => '11',
            'trackingCode' => 'failed-tracking-code',
        ]);

        $response->assertRedirect();
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('pending', $otherPayment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertDatabaseHas('discount_code_usages', [
            'order_id' => $order->id,
            'status' => 'reserved',
        ]);
    }

    #[RunInSeparateProcess]
    public function test_successful_verify_marks_order_paid(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('digipay');
        $payment->update(['provider_id' => 'provider-success']);

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
            'providerId' => 'provider-success',
            'type' => '11',
            'trackingCode' => 'tracking-success',
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

    public function test_payment_status_reports_order_success_and_pending_sibling_before_latest_attempt(): void
    {
        [$order, $failedPayment] = $this->makeOrderWithPendingPayment('digipay');
        $failedPayment->update(['status' => 'failed']);
        $pendingPayment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'newest-pending-attempt',
            'amount' => $order->total_amount,
            'payment_method' => 'online',
            'gateway' => 'digipay',
            'status' => 'pending',
            'description' => 'در انتظار پرداخت',
        ]);

        $request = Request::create('/api/payment/status/'.$order->id, 'GET');
        $request->setUserResolver(fn () => $order->user);
        $controller = app(PaymentController::class);

        $response = $controller->status($request, $order->id);
        $this->assertSame('pending', $response->getData(true)['confirmation_status']);

        $order->update(['payment_status' => 'paid', 'status' => 'confirmed']);
        $response = $controller->status($request, $order->id);
        $this->assertSame('paid', $response->getData(true)['confirmation_status']);

        $order->update(['payment_status' => 'failed']);
        $pendingPayment->update(['status' => 'failed']);
        $response = $controller->status($request, $order->id);
        $this->assertSame('failed', $response->getData(true)['confirmation_status']);
    }

    #[RunInSeparateProcess]
    public function test_post_verify_failure_still_persists_paid_status(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('digipay');
        $payment->update(['provider_id' => 'provider-observer']);

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
            'providerId' => 'provider-observer',
            'type' => '11',
            'trackingCode' => 'tracking-observer',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/confirm?order_id='.$order->id, $response->headers->get('Location'));

        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status, 'پرداخت واقعاً موفق بوده و نباید failed شود فقط چون observer خطا داده.');
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
    }

    #[RunInSeparateProcess]
    public function test_successful_verify_with_zero_stock_still_marks_paid(): void
    {
        config(['hesabfa.enable_reserved_stock' => true]);

        [$order, $payment] = $this->makeOrderWithPendingPayment('parsian');

        $product = Product::create([
            'name' => 'شمش بدون موجودی',
            'slug' => 'zero-stock-'.uniqid(),
            'price' => 100000,
            'stock_quantity' => 0,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'quantity' => 1,
            'subtotal' => $product->price,
        ]);

        $receipt = Mockery::mock(Receipt::class);
        $receipt->shouldReceive('getReferenceId')->andReturn('RRN-ZERO-STOCK');
        $receipt->shouldReceive('getDetails')->andReturn([]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andReturn($receipt);

        $response = $this->post("/api/payment/callback/{$order->id}/parsian", [
            'Token' => $payment->transaction_id,
            'status' => '0',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/confirm?order_id='.$order->id, $response->headers->get('Location'));

        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status, 'پرداخت موفق با موجودی صفر نباید failed شود.');
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

    #[RunInSeparateProcess]
    public function test_duplicate_callback_after_success_does_not_overwrite_paid(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('digipay');
        $payment->update(['provider_id' => 'provider-duplicate']);

        $receipt = Mockery::mock(Receipt::class);
        $receipt->shouldReceive('getReferenceId')->andReturn('REF-789');
        $receipt->shouldReceive('getDetails')->andReturn([]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andReturn($receipt);

        $this->post("/api/payment/callback/{$order->id}/digipay", [
            'result' => 'SUCCESS',
            'providerId' => 'provider-duplicate',
            'type' => '11',
            'trackingCode' => 'tracking-duplicate',
        ]);

        $payment->refresh();

        $this->assertSame('paid', $payment->status);

        $secondResponse = $this->post("/api/payment/callback/{$order->id}/digipay", [
            'result' => 'SUCCESS',
            'providerId' => 'provider-duplicate',
            'type' => '11',
            'trackingCode' => 'tracking-duplicate',
        ]);

        $secondResponse->assertRedirect();
        $this->assertStringContainsString('/confirm?order_id='.$order->id, $secondResponse->headers->get('Location'));

        $payment->refresh();

        $this->assertSame('paid', $payment->status, 'Duplicate callback must not overwrite paid status');
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    #[RunInSeparateProcess]
    public function test_callback_matches_payment_by_transaction_id_from_request(): void
    {
        $order = Order::factory()->create([
            'total_amount' => 200000,
            'payment_status' => 'pending',
            'status' => 'awaiting_payment',
        ]);

        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'parsian-token-999',
            'amount' => 200000,
            'payment_method' => 'online',
            'gateway' => 'parsian',
            'status' => 'pending',
            'description' => 'در انتظار پرداخت',
        ]);

        $receipt = Mockery::mock(Receipt::class);
        $receipt->shouldReceive('getReferenceId')->andReturn('RRN-888');
        $receipt->shouldReceive('getDetails')->andReturn([]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andReturn($receipt);

        $response = $this->post("/api/payment/callback/{$order->id}/parsian", [
            'Token' => 'parsian-token-999',
            'status' => '0',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/confirm?order_id='.$order->id, $response->headers->get('Location'));

        $payment->refresh();

        $this->assertSame('paid', $payment->status);
    }

    #[RunInSeparateProcess]
    public function test_callback_with_matching_transaction_id_already_paid_redirects_to_confirm(): void
    {
        $order = Order::factory()->create([
            'total_amount' => 300000,
            'payment_status' => 'pending',
            'status' => 'awaiting_payment',
        ]);

        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'already-paid-txn',
            'amount' => 300000,
            'payment_method' => 'online',
            'gateway' => 'parsian',
            'status' => 'paid',
            'paid_at' => now(),
            'description' => 'قبلاً پرداخت شده',
        ]);

        $response = $this->post("/api/payment/callback/{$order->id}/parsian", [
            'Token' => 'already-paid-txn',
            'status' => '0',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/confirm?order_id='.$order->id, $response->headers->get('Location'));

        $payment->refresh();

        $this->assertSame('paid', $payment->status);
    }

    public function test_callback_with_unknown_transaction_id_redirects_to_checkout(): void
    {
        $order = Order::factory()->create([
            'total_amount' => 150000,
            'payment_status' => 'pending',
            'status' => 'awaiting_payment',
        ]);

        $response = $this->post("/api/payment/callback/{$order->id}/parsian", [
            'Token' => 'nonexistent-token',
            'status' => '0',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/checkout', $response->headers->get('Location'));
    }

    #[RunInSeparateProcess]
    public function test_bare_callback_resolves_payment_by_tv_token_and_marks_paid(): void
    {
        [$order, $payment] = $this->makeOrderWithPendingPayment('nopay');

        $receipt = Mockery::mock(Receipt::class);
        $receipt->shouldReceive('getReferenceId')->andReturn('REF-NOPAY-1');
        $receipt->shouldReceive('getDetails')->andReturn([]);

        $mock = Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andReturn($receipt);

        $response = $this->get("/api/payment/callback?tv={$payment->transaction_id}");

        $response->assertRedirect();
        $this->assertStringContainsString('https://pay.sawiss.com/confirm?order_id='.$order->id, $response->headers->get('Location'));

        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
    }

    public function test_bare_callback_with_unknown_token_redirects_to_checkout(): void
    {
        $response = $this->get('/api/payment/callback?tv=unknown-token');

        $response->assertRedirect();
        $this->assertStringContainsString('/checkout', $response->headers->get('Location'));
    }

    public function test_bare_callback_without_token_redirects_to_checkout(): void
    {
        $response = $this->get('/api/payment/callback');

        $response->assertRedirect();
        $this->assertStringContainsString('/checkout', $response->headers->get('Location'));
    }
}
