<?php

namespace App\Observers;

use App\Services\OrderSmsNotificationService;
use Modules\Order\Models\Order;

class OrderSmsNotificationObserver
{
    public function __construct(private readonly OrderSmsNotificationService $notifications) {}

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status') && in_array($order->status, ['confirmed', 'completed'], true)) {
            $this->notifications->queueReceivedHealthMessage($order);
        }
    }
}
