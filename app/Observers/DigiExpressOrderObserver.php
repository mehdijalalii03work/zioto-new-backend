<?php

namespace App\Observers;

use App\Jobs\RegisterDigiExpressShipment;
use App\Models\Setting;
use Modules\Order\Models\Order;

class DigiExpressOrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('payment_status') || $order->payment_status !== 'paid') {
            return;
        }
        if (Setting::getValue('digiexpress_registration_timing', 'after_preparation') !== 'after_payment'
            || Setting::getValue('digiexpress_parcel_mode', 'estimated') !== 'estimated'
            || $order->shipping?->shippingMethod?->code !== 'digiexpress_address') {
            return;
        }

        RegisterDigiExpressShipment::dispatch($order->id)->afterCommit();
    }
}
