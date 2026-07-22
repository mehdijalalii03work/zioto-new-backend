<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateFeeRequest;
use App\Http\Requests\InitPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Cart;
use App\Services\InstallmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Exceptions\PurchaseFailedException;
use Shetabit\Multipay\Invoice;
use Shetabit\Multipay\Payment as ShetabitPayment;

class PaymentController extends Controller
{
    public function calculateFee(CalculateFeeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $baseTotal = 0;
        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $baseTotal += $product->price * $item['quantity'];
        }
        $baseTotal += $validated['shipping_cost'];

        $isInstallment = InstallmentService::isInstallmentGateway($validated['gateway']);
        $feeAmount = 0;

        if ($isInstallment) {
            $feeAmount = InstallmentService::calculateFee((int) $baseTotal);
        }

        return response()->json([
            'base_total' => (int) $baseTotal,
            'fee_amount' => $feeAmount,
            'total_with_fee' => (int) $baseTotal + $feeAmount,
        ]);
    }

    public function init(InitPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = Order::with(['user', 'address'])->findOrFail($validated['order_id']);

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'سفارش قبلاً پرداخت شده', 'error_code' => 'ORDER_ALREADY_PAID'], 422);
        }

        $nationalCode = $order->address?->receiver_national_code ?? $order->user?->national_code ?? '';
        $phone = $order->address?->receiver_phone ?? $order->user?->phone ?? '';
        $callbackUrl = route('payment.callback', ['orderId' => $order->id, 'gateway' => $validated['gateway']]);

        $invoice = (new Invoice)->amount($order->total_amount)->detail([
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
            'phone' => $phone,
            'nationalCode' => $nationalCode,
            'description' => 'پرداخت سفارش '.$order->order_number,
            'redirectionUrl' => $callbackUrl,
        ]);

        Log::channel('payment')->info('Payment init started', [
            'order_id' => $order->id,
            'gateway' => $validated['gateway'],
            'total_amount' => $order->total_amount,
            'phone' => $phone,
            'nationalCode' => $nationalCode,
            'callbackUrl' => $callbackUrl,
        ]);

        try {
            $paymentConfig = config('payment');
            $payment = new ShetabitPayment($paymentConfig);
            $payment->via($validated['gateway']);

            $capturedTransactionId = null;

            $form = $payment->callbackUrl($callbackUrl)
                ->purchase($invoice, function ($driver, $transactionId) use (&$capturedTransactionId, $request, $order, $validated, $nationalCode) {
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
                        'national_code' => $nationalCode,
                    ]);
                })
                ->pay();

            Log::channel('payment')->info('Payment init success', [
                'order_id' => $order->id,
                'gateway' => $validated['gateway'],
                'transaction_id' => $capturedTransactionId,
            ]);

            return response()->json([
                'payment_url' => $form->getAction(),
                'transaction_id' => $capturedTransactionId,
            ]);

        } catch (PurchaseFailedException $e) {
            Log::channel('payment')->error('Payment init PurchaseFailedException', [
                'order_id' => $order->id,
                'gateway' => $validated['gateway'],
                'total_amount' => $order->total_amount,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'خطا در ایجاد درخواست پرداخت: '.$e->getMessage(), 'error_code' => 'PAYMENT_FAILED'], 500);
        } catch (\Exception $e) {
            Log::channel('payment')->error('Payment init Exception', [
                'order_id' => $order->id,
                'gateway' => $validated['gateway'],
                'total_amount' => $order->total_amount,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'خطای غیرمنتظره: '.$e->getMessage(), 'error_code' => 'PAYMENT_ERROR'], 500);
        }
    }

    public function callback(Request $request, string $orderId, string $gateway): RedirectResponse
    {
        $order = Order::findOrFail($orderId);
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        $payment = DB::transaction(function () use ($order, $gateway) {
            $payment = Payment::where('order_id', $order->id)
                ->where('gateway', $gateway)
                ->where('status', 'pending')
                ->latest()
                ->lockForUpdate()
                ->first();

            if ($payment) {
                $payment->update(['status' => 'processing']);
            }

            return $payment;
        });

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

            DB::transaction(function () use ($payment, $order, $receipt, $gateway) {
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

                Cart::where('user_id', $order->user_id)->delete();
            });

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
            $payment->update(['status' => 'pending']);

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
            'payment' => $payment ? new PaymentResource($payment) : null,
        ]);
    }
}
