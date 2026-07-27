<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateFeeRequest;
use App\Http\Requests\InitPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Cart;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
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
        $user = $request->user();

        $baseTotal = 0;
        $totalWeight = 0;
        foreach ($validated['items'] as $item) {
            $product = Product::findOrFail($item['product_id']);
            $baseTotal += $product->price * $item['quantity'];
            $totalWeight += ($product->weight ?? 0) * $item['quantity'];
        }

        $address = empty($validated['user_address_id']) ? null : $user->addresses()->find($validated['user_address_id']);
        $shippingCost = $this->calculateShippingCost(
            $validated['shipping_method_id'],
            $totalWeight,
            $baseTotal,
            $address?->province_id,
            $address?->city_id
        );

        $baseTotal += $shippingCost;

        $isInstallment = InstallmentService::isInstallmentGateway($validated['gateway']);
        $feeAmount = 0;

        if ($isInstallment) {
            $feeAmount = InstallmentService::calculateFee((int) $baseTotal);
        }

        return response()->json([
            'base_total' => (int) $baseTotal,
            'shipping_cost' => $shippingCost,
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

        $nationalCode = $order->user?->national_code ?? '';
        $phone = $order->address?->receiver_phone ?? $order->user?->phone ?? '';
        $callbackUrl = route('payment.callback', ['orderId' => $order->id, 'gateway' => $validated['gateway']]);
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $redirectToFrontend = $frontendUrl.'/confirm?order_id='.$order->id;

        $invoice = (new Invoice)->amount($order->total_amount)->detail([
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
            'phone' => $phone,
            'nationalCode' => $nationalCode,
            'description' => 'پرداخت سفارش '.$order->order_number,
            'redirectionUrl' => $redirectToFrontend,
        ]);

        Log::channel('payment')->info('Payment init started', [
            'order_id' => $order->id,
            'gateway' => $validated['gateway'],
            'total_amount' => $order->total_amount,
            'phone' => $phone,
            'nationalCode' => $nationalCode,
            'callbackUrl' => $callbackUrl,
            'redirectToFrontend' => $redirectToFrontend,
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
                        'user_id' => $order->user_id,
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
        Log::channel('payment')->info('Payment callback received', [
            'order_id' => $orderId,
            'gateway' => $gateway,
            'method' => $request->method(),
            'query' => $request->query(),
            'post' => $request->post(),
        ]);

        $order = Order::findOrFail($orderId);
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        try {
            $payment = DB::transaction(function () use ($order, $gateway) {

                $payment = Payment::where('order_id', $order->id)
                    ->where('gateway', $gateway)
                    ->whereIn('status', ['pending', 'processing'])
                    ->latest()
                    ->lockForUpdate()
                    ->first();

                if ($payment && $payment->status === 'pending') {
                    $payment->update([
                        'status' => 'processing',
                    ]);
                }

                return $payment;
            });
        } catch (\Throwable $e) {
            Log::channel('payment')->error('Payment callback lock/lookup exception', [
                'order_id' => $order->id,
                'gateway' => $gateway,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect($frontendUrl.'/payment-failed');
        }

        if (! $payment) {

            $existingPaid = Payment::where('order_id', $order->id)
                ->where('gateway', $gateway)
                ->where('status', 'paid')
                ->exists();

            Log::channel('payment')->warning('Payment callback without pending payment', [
                'order_id' => $order->id,
                'gateway' => $gateway,
                'already_paid' => $existingPaid,
            ]);

            return redirect(
                $existingPaid
                    ? $frontendUrl.'/confirm?order_id='.$order->id
                    : $frontendUrl.'/checkout'
            );
        }

        Log::channel('payment')->info('Starting payment verify', [
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'gateway' => $gateway,
            'transaction_id' => $payment->transaction_id,
            'amount' => $order->total_amount,
        ]);

        $receipt = null;

        try {
            if ($gateway === 'parsian') {
                $parsianStatus = $request->input('status');
                if ($parsianStatus !== null && $parsianStatus !== '0') {
                    throw new InvalidPaymentException(
                        'پرداخت تکمیل نشد (کد وضعیت درگاه پارسیان: '.$parsianStatus.')'
                    );
                }
            }

            $receipt = (new ShetabitPayment(config('payment')))
                ->via($gateway)
                ->amount($order->total_amount)
                ->transactionId($payment->transaction_id)
                ->verify();

            Log::channel('payment')->info('Payment verify success', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'reference_id' => $receipt->getReferenceId(),
                'details' => $receipt->getDetails(),
            ]);

        } catch (InvalidPaymentException $e) {

            Log::channel('payment')->warning('Payment verify failed', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'gateway' => $gateway,
                'transaction_id' => $payment->transaction_id,
                'message' => $e->getMessage(),
            ]);

            $payment->update([
                'status' => 'failed',
                'gateway_response' => [
                    'error' => $e->getMessage(),
                ],
            ]);

            $order->update([
                'payment_status' => 'failed',
            ]);

            $order->addNote(
                "پرداخت ناموفق بود. خطا: {$e->getMessage()} | درگاه: {$gateway}",
                'payment'
            );

            return redirect($frontendUrl.'/payment-failed');

        } catch (\Throwable $e) {

            Log::channel('payment')->error('Payment verify exception', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'gateway' => $gateway,
                'transaction_id' => $payment->transaction_id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $payment->update([
                'status' => 'failed',
                'gateway_response' => [
                    'error' => $e->getMessage(),
                ],
            ]);

            $order->update([
                'payment_status' => 'failed',
            ]);

            $order->addNote(
                "خطای غیرمنتظره هنگام تایید پرداخت. خطا: {$e->getMessage()} | درگاه: {$gateway}",
                'payment'
            );

            return redirect($frontendUrl.'/payment-failed');
        }

        try {
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
                    "پرداخت موفقیت آمیز بود. کد رهگیری: {$receipt->getReferenceId()} | درگاه: {$gateway} | مبلغ: " .
                    number_format($order->total_amount / 10) .
                    ' تومان',
                    'payment',
                    true
                );

                Cart::where('user_id', $order->user_id)->delete();
            });

        } catch (\Throwable $e) {
            Log::channel('payment')->critical('Payment verified successfully but post-processing failed - needs manual review', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'gateway' => $gateway,
                'reference_id' => $receipt->getReferenceId(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            try {
                $payment->forceFill([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'gateway_response' => [
                        'reference_id' => $receipt->getReferenceId(),
                        'details' => $receipt->getDetails(),
                    ],
                ])->saveQuietly();

                $order->forceFill([
                    'payment_status' => 'paid',
                    'status' => 'confirmed',
                ])->saveQuietly();

                Cart::where('user_id', $order->user_id)->delete();

            } catch (\Throwable $inner) {
                Log::channel('payment')->critical('Failed to persist paid status after verify success - MANUAL FIX REQUIRED', [
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'gateway' => $gateway,
                    'reference_id' => $receipt->getReferenceId(),
                    'message' => $inner->getMessage(),
                ]);
            }
        }

        return redirect($frontendUrl.'/confirm?order_id='.$order->id);
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

    private function calculateShippingCost(int $shippingMethodId, float $totalWeight, int $cartTotal, ?int $provinceId, ?int $cityId): int
    {
        $method = ShippingMethod::active()->with('rates')->find($shippingMethodId);

        if (! $method || $method->rates->isEmpty() || $method->is_pickup) {
            return 0;
        }

        $rate = $this->findMatchingRate($method, $totalWeight, $cartTotal, $provinceId, $cityId);

        if (! $rate) {
            return 0;
        }

        $shippingCost = (int) $rate->base_rate;

        if ($rate->per_kg_rate && $totalWeight > 0) {
            $extraKg = max(0, ceil($totalWeight / 1000) - 1);
            $shippingCost += $extraKg * (int) $rate->per_kg_rate;
        }

        $freeShipping = false;
        if ($rate->free_shipping_min && $cartTotal >= $rate->free_shipping_min) {
            $shippingCost = 0;
            $freeShipping = true;
        }

        $taxAmount = 0;
        if ($rate->tax_rate && $rate->tax_rate > 0 && ! $freeShipping) {
            $taxAmount = (int) round($shippingCost * $rate->tax_rate / 100);
        }

        return $shippingCost + $taxAmount;
    }

    private function findMatchingRate(ShippingMethod $method, float $totalWeight, int $cartTotal, ?int $provinceId, ?int $cityId): ?ShippingRate
    {
        $rates = $method->rates;

        if ($rates->isEmpty()) {
            return null;
        }

        $rateType = $rates->first()->rate_type;

        return match ($rateType) {
            'flat' => $rates->first(),
            'weight' => $rates
                ->filter(fn (ShippingRate $r) => $r->min_weight <= $totalWeight && (! $r->max_weight || $totalWeight <= $r->max_weight))
                ->sortBy('min_weight')
                ->first(),
            'province' => $rates
                ->filter(fn (ShippingRate $r) => is_null($r->province_id) || (int) $r->province_id === (int) $provinceId)
                ->first(),
            'city' => $rates
                ->filter(fn (ShippingRate $r) => is_null($r->city_id) || (int) $r->city_id === (int) $cityId)
                ->first(),
            'cart_total' => $rates
                ->filter(fn (ShippingRate $r) => $r->min_cart_total <= $cartTotal && (! $r->max_cart_total || $cartTotal <= $r->max_cart_total))
                ->sortBy('min_cart_total')
                ->first(),
            default => $rates->first(),
        };
    }
}
