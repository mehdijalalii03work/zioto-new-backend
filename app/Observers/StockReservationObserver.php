<?php

namespace App\Observers;

use App\Services\RahkaranInventoryService;
use App\Services\StockSyncService;
use App\Support\InventorySource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;

class StockReservationObserver
{
    private const RESERVED_STATUSES = ['confirmed', 'completed'];

    public function __construct(
        private StockSyncService $stockSync,
        private RahkaranInventoryService $rahkaranInventory,
    ) {}

    public function created(Order $order): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if ($this->usesRahkaranSerials()) {
            if ($order->payment_status === 'paid' && in_array($order->status, self::RESERVED_STATUSES, true)) {
                $this->reserveSelectedSource($order);
                if ($order->status === 'completed') {
                    $this->rahkaranInventory->finalizeOrder($order);
                }
            }

            return;
        }

        if (in_array($order->status, self::RESERVED_STATUSES, true)) {
            $this->reserveSelectedSource($order);
        }
    }

    public function updated(Order $order): void
    {
        if ($order->getOriginal('status') === 'pending' && in_array($order->status, ['cancelled', 'expired'], true)) {
            $this->rahkaranInventory->releaseOrder($order);

            return;
        }

        if (! $this->isEnabled()) {
            return;
        }

        if ($this->usesRahkaranSerials()) {
            $wasAssigned = $order->getOriginal('payment_status') === 'paid'
                && in_array($order->getOriginal('status'), self::RESERVED_STATUSES, true);
            $isAssigned = $order->payment_status === 'paid'
                && in_array($order->status, self::RESERVED_STATUSES, true);

            if (! $wasAssigned && $isAssigned) {
                $this->reserveSelectedSource($order);
            } elseif ($wasAssigned && ! $isAssigned) {
                $this->releaseSelectedSource($order);
            }

            if ($order->wasChanged('status')
                && $order->status === 'completed'
                && $isAssigned
                && (InventorySource::isOffline() || filled($order->rahkaran_invoice_id))) {
                $this->rahkaranInventory->finalizeOrder($order);
            }

            return;
        }

        $wasReserved = in_array($order->getOriginal('status'), self::RESERVED_STATUSES, true);
        $isReserved = in_array($order->status, self::RESERVED_STATUSES, true);

        if (! $wasReserved && $isReserved) {
            $this->reserveSelectedSource($order);
        } elseif ($wasReserved && ! $isReserved) {
            $this->releaseSelectedSource($order);
        }

    }

    public function deleted(Order $order): void
    {
        if ($order->status === 'pending') {
            $this->rahkaranInventory->releaseOrder($order);

            return;
        }

        if (! $this->isEnabled()) {
            return;
        }

        if ($this->usesRahkaranSerials() && $order->payment_status !== 'paid') {
            return;
        }

        if (in_array($order->status, self::RESERVED_STATUSES, true)) {
            $this->releaseSelectedSource($order);
        }
    }

    private function reserveSelectedSource(Order $order): void
    {
        if ($this->usesRahkaranSerials()) {
            try {
                $this->rahkaranInventory->reserveForOrder($order);
            } catch (\Throwable $exception) {
                Log::error('Rahkaran serial reservation failed', ['order_id' => $order->id, 'message' => $exception->getMessage()]);
            }

            if (InventorySource::usesRahkaranInventory()) {
                return;
            }
        }

        $this->reserveStock($order);
    }

    private function releaseSelectedSource(Order $order): void
    {
        if ($this->usesRahkaranSerials()) {
            $this->rahkaranInventory->releaseOrder($order);

            if (InventorySource::usesRahkaranInventory()) {
                return;
            }
        }

        $this->releaseStock($order);
    }

    private function isEnabled(): bool
    {
        return InventorySource::usesRahkaranInventory()
            || setting('rahkaran_serial_tracking_enabled', false)
            || config('hesabfa.enable_reserved_stock', false);
    }

    private function usesRahkaranSerials(): bool
    {
        return InventorySource::usesRahkaranInventory()
            || setting('rahkaran_serial_tracking_enabled', false);
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
