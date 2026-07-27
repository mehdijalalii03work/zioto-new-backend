<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Tests\TestCase;

class PaymentControllerInitTest extends TestCase
{
    use RefreshDatabase;

    public function test_init_rejects_already_paid_order(): void
    {
        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'total_amount' => 100000,
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error_code' => 'ORDER_ALREADY_PAID',
        ]);
    }

    public function test_init_rejects_cancelled_order(): void
    {
        $order = Order::factory()->create([
            'status' => 'cancelled',
            'payment_status' => 'pending',
            'total_amount' => 100000,
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error_code' => 'ORDER_NOT_ACTIVE',
        ]);
    }

    public function test_init_rejects_expired_order(): void
    {
        $order = Order::factory()->create([
            'status' => 'expired',
            'payment_status' => 'pending',
            'total_amount' => 100000,
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error_code' => 'ORDER_NOT_ACTIVE',
        ]);
    }

    public function test_init_rejects_duplicate_pending_payment(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 100000,
        ]);

        Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'existing-txn',
            'amount' => 100000,
            'payment_method' => 'online',
            'gateway' => 'parsian',
            'status' => 'pending',
            'description' => 'در انتظار پرداخت',
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error_code' => 'PAYMENT_ALREADY_INITIATED',
        ]);
    }

    public function test_init_allows_new_payment_for_order_with_previous_failed_payment(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'failed',
            'total_amount' => 100000,
        ]);

        Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'failed-txn',
            'amount' => 100000,
            'payment_method' => 'online',
            'gateway' => 'parsian',
            'status' => 'failed',
            'description' => 'پرداخت ناموفق',
        ]);

        $response = $this->postJson('/api/payment/init', [
            'order_id' => $order->id,
            'gateway' => 'parsian',
        ]);

        $this->assertNotEquals(422, $response->getStatusCode(), 'Init should allow retry after failed payment');
    }
}
