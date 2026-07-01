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
use Shetabit\Multipay\Payment as ShetabitPayment;

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
            $paymentConfig = config('payment');
            $payment = new ShetabitPayment($paymentConfig);
            $payment->via($validated['gateway']);

            $capturedTransactionId = null;

            $form = $payment->callbackUrl($callbackUrl)
                ->purchase($invoice, function ($driver, $transactionId) use (&$capturedTransactionId, $request, $order, $validated) {
                    $capturedTransactionId = $transactionId;

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
                })
                ->pay();

            return response()->json([
                'payment_url' => $form->getAction(),
                'transaction_id' => $capturedTransactionId,
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

        $payment = Payment::where('order_id', $order->id)
            ->where('gateway', $gateway)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $payment) {
            $existingPaid = Payment::where('order_id', $order->id)
                ->where('gateway', $gateway)
                ->where('status', 'paid')
                ->exists();

            return redirect($existingPaid ? $frontendUrl.'/confirm?order_id='.$order->id : $frontendUrl.'/checkout');
        }

        try {
            $paymentConfig = config('payment');
            $shetabitPayment = new ShetabitPayment($paymentConfig);
            $shetabitPayment->via($gateway);

            $receipt = $shetabitPayment
                ->amount($order->total_amount)
                ->transactionId($payment->transaction_id)
                ->verify();

            $payment->update([
                'status' => 'paid',
                'gateway_response' => [
                    'reference_id' => $receipt->getReferenceId(),
                    'details' => $receipt->getDetails(),
                ],
                'paid_at' => now(),
            ]);

            $order->update([
                'payment_status' => 'paid',
                'status' => 'confirmed',
            ]);

            $order->addNote(
                "پرداخت موفقیت آمیز بود. کد رهگیری: {$receipt->getReferenceId()} | درگاه: {$gateway} | مبلغ: ".number_format($order->total_amount).' تومان',
                'payment',
                true
            );

            return redirect($frontendUrl.'/confirm?order_id='.$order->id);

        } catch (InvalidPaymentException $e) {
            $payment->update([
                'status' => 'failed',
                'gateway_response' => ['error' => $e->getMessage()],
            ]);

            $order->update(['payment_status' => 'failed']);

            $order->addNote(
                "پرداخت ناموفق بود. خطا: {$e->getMessage()} | درگاه: {$gateway}",
                'payment'
            );

            return redirect($frontendUrl.'/checkout?payment=failed');

        } catch (\Exception $e) {
            return redirect($frontendUrl.'/checkout?payment=error');
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
