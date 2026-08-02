<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Order\Models\Order;

class CancelUnpaidOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid';

    protected $description = 'Cancel orders that are older than 20 minutes and still unpaid';

    public function handle(): int
    {
        // Scheduled job: must cover orders from ALL platforms.
        $orders = Order::withoutTenantScope()
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->where('payment_status', 'pending')
                    ->orWhere('payment_status', 'failed');
            })
            ->where('created_at', '<', now()->subMinutes(20))
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', 'processing'))
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
