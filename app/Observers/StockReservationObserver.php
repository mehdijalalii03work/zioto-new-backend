<?php

namespace App\Observers;

use App\Services\StockSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;

class StockReservationObserver
{
    private const RESERVED_STATUSES = ['confirmed', 'processing'];

    public function __construct(
        private StockSyncService $stockSync,
    ) {}

    public function created(Order $order): void
    {
        if (in_array($order->status, self::RESERVED_STATUSES)) {
            $this->reserveStock($order);
        }
    }

    public function updated(Order $order): void
    {
        $wasReserved = in_array($order->getOriginal('status'), self::RESERVED_STATUSES);
        $isReserved = in_array($order->status, self::RESERVED_STATUSES);

        if (! $wasReserved && $isReserved) {
            $this->reserveStock($order);
        } elseif ($wasReserved && ! $isReserved) {
            $this->releaseStock($order);
        }
    }

    public function deleted(Order $order): void
    {
        if (in_array($order->status, self::RESERVED_STATUSES)) {
            $this->releaseStock($order);
        }
    }

    public function reserveStock(Order $order): void
    {
        $order->load('items');

        $reservations = $order->items
            ->filter(fn ($item) => $item->product_id)
            ->mapWithKeys(fn ($item) => [$item->product_id => $item->quantity])
            ->all();

        if (empty($reservations)) {
            return;
        }

        foreach ($reservations as $productId => $quantity) {
            DB::table('products')
                ->where('id', $productId)
                ->increment('hesabfa_reserved_stock', $quantity);
        }

        Log::info('Stock reserved for order', [
            'order_id' => $order->id,
            'reservations' => $reservations,
        ]);
    }

    public function releaseStock(Order $order): void
    {
        $order->load('items');

        $reservations = $order->items
            ->filter(fn ($item) => $item->product_id)
            ->mapWithKeys(fn ($item) => [$item->product_id => $item->quantity])
            ->all();

        if (empty($reservations)) {
            return;
        }

        foreach ($reservations as $productId => $quantity) {
            DB::table('products')
                ->where('id', $productId)
                ->decrement('hesabfa_reserved_stock', $quantity);
        }

        Log::info('Stock released for order', [
            'order_id' => $order->id,
            'reservations' => $reservations,
        ]);
    }
}
