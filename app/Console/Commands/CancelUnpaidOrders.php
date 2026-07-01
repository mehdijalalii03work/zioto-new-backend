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
        $cancelled = Order::where('status', 'pending')
            ->where(function ($query) {
                $query->where('payment_status', 'pending')
                    ->orWhere('payment_status', 'failed');
            })
            ->where('created_at', '<', now()->subMinutes(20))
            ->update([
                'status' => 'cancelled',
                'cancel_reason' => 'عدم پرداخت ظرف ۲۰ دقیقه',
            ]);

        if ($cancelled > 0) {
            $this->info("{$cancelled} سفارش پرداخت نشده لغو شد.");
        }

        return self::SUCCESS;
    }
}
