<?php

namespace App\Jobs;

use App\Services\DigiExpressShipmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Modules\Order\Models\Order;

class RegisterDigiExpressShipment implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $orderId) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('digiexpress-order-'.$this->orderId))->expireAfter(300)];
    }

    public function handle(DigiExpressShipmentService $service): void
    {
        $order = Order::query()->find($this->orderId);
        if ($order) {
            $service->register($order);
        }
    }
}
