<?php

namespace Tests\Feature\Commands;

use App\Services\DiscountCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Tests\TestCase;

class CancelUnpaidOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_digipay_payment_without_provider_id_is_not_cancelled(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
            'created_at' => now()->subMinutes(30),
        ]);

        $payment = Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'legacy-digipay-pending',
            'amount' => $order->total_amount,
            'payment_method' => 'online',
            'gateway' => 'digipay',
            'status' => 'pending',
            'description' => 'در انتظار پرداخت',
        ]);

        $this->artisan('orders:cancel-unpaid')->assertExitCode(0);

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('needs_review', $payment->fresh()->status);
    }

    public function test_orders_with_digipay_verification_in_progress_are_not_cancelled(): void
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_status' => 'pending',
            'created_at' => now()->subMinutes(30),
        ]);

        $discountCodeId = DB::table('discount_codes')->insertGetId([
            'code' => 'DIGIPAYRESERVE',
            'discount_amount' => 1000,
            'platforms' => json_encode(['main']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $order->update([
            'discount_code_id' => $discountCodeId,
            'discount_code_snapshot' => 'DIGIPAYRESERVE',
            'discount_amount' => 1000,
        ]);
        app(DiscountCodeService::class)->reserveUsage($order);

        Payment::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'transaction_id' => 'digipay-verifying-1',
            'amount' => $order->total_amount,
            'payment_method' => 'online',
            'gateway' => 'digipay',
            'status' => 'verification_pending',
            'description' => 'در حال بررسی پرداخت',
        ]);

        $this->artisan('orders:cancel-unpaid')->assertExitCode(0);

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseHas('discount_code_usages', [
            'order_id' => $order->id,
            'status' => 'reserved',
        ]);
    }
}
