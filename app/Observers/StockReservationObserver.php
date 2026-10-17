<?php

namespace App\Observers;

use App\Services\StockSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;

class StockReservationObserver
{
    private const RESERVED_STATUSES = ['confirmed'];

    public function __construct(
        private StockSyncService $stockSync,
    ) {}

    public function created(Order $order): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (in_array($order->status, self::RESERVED_STATUSES)) {
            $this->reserveStock($order);
        }
    }

    public function updated(Order $order): void
    {
        if (! $this->isEnabled()) {
            return;
        }

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
        if (! $this->isEnabled()) {
            return;
        }

        if (in_array($order->status, self::RESERVED_STATUSES)) {
            $this->releaseStock($order);
        }
    }

    private function isEnabled(): bool
    {
        return config('hesabfa.enable_reserved_stock', false);
    }

    public function reserveStock(Order $order): void
    {
        $order->load('items.product');

        $reservations = $this->buildReservations($order);

        if (empty($reservations)) {
            return;
        }

        DB::transaction(function () use ($order, $reservations) {
            foreach ($reservations as $productId => $quantity) {
                $updated = DB::table('products')
                    ->where('id', $productId)
                    ->whereRaw('hesabfa_physical_stock - hesabfa_reserved_stock - hesabfa_manual_reserved >= ?', [$quantity])
                    ->increment('hesabfa_reserved_stock', $quantity);

                if (! $updated) {
                    Log::warning('Insufficient stock for reservation - order confirmed anyway', [
                        'order_id' => $order->id,
                        'product_id' => $productId,
                        'quantity' => $quantity,
                    ]);

                    continue;
                }
            }

            Log::info('Stock reserved for order', [
                'order_id' => $order->id,
                'reservations' => $reservations,
            ]);
        });
    }

    public function releaseStock(Order $order): void
    {
        $order->load('items.product');

        $reservations = $this->buildReservations($order);

        if (empty($reservations)) {
            return;
        }

        DB::transaction(function () use ($order, $reservations) {
            foreach ($reservations as $productId => $quantity) {
                DB::table('products')
                    ->where('id', $productId)
                    ->where('hesabfa_reserved_stock', '>=', $quantity)
                    ->decrement('hesabfa_reserved_stock', $quantity);
            }

            Log::info('Stock released for order', [
                'order_id' => $order->id,
                'reservations' => $reservations,
            ]);
        });
    }

    /**
     * Maps order items to reserved singles per stock-owning product.
     * Pack items reserve quantity × pack_size on their base product.
     *
     * @return array<int, int>
     */
    private function buildReservations(Order $order): array
    {
        $reservations = [];

        foreach ($order->items as $item) {
            if (! $item->product_id || ! $item->product) {
                continue;
            }

            $stockOwnerId = $item->product->base_product_id ?? $item->product_id;
            $singles = $item->quantity * $item->product->pack_size;

            $reservations[$stockOwnerId] = ($reservations[$stockOwnerId] ?? 0) + $singles;
        }

        return $reservations;
    }
}
