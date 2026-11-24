<?php

namespace App\Console\Commands;

use App\Support\InventorySource;
use App\Support\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ->where('created_at', '<=', now()->subMinutes(20))
            ->lazyById();

        $cancelled = 0;

        foreach ($orders as $order) {
            $cancelled += DB::transaction(function () use ($order): int {
                $lockedOrder = Order::withoutTenantScope()->lockForUpdate()->find($order->id);
                if (! $lockedOrder || $lockedOrder->status !== 'pending'
                    || ! in_array($lockedOrder->payment_status, ['pending', 'failed'], true)) {
                    return 0;
                }

                $processing = $lockedOrder->payments()->withoutGlobalScope(TenantScope::class)
                    ->where('status', 'processing')->latest('updated_at')->first();
                if ($processing && (! InventorySource::usesRahkaranInventory()
                    || $processing->updated_at->gt(now()->subMinutes(5)))) {
                    return 0;
                }

                $lockedOrder->update(['status' => 'cancelled', 'cancel_reason' => 'عدم پرداخت ظرف ۲۰ دقیقه']);
                if ($processing) {
                    $lockedOrder->addNote('نتیجهٔ پرداخت مشخص نشد و نیازمند تطبیق مالی است.', 'payment');
                    Log::warning('Expired order has an unresolved payment', ['order_id' => $lockedOrder->id, 'payment_id' => $processing->id]);
                }

                return 1;
            }, attempts: 3);
        }

        if ($cancelled > 0) {
            $this->info("{$cancelled} سفارش پرداخت نشده لغو شد.");
        }

        return self::SUCCESS;
    }
}
