<?php

namespace App\Services;

use App\Enums\OrderSmsType;
use App\Jobs\SendOrderSms;
use Modules\Order\Models\Order;
use Morilog\Jalali\Jalalian;
use Throwable;

class OrderSmsNotificationService
{
    public function __construct(private readonly SmsIrService $sms) {}

    public function queueConfirmation(Order $order): void
    {
        if ($order->status !== 'confirmed' || $order->payment_status !== 'paid') {
            return;
        }

        $this->queueIfUnsent($order, OrderSmsType::Confirmation);
    }

    public function queueReceivedHealthMessage(Order $order): void
    {
        if (! in_array($order->status, ['confirmed', 'completed'], true) || $order->payment_status !== 'paid') {
            return;
        }

        $this->queueIfUnsent($order, OrderSmsType::ReceivedHealth);
    }

    public function send(int $orderId, OrderSmsType $type): bool
    {
        $order = Order::withoutTenantScope()
            ->with(['shipping.shippingMethod', 'user'])
            ->find($orderId);

        if (! $order || ! $this->isEligible($order, $type)) {
            return true;
        }

        $shipping = $order->shipping;
        $user = $order->user;
        $mobile = $user?->phone;
        $firstName = $this->firstName($user?->first_name, $user?->name);

        if (! $shipping || ! $mobile || ! $firstName) {
            return true;
        }

        $date = Jalalian::fromDateTime($order->created_at)->format('Y/m/d');
        $trackingCode = (string) $order->order_number;

        if ($type === OrderSmsType::ReceivedHealth) {
            $sent = $this->sms->sendOrderReceivedHealthMessage($mobile, $firstName);
            $sentAtColumn = 'received_health_sms_sent_at';
        } elseif ($shipping->shippingMethod?->is_pickup) {
            $sent = $this->sms->sendPickupOrderConfirmation(
                $mobile,
                $firstName,
                $trackingCode,
                (int) $order->total_amount,
                $date,
            );
            $sentAtColumn = 'order_confirmation_sms_sent_at';
        } else {
            $sent = $this->sms->sendShippingOrderConfirmation(
                $mobile,
                $firstName,
                $trackingCode,
                (int) $order->total_amount,
                $date,
            );
            $sentAtColumn = 'order_confirmation_sms_sent_at';
        }

        if (! $sent) {
            return false;
        }

        try {
            $shipping->forceFill([$sentAtColumn => now()])->save();
        } catch (Throwable $exception) {
            try {
                report($exception);
            } catch (Throwable) {
                // Do not retry an SMS accepted by the provider because marker logging failed.
            }
        }

        return true;
    }

    private function queueIfUnsent(Order $order, OrderSmsType $type): void
    {
        try {
            $order->loadMissing('shipping.shippingMethod', 'user');

            if (! $this->isEligible($order, $type)) {
                return;
            }

            SendOrderSms::dispatch((int) $order->getKey(), $type);
        } catch (Throwable $exception) {
            try {
                report($exception);
            } catch (Throwable) {
                // A queue outage must not undo payment or order completion.
            }
        }
    }

    private function isEligible(Order $order, OrderSmsType $type): bool
    {
        $shipping = $order->shipping;

        if (! $shipping || ! $shipping->shippingMethod || ! $order->user?->phone) {
            return false;
        }

        if ($type === OrderSmsType::Confirmation) {
            return in_array($order->status, ['confirmed', 'completed'], true)
                && $order->payment_status === 'paid'
                && $shipping->order_confirmation_sms_sent_at === null;
        }

        return in_array($order->status, ['confirmed', 'completed'], true)
            && $order->payment_status === 'paid'
            && ! $shipping->shippingMethod->is_pickup
            && $shipping->received_health_sms_sent_at === null;
    }

    private function firstName(?string $firstName, ?string $fullName): ?string
    {
        $firstName = trim((string) $firstName);

        if ($firstName !== '') {
            return $firstName;
        }

        $fullName = trim((string) $fullName);

        if ($fullName === '') {
            return null;
        }

        return explode(' ', $fullName, 2)[0];
    }
}
