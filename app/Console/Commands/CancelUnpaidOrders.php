<?php

namespace App\Console\Commands;

use App\Services\Inventory\SerialInventoryService;
use Illuminate\Console\Command;
use Modules\Order\Models\Order;
use Throwable;

class CancelUnpaidOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid';

    protected $description = 'Cancel orders that are older than 20 minutes and still unpaid';

    public function handle(SerialInventoryService $inventory): int
    {
        // Scheduled job: must cover orders from ALL platforms with an
        // explicit platform per order. Ambiguous payments (processing or
        // initiating) are never cancelled here; their fate is decided by the
        // gateway callback or the serial expiry sweep instead.
        $orders = Order::withoutTenantScope()
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->where('payment_status', 'pending')
                    ->orWhere('payment_status', 'failed');
            })
            ->where('created_at', '<', now()->subMinutes(20))
            ->whereDoesntHave('payments', fn ($q) => $q->whereIn('status', ['processing', 'initiating']))
            ->get();

        $cancelled = 0;

        foreach ($orders as $order) {
            try {
                if ($inventory->cancelUnpaidOrder($order->id, $order->platform, 'عدم پرداخت ظرف ۲۰ دقیقه', true)) {
                    $cancelled++;
                }
            } catch (Throwable $e) {
                report($e);
                $this->error("لغو سفارش {$order->id} انجام نشد.");
            }
        }

        if ($cancelled > 0) {
            $this->info("{$cancelled} سفارش پرداخت نشده لغو شد.");
        }

        return self::SUCCESS;
    }
}
