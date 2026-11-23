<?php

namespace App\Jobs;

use App\Services\RahkaranInvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use RuntimeException;

class SyncRahkaranInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $orderId)
    {
        $this->afterCommit();
    }

    public function handle(RahkaranInvoiceService $invoices): void
    {
        $order = Order::withoutTenantScope()->find($this->orderId);
        if (! $order) {
            return;
        }

        $result = $invoices->syncOrder($order);
        if (! $result['success']) {
            Log::error('Rahkaran invoice sync failed', ['order_id' => $order->id, 'message' => $result['message']]);
            throw new RuntimeException($result['message']);
        }
    }
}
