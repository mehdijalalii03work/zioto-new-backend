<?php

namespace App\Jobs;

use App\Services\Inventory\SerialInventoryService;
use App\Support\Platform;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Throwable;

class ReconcilePaidSerialOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $orderId,
        public readonly string $platform,
    ) {}

    /**
     * Recover a paid order whose serial finalization never completed
     * (callback crash, quiet fallback, lost queue). Skips tapsi, unpaid,
     * legacy and already-complete orders.
     */
    public function handle(SerialInventoryService $inventory): void
    {
        if ($this->platform === Platform::TAPSI) {
            return;
        }

        $order = Order::withoutTenantScope()
            ->where('id', $this->orderId)
            ->where('platform', $this->platform)
            ->first();

        if ($order === null || $order->status === 'cancelled' || $order->payment_status !== 'paid' || ! $order->serial_inventory_enabled) {
            return;
        }

        if ($inventory->isSeriallyComplete($order)) {
            return;
        }

        $inventory->finalizePaidOrder($order->id, $order->platform);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Paid serial reconcile failed.', [
            'order_id' => $this->orderId,
            'platform' => $this->platform,
            'error' => basename($e->getMessage()),
        ]);
    }
}
