<?php

namespace App\Observers;

use App\Jobs\SyncRahkaranInvoice;
use Modules\Order\Models\Order;

class RahkaranInvoiceObserver
{
    public function updated(Order $order): void
    {
        if (filled($order->tapsi_order_id)
            || (! $order->wasChanged('status') && ! $order->wasChanged('payment_status'))
            || $order->status !== 'confirmed'
            || $order->payment_status !== 'paid') {
            return;
        }

        if (! setting('send_invoices_to_rahkaran', false) || $order->rahkaran_invoice_id || $order->rahkaran_sync_status === 'success') {
            return;
        }

        SyncRahkaranInvoice::dispatch($order->id)->afterCommit();
    }
}
