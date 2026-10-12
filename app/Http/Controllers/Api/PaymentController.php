<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DuplicatePaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateFeeRequest;
use App\Http\Requests\InitPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Jobs\ReconcilePaidSerialOrder;
use App\Models\Cart;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Payment\Exceptions\AmbiguousGatewayOutcomeException;
use App\Payment\Exceptions\TerminalPaymentRejectedException;
use App\Services\InstallmentService;
use App\Services\Inventory\AvailabilityCheck;
use App\Services\Inventory\SerialInventoryService;
use App\Services\Inventory\SerialStockShortageException;
use App\Support\Platform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Product\Models\Product;
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

        $isFeeGateway = InstallmentService::isFeeGateway($validated['gateway']);
        $feeAmount = 0;

        if ($isFeeGateway) {
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

        if ($order->platform !== Platform::TAPSI && (! config('serial_inventory.enabled', false) || ! $order->serial_inventory_enabled)) {
            return response()->json(['message' => 'پرداخت موقتاً در دسترس نیست.', 'error_code' => 'INVENTORY_NOT_READY'], 503);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'سفارش قبلاً پرداخت شده', 'error_code' => 'ORDER_ALREADY_PAID'], 422);
        }

        if (in_array($order->status, ['cancelled', 'expired'])) {
            return response()->json(['message' => 'سفارش لغو شده یا منقضی شده است', 'error_code' => 'ORDER_NOT_ACTIVE'], 422);
        }

        $existingPending = Payment::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'processing', 'initiating'])
            ->first();

        if ($existingPending) {
            return response()->json(['message' => 'درخواست پرداخت قبلاً ثبت شده است', 'error_code' => 'PAYMENT_ALREADY_INITIATED'], 422);
        }

        if ($validated['gateway'] === 'nopay') {
            $order->load('items.product:id,is_nopay');

            if ($order->items->contains(fn ($item) => ! $item->product?->is_nopay)) {
                return response()->json([
                    'message' => 'برخی محصولات سفارش برای پرداخت اقساطی نوپی در دسترس نیستند',
                    'error_code' => 'PRODUCT_NOT_ELIGIBLE_FOR_NOPAY',
                ], 422);
            }
        }

        $stockCheck = $this->validateOrderStock($order);
        if (! $stockCheck['valid']) {
            return response()->json([
                'message' => 'موجودی برخی محصولات کافی نیست: '.$stockCheck['product'],
                'error_code' => 'INSUFFICIENT_STOCK',
                'products' => $stockCheck['items'] ?? [],
            ], 422);
        }

        $nationalCode = $order->user?->national_code ?? '';
        $phone = $order->user?->phone ?? '';

        // Nopay gateway requires the return URL to match the merchant panel's
        // registered URL1 (Silent Response) exactly — no orderId/gateway suffix.
        $callbackUrl = $validated['gateway'] === 'nopay'
            ? config('payment.drivers.nopay.callbackUrl') ?: route('payment.callback', ['orderId' => $order->id, 'gateway' => 'nopay'])
            : route('payment.callback', ['orderId' => $order->id, 'gateway' => $validated['gateway']]);

        $frontendUrl = $this->frontendUrlFor($validated['gateway']);
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

        // Atomic claim + serial/capacity reservation before touching the
        // gateway. A second concurrent init blocks on the order lock and then
        // observes the initiating payment below.
        try {
            $claimed = DB::transaction(function () use ($order, $validated) {
                $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

                if ($lockedOrder->payment_status === 'paid' || in_array($lockedOrder->status, ['cancelled', 'expired'], true)) {
                    throw new \DomainException('ORDER_NOT_ACTIVE');
                }

                $order->setRawAttributes($lockedOrder->getAttributes(), true);

                $race = Payment::where('order_id', $order->id)
                    ->whereIn('status', ['pending', 'processing', 'initiating'])
                    ->lockForUpdate()
                    ->first();

                if ($race) {
                    throw new DuplicatePaymentException;
                }

                $payment = Payment::create([
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'platform' => $order->platform,
                    'transaction_id' => 'init-'.Str::ulid(),
                    'amount' => $order->total_amount,
                    'payment_method' => $order->payment_method,
                    'gateway' => $validated['gateway'],
                    'status' => 'initiating',
                    'description' => 'شروع پرداخت',
                ]);

                app(SerialInventoryService::class)->reserveForPayment($order->id, $order->platform, $payment->id);

                return $payment;
            });
        } catch (\DomainException) {
            return response()->json(['message' => 'سفارش برای پرداخت فعال نیست.', 'error_code' => 'ORDER_NOT_ACTIVE'], 422);
        } catch (DuplicatePaymentException) {
            return response()->json(['message' => 'درخواست پرداخت قبلاً ثبت شده است', 'error_code' => 'PAYMENT_ALREADY_INITIATED'], 422);
        } catch (SerialStockShortageException $e) {
            $shortProduct = $e->getProductId() !== null ? Product::find($e->getProductId()) : null;
            $label = $shortProduct
                ? "{$shortProduct->name}: موجودی {$e->getAvailable()}، درخواست {$e->getNeeded()}"
                : 'موجودی کافی نیست';

            return response()->json([
                'message' => 'موجودی برخی محصولات کافی نیست: '.$label,
                'error_code' => 'INSUFFICIENT_STOCK',
                'products' => [[
                    'product_id' => $e->getProductId(),
                    'product_name' => $shortProduct?->name ?? '',
                    'requested' => $e->getNeeded(),
                    'available' => $e->getAvailable(),
                ]],
            ], 422);
        }

        $invoice->amount($claimed->amount);

        try {
            $paymentConfig = config('payment');
            $payment = new ShetabitPayment($paymentConfig);
            $payment->via($validated['gateway']);

            $capturedTransactionId = null;

            $form = $payment->callbackUrl($callbackUrl)
                ->purchase($invoice, function ($driver, $transactionId) use (&$capturedTransactionId, $claimed) {
                    $capturedTransactionId = $transactionId;

                    DB::transaction(function () use ($claimed, $transactionId): void {
                        Order::withoutTenantScope()->whereKey($claimed->order_id)->lockForUpdate()->firstOrFail();
                        $attempt = Payment::withoutTenantScope()->whereKey($claimed->id)->lockForUpdate()->firstOrFail();

                        if ($attempt->status === 'initiating') {
                            $attempt->update(['transaction_id' => $transactionId, 'status' => 'pending', 'description' => 'در انتظار پرداخت']);
                        }
                    });
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

        } catch (\Throwable $e) {
            if ($e instanceof TerminalPaymentRejectedException && $e->gateway === $claimed->gateway) {
                $this->failPaymentAttempt($order, $claimed, $e);

                return response()->json(['message' => 'درگاه درخواست پرداخت را نپذیرفت.', 'error_code' => 'PAYMENT_FAILED'], 500);
            }

            Log::channel('payment')->error('Payment init Exception', [
                'order_id' => $order->id,
                'gateway' => $validated['gateway'],
                'total_amount' => $order->total_amount,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Ambiguous outcome (timeout/crash between purchase and token
            // save): the initiating claim and its holdings intentionally stay
            // for review/expiry, and retries stay blocked on the claim.
            DB::transaction(function () use ($order, $claimed, $e): void {
                $locked = Order::withoutTenantScope()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                $attempt = Payment::withoutTenantScope()->whereKey($claimed->id)->lockForUpdate()->firstOrFail();

                if ($attempt->status === 'initiating') {
                    $attempt->update(['gateway_response' => $this->gatewayFailureDetails($e)]);
                }

                if ($attempt->status === 'pending') {
                    $attempt->update(['status' => 'processing', 'gateway_response' => array_merge($this->gatewayFailureDetails($e), ['verification_retryable' => true])]);
                }

                if ($locked->serial_inventory_enabled && $locked->payment_status !== 'paid') {
                    $locked->update(['serial_fulfillment_status' => 'needs_review']);
                    $locked->addNote('نتیجه ایجاد پرداخت نامشخص است؛ رزرو تا تعیین تکلیف باقی می‌ماند.', 'payment');
                }
            });

            return response()->json(['message' => 'نتیجه ایجاد پرداخت نامشخص است.', 'error_code' => 'PAYMENT_ERROR'], 500);
        }
    }

    public function callbackByToken(Request $request): RedirectResponse
    {
        $token = $request->input('tv') ?? $request->input('Token') ?? $request->input('token') ?? null;

        Log::channel('payment')->info('Payment callback (bare) received', [
            'method' => $request->method(),
            'query' => $request->query(),
            'post' => $request->post(),
        ]);

        if (! $token) {
            return redirect(config('app.frontend_url', 'http://localhost:3000').'/checkout');
        }

        $payment = Payment::withoutTenantScope()
            ->where('transaction_id', $token)
            ->whereIn('status', ['pending', 'processing'])
            ->latest('id')
            ->first();

        if (! $payment) {
            Log::channel('payment')->warning('Payment callback (bare) with unknown token', [
                'token' => $token,
            ]);

            return redirect(config('app.frontend_url', 'http://localhost:3000').'/checkout');
        }

        return $this->callback($request, (string) $payment->order_id, $payment->gateway);
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

        // Callback is a public gateway redirect (no X-Platform header):
        // must resolve the order regardless of platform.
        $order = Order::withoutTenantScope()->findOrFail($orderId);
        $frontendUrl = $this->frontendUrlFor($gateway);

        $callbackTransactionId = $request->input('Token') ?? $request->input('token')
            ?? $request->input('uuid') ?? $request->input('tv') ?? null;

        $resolvedPayment = null;

        if ($callbackTransactionId) {
            // Callback is a public route (no X-Platform header): scope by the
            // order's platform instead of the request header.
            $resolvedPayment = Payment::withoutTenantScope()
                ->where('transaction_id', $callbackTransactionId)
                ->where('order_id', $order->id)
                ->where('platform', $order->platform)
                ->where('gateway', $gateway)
                ->first();

            if ($resolvedPayment && ! in_array($resolvedPayment->status, ['pending', 'processing'])) {
                return redirect($frontendUrl.'/confirm?order_id='.$order->id);
            }

            if (! $resolvedPayment) {
                Log::channel('payment')->warning('Payment callback with unknown transaction_id', [
                    'order_id' => $order->id,
                    'gateway' => $gateway,
                    'callback_transaction_id' => $callbackTransactionId,
                ]);

                return redirect($frontendUrl.'/checkout');
            }
        }

        try {
            $payment = DB::transaction(function () use ($order, $gateway, $resolvedPayment) {
                // Shared lock order with the inventory service: order first.
                Order::withoutTenantScope()->where('id', $order->id)->lockForUpdate()->first();

                if ($resolvedPayment) {
                    $payment = Payment::withoutTenantScope()
                        ->where('id', $resolvedPayment->id)
                        ->where('gateway', $gateway)
                        ->where('order_id', $order->id)
                        ->where('platform', $order->platform)
                        ->lockForUpdate()
                        ->first();
                } else {
                    $payment = Payment::withoutTenantScope()
                        ->where('order_id', $order->id)
                        ->where('gateway', $gateway)
                        ->whereIn('status', ['pending', 'processing'])
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();
                }

                if (! $payment || ! in_array($payment->status, ['pending', 'processing'], true)) {
                    return null;
                }

                if ($payment->status === 'processing' && ! ($payment->gateway_response['verification_retryable'] ?? false)) {
                    return null;
                }

                $payment->update(['status' => 'processing', 'gateway_response' => ['verification_retryable' => false]]);

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
            $receipt = (new ShetabitPayment(config('payment')))
                ->via($gateway)
                ->amount($payment->amount)
                ->transactionId($payment->transaction_id)
                ->verify();

            Log::channel('payment')->info('Payment verify success', [
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'reference_id' => $receipt->getReferenceId(),
                'details' => $receipt->getDetails(),
            ]);

        } catch (\Throwable $e) {
            if ($e instanceof TerminalPaymentRejectedException && $e->gateway === $payment->gateway) {
                $this->failPaymentAttempt($order, $payment, $e);
                $order->refresh();

                return redirect($order->payment_status === 'paid' ? $frontendUrl.'/confirm?order_id='.$order->id : $frontendUrl.'/payment-failed');
            }

            Log::channel('payment')->error('Payment verification outcome ambiguous', ['payment_id' => $payment->id, 'order_id' => $order->id, 'message' => $e->getMessage()]);
            DB::transaction(function () use ($order, $payment, $e): void {
                $locked = Order::withoutTenantScope()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                $attempt = Payment::withoutTenantScope()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

                if ($attempt->status !== 'paid') {
                    $attempt->update(['status' => 'processing', 'gateway_response' => array_merge($this->gatewayFailureDetails($e), ['verification_retryable' => true])]);

                    if ($locked->payment_status !== 'paid' && $locked->serial_inventory_enabled) {
                        $locked->update(['serial_fulfillment_status' => 'needs_review']);
                    }
                }
            });

            return redirect($frontendUrl.'/confirm?order_id='.$order->id);
        }

        $stockCheck = $this->validateOrderStock($order);
        if (! $stockCheck['valid']) {
            Log::channel('payment')->warning('Payment verified successfully but stock insufficient - order confirmed anyway', [
                'order_id' => $order->id,
                'product' => $stockCheck['product'],
            ]);

            $order->addNote("پرداخت موفق بود ولی موجودی کافی نیست و نیاز به بررسی دارد: {$stockCheck['message']}", 'payment');
        }

        try {
            DB::transaction(function () use ($payment, $order, $receipt, $gateway) {
                // Re-read under lock: the order may have been cancelled while
                // the customer was paying. A late payment records the money
                // but never reactivates a cancelled order by itself.
                $locked = Order::withoutTenantScope()->where('id', $order->id)->lockForUpdate()->firstOrFail();
                $attempt = Payment::withoutTenantScope()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                $wasCancelled = $locked->status === 'cancelled';

                $attempt->update([
                    'status' => 'paid',
                    'gateway_response' => [
                        'reference_id' => $receipt->getReferenceId(),
                        'details' => $receipt->getDetails(),
                    ],
                    'paid_at' => now(),
                ]);

                if ($wasCancelled) {
                    $locked->update([
                        'payment_status' => 'paid',
                    ]);

                    $locked->addNote(
                        "پرداخت دیررس برای سفارش لغوشده ثبت شد؛ فعال‌سازی خودکار انجام نشد و رسیدگی دستی لازم است. کد رهگیری: {$receipt->getReferenceId()} | درگاه: {$gateway}",
                        'payment'
                    );
                } else {
                    $locked->update([
                        'payment_status' => 'paid',
                        'status' => 'confirmed',
                    ]);

                    $locked->addNote(
                        "پرداخت موفقیت آمیز بود. کد رهگیری: {$receipt->getReferenceId()} | درگاه: {$gateway} | مبلغ: ".
                        number_format($order->total_amount / 10).
                        ' تومان',
                        'payment',
                        true
                    );
                }

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
                DB::transaction(function () use ($payment, $order, $receipt): void {
                    $order = Order::withoutTenantScope()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                    $payment = Payment::withoutTenantScope()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

                    $wasCancelled = $order->status === 'cancelled';

                    $payment->forceFill([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'gateway_response' => [
                            'reference_id' => $receipt->getReferenceId(),
                            'details' => $receipt->getDetails(),
                        ],
                    ])->saveQuietly();

                    $order->forceFill(array_merge(
                        ['payment_status' => 'paid'],
                        $wasCancelled ? [] : ['status' => 'confirmed'],
                    ))->saveQuietly();

                    Cart::where('user_id', $order->user_id)->delete();
                });

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

        $this->finalizeSerialFulfillment($order->fresh() ?? $order, $gateway);

        return redirect($frontendUrl.'/confirm?order_id='.$order->id);
    }

    /** @return array<string, mixed> */
    private function gatewayFailureDetails(\Throwable $error): array
    {
        if ($error instanceof AmbiguousGatewayOutcomeException) {
            return ['http_status' => $error->httpStatus, 'provider_code' => $error->providerCode, 'provider_response' => $error->providerResponse];
        }

        if ($error instanceof TerminalPaymentRejectedException) {
            return ['provider_code' => $error->providerCode, 'provider_evidence' => $error->providerEvidence, 'terminal_rejection' => true];
        }

        return ['error' => $error->getMessage(), 'exception_code' => $error->getCode()];
    }

    private function failPaymentAttempt(Order $order, Payment $payment, \Throwable $error): void
    {
        DB::transaction(function () use ($order, $payment, $error): void {
            $locked = Order::withoutTenantScope()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $attempt = Payment::withoutTenantScope()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($attempt->status === 'paid') {
                return;
            }

            $attempt->update(['status' => 'failed', 'gateway_response' => $this->gatewayFailureDetails($error)]);

            if ($locked->payment_status !== 'paid') {
                $locked->update(['payment_status' => 'failed']);
                app(SerialInventoryService::class)->releaseUnpaidHoldings($locked->id, $locked->platform, $attempt->id, null, 'پرداخت ناموفق');
            }
        });
    }

    private function finalizeSerialFulfillment(Order $order, string $gateway): void
    {
        if ($order->platform === Platform::TAPSI) {
            return;
        }

        if ($order->status === 'cancelled') {
            $order->update(['serial_fulfillment_status' => 'needs_review']);

            return;
        }

        try {
            app(SerialInventoryService::class)->finalizePaidOrder($order->id, $order->platform);
        } catch (\Throwable $e) {
            Log::channel('payment')->critical('Serial finalize failed after paid truth - needs manual review', [
                'order_id' => $order->id,
                'gateway' => $gateway,
                'message' => $e->getMessage(),
            ]);

            $order->update(['serial_fulfillment_status' => 'needs_review']);
        }

        ReconcilePaidSerialOrder::dispatch($order->id, $order->platform)->afterCommit();
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
        if ($rate->free_shipping_max && $cartTotal <= $rate->free_shipping_max) {
            $shippingCost = 0;
            $freeShipping = true;
        } elseif ($rate->free_shipping_min && $cartTotal >= $rate->free_shipping_min) {
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

    private function validateOrderStock(Order $order): array
    {
        $order->load('items.product');

        $lines = [];

        foreach ($order->items as $item) {
            $lines[] = [
                'product' => $item->product,
                'product_id' => $item->product_id,
                'quantity' => (int) $item->quantity,
            ];
        }

        return AvailabilityCheck::check($lines, $order->platform, $order);
    }

    private function frontendUrlFor(string $gateway): string
    {
        return $gateway === 'nopay'
            ? config('app.nopay_frontend_url', 'https://pay.sawiss.com')
            : config('app.frontend_url', 'http://localhost:3000');
    }
}
