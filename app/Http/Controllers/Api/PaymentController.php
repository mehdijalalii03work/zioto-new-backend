<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;
use Shetabit\Multipay\Invoice;

class PaymentController extends Controller
{
    public function init(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'gateway' => 'required|in:parsian,digipay,kamanlend,smartis',
        ]);

        $order = Order::findOrFail($validated['order_id']);

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'سفارش قبلاً پرداخت شده'], 422);
        }

        $notes = json_decode($order->notes, true) ?? [];
        $phone = $notes['phone'] ?? $request->user()?->phone ?? '';
        $nationalCode = $notes['national_code'] ?? '';
        $callbackUrl = route('payment.callback', ['orderId' => $order->id, 'gateway' => $validated['gateway']]);

        $invoice = (new Invoice)->amount($order->total_amount)->detail([
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
            'phone' => $phone,
            'nationalCode' => $nationalCode,
            'description' => 'پرداخت سفارش '.$order->order_number,
            'redirectionUrl' => $callbackUrl,
        ]);

        try {
            $driver = config("payment.map.{$validated['gateway']}");
            $settings = config("payment.drivers.{$validated['gateway']}");
            $settings['callbackUrl'] = $callbackUrl;
            $gateway = new $driver($invoice, $settings);

            $transactionId = $gateway->purchase();

            Payment::create([
                'user_id' => $request->user()?->id,
                'order_id' => $order->id,
                'transaction_id' => $transactionId,
                'amount' => $order->total_amount,
                'payment_method' => $order->payment_method,
                'gateway' => $validated['gateway'],
                'status' => 'pending',
                'description' => 'در انتظار پرداخت',
            ]);

            $paymentUrl = match ($validated['gateway']) {
                'parsian' => config('payment.drivers.parsian.apiPaymentUrl').'/'.$transactionId,
                'digipay' => 'https://mydigipay.com/digiipay-payment?token='.$transactionId,
                'kamanlend' => config('payment.drivers.kamanlend.gatewayUrl').'?token='.$transactionId,
                'smartis' => config('payment.drivers.smartis.paymentPageUrl').'?uuid='.$transactionId,
            };

            return response()->json([
                'payment_url' => $paymentUrl,
                'transaction_id' => $transactionId,
            ]);

        } catch (PurchaseFailedException $e) {
            return response()->json(['message' => 'خطا در ایجاد درخواست پرداخت: '.$e->getMessage()], 500);
        } catch (\Exception $e) {
            return response()->json(['message' => 'خطای غیرمنتظره: '.$e->getMessage()], 500);
        }
    }

    public function callback(Request $request, string $orderId, string $gateway): RedirectResponse
    {
        $order = Order::findOrFail($orderId);
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

        $notes = json_decode($order->notes, true) ?? [];
        $nationalCode = $notes['national_code'] ?? '';

        $payment = Payment::where('order_id', $order->id)
            ->where('gateway', $gateway)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $payment) {
            return redirect($frontendUrl.'/checkout');
        }

        try {
            $invoice = (new Invoice)->amount($order->total_amount)->detail([
                'nationalCode' => $nationalCode,
            ]);
            $invoice->transactionId($payment->transaction_id);

            $driver = config("payment.map.{$gateway}");
            $settings = config("payment.drivers.{$gateway}");
            $driverInstance = new $driver($invoice, $settings);

            $receipt = $driverInstance->verify();

            $payment->update([
                'status' => 'paid',
                'gateway_response' => $receipt->getReferenceId(),
                'paid_at' => now(),
            ]);

            $order->update([
                'payment_status' => 'paid',
                'status' => 'confirmed',
            ]);

            $order->addNote(
                "پرداخت موفقیت آمیز بود. کد رهگیری: {$receipt->getReferenceId()} | درگاه: {$gateway} | مبلغ: " . number_format($order->total_amount) . " تومان",
                'payment',
                true
            );

            return redirect($frontendUrl.'/confirm');

        } catch (InvalidPaymentException $e) {
            $payment->update([
                'status' => 'failed',
                'gateway_response' => $e->getMessage(),
            ]);

            $order->update(['payment_status' => 'failed']);

            $order->addNote(
                "پرداخت ناموفق بود. خطا: {$e->getMessage()} | درگاه: {$gateway}",
                'payment'
            );

            return redirect($frontendUrl.'/checkout');

        } catch (\Exception $e) {
            return redirect($frontendUrl.'/checkout');
        }
    }

    public function status(Request $request, int $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $payment = Payment::where('order_id', $order->id)
            ->latest()
            ->first();

        return response()->json([
            'order_status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment' => $payment ? [
                'gateway' => $payment->gateway,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'paid_at' => $payment->paid_at,
            ] : null,
        ]);
    }
}
