<?php

namespace App\Console\Commands;

use App\Models\ProductSerial;
use App\Models\ProductStockReservation;
use App\Services\Inventory\SerialInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Throwable;

class ReleaseExpiredSerialReservations extends Command
{
    protected $signature = 'serials:release-expired';

    protected $description = 'Release expired serial and manual reservations whose payment never completed';

    public function handle(SerialInventoryService $inventory): int
    {
        $now = now();

        $serialOrderIds = ProductSerial::where('status', 'reserved')
            ->where('reserved_until', '<', $now)
            ->whereNotNull('current_order_item_id')
            ->join('order_items', 'order_items.id', '=', 'product_serials.current_order_item_id')
            ->distinct()
            ->pluck('order_items.order_id');

        $manualOrderIds = ProductStockReservation::where('status', 'reserved')
            ->where('reserved_until', '<', $now)
            ->join('order_items', 'order_items.id', '=', 'product_stock_reservations.order_item_id')
            ->distinct()
            ->pluck('order_items.order_id');

        $orderIds = $serialOrderIds->merge($manualOrderIds)->unique()->values();

        $released = 0;

        foreach ($orderIds as $orderId) {
            $order = Order::withoutTenantScope()->find($orderId);

            if ($order === null) {
                continue;
            }

            try {
                $inventory->releaseExpiredReservation($order->id, $order->platform);
                $released++;
            } catch (Throwable $e) {
                Log::warning('Failed to release expired serial reservation.', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("سفارش {$order->id} آزاد نشد: {$e->getMessage()}");
            }
        }

        if ($released > 0) {
            $this->info("رزرو منقضی {$released} سفارش آزاد شد.");
        }

        return self::SUCCESS;
    }
}
