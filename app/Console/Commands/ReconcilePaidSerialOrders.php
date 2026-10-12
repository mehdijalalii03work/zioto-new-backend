<?php

namespace App\Console\Commands;

use App\Jobs\ReconcilePaidSerialOrder;
use App\Services\Inventory\SerialInventoryService;
use App\Support\Platform;
use Illuminate\Console\Command;
use Modules\Order\Models\Order;

class ReconcilePaidSerialOrders extends Command
{
    protected $signature = 'serials:reconcile-paid';

    protected $description = 'Dispatch recovery for paid serial orders with incomplete allocation';

    public function handle(SerialInventoryService $inventory): int
    {
        $dispatched = 0;

        Order::withoutTenantScope()
            ->where('payment_status', 'paid')
            ->where('serial_inventory_enabled', true)
            ->whereNotIn('platform', [Platform::TAPSI])
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('id')
            ->chunk(100, function ($orders) use ($inventory, &$dispatched): void {
                foreach ($orders as $order) {
                    if ($inventory->isSeriallyComplete($order)) {
                        continue;
                    }

                    ReconcilePaidSerialOrder::dispatch($order->id, $order->platform);

                    $dispatched++;
                }
            });

        if ($dispatched > 0) {
            $this->info("بازیابی {$dispatched} سفارش پرداخت‌شده در صف قرار گرفت.");
        }

        return self::SUCCESS;
    }
}
