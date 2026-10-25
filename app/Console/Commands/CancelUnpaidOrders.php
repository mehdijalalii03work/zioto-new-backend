<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;

class CancelUnpaidOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid';

    protected $description = 'Cancel orders that are older than 20 minutes and still unpaid';

    public function handle(): int
    {
        Payment::withoutTenantScope()
            ->where('gateway', 'digipay')
            ->where('status', 'pending')
            ->whereNull('provider_id')
            ->whereHas('order', fn ($query) => $query
                ->where('status', 'pending')
                ->whereIn('payment_status', ['pending', 'failed'])
                ->where('created_at', '<', now()->subMinutes(20)))
            ->update([
                'status' => 'needs_review',
                'gateway_response' => json_encode([
                    'verification' => ['last_error' => 'پرداخت قدیمی دیجی‌پی شناسه‌ی تطبیق ندارد و باید دستی بررسی شود.'],
                ], JSON_THROW_ON_ERROR),
            ]);

        // Scheduled job: must cover orders from ALL platforms.
        $orders = Order::withoutTenantScope()
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->where('payment_status', 'pending')
                    ->orWhere('payment_status', 'failed');
            })
            ->where('created_at', '<', now()->subMinutes(20))
            ->whereDoesntHave('payments', fn ($q) => $q
                ->whereIn('status', ['processing', 'verification_pending', 'needs_review'])
                ->orWhere(function ($legacyDigipayPaymentQuery) {
                    $legacyDigipayPaymentQuery
                        ->where('gateway', 'digipay')
                        ->where('status', 'pending')
                        ->whereNull('provider_id');
                }))
            ->get();

        $cancelled = 0;

        foreach ($orders as $order) {
            $order->update([
                'status' => 'cancelled',
                'cancel_reason' => 'عدم پرداخت ظرف ۲۰ دقیقه',
            ]);
            $cancelled++;
        }

        if ($cancelled > 0) {
            $this->info("{$cancelled} سفارش پرداخت نشده لغو شد.");
        }

        return self::SUCCESS;
    }
}
