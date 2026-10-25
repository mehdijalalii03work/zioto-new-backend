<?php

namespace App\Observers;

use App\Services\DiscountCodeService;
use Modules\Order\Models\Order;

class DiscountCodeUsageObserver
{
    public function __construct(private DiscountCodeService $discountCodeService) {}

    public function updated(Order $order): void
    {
        if ($order->wasChanged('payment_status') && $order->payment_status === 'paid') {
            $this->discountCodeService->redeemUsage($order->id);

            return;
        }

        if ($order->wasChanged('status') && in_array($order->status, ['cancelled', 'expired'], true) && $order->payment_status !== 'paid') {
            $this->discountCodeService->releaseUsage($order->id);
        }
    }

    public function deleted(Order $order): void
    {
        if ($order->payment_status !== 'paid') {
            $this->discountCodeService->releaseUsage($order->id);
        }
    }
}
