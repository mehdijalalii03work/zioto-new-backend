<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderNoteResource;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\OrderShipping;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\UserAddress;
use App\Services\DiscountCodeService;
use App\Services\InstallmentService;
use App\Services\Pricing\DynamicPriceService;
use App\Services\Pricing\SalesRestrictionService;
use App\Support\Platform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Order\Models\Order;

class OrderSubmitController extends Controller
{
    public function __construct(
        private readonly DynamicPriceService $dynamicPrice,
        private readonly SalesRestrictionService $salesRestrictions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['shipping', 'items', 'address.province', 'address.city'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'orders' => OrderResource::collection($orders),
        ]);
    }

    public function show(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with(['shipping', 'items', 'address.province', 'address.city', 'orderNotes'])
            ->where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    public function notes(Request $request, int $orderId): JsonResponse
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notes = $order->orderNotes()
            ->where('is_customer_note', true)
            ->where('type', '!=', 'hesabfa')
            ->get();

        return response()->json([
            'notes' => OrderNoteResource::collection($notes),
        ]);
    }

    public function store(StoreOrderRequest $request, DiscountCodeService $discountCodeService): JsonResponse
    {
        if (! $this->salesRestrictions->salesEnabled()) {
            return response()->json([
                'message' => $this->salesRestrictions->salesClosedMessage(),
                'error_code' => 'SALES_CLOSED',
            ], 422);
        }

        $user = $request->user();
        $validated = $request->validated();

        $cartItems = Cart::where('user_id', $user->id)
            ->with('product:id,name,price,is_nopay,weight,stock_quantity,pack_size,base_product_id,hesabfa_physical_stock,hesabfa_reserved_stock,hesabfa_manual_reserved,price_board_item,price_type,labor_coefficients,category_id,metal_type,tokeniko_sku')
            ->with('product.baseProduct:id,hesabfa_physical_stock,hesabfa_reserved_stock,hesabfa_manual_reserved,stock_quantity')
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'سبد خرید شما خالی است',
                'error_code' => 'CART_EMPTY',
            ], 422);
        }

        foreach ($cartItems as $cartItem) {
            if (($reason = $this->salesRestrictions->blockedReason($cartItem->product)) !== null) {
                return response()->json([
                    'message' => $this->salesRestrictions->humanMessage($reason),
                    'error_code' => match ($reason) {
                        'product_disabled' => 'PRODUCT_SALES_DISABLED',
                        'category_disabled' => 'CATEGORY_SALES_DISABLED',
                        'outside_hours' => 'OUTSIDE_SALES_HOURS',
                        default => 'PRODUCT_NOT_PURCHASABLE',
                    },
                ], 422);
            }
        }

        $stockCheck = $this->validateCartStock($cartItems);

        if (! $stockCheck['valid']) {
            return response()->json([
                'message' => 'موجودی برخی محصولات کافی نیست: '.$stockCheck['product'],
                'error_code' => 'INSUFFICIENT_STOCK',
                'products' => $stockCheck['items'] ?? [],
            ], 422);
        }

        if (Platform::isNopay($request) && $cartItems->contains(fn (Cart $item) => ! $item->product->is_nopay)) {
            return response()->json([
                'message' => 'برخی محصولات سبد خرید شما برای پرداخت اقساطی نوپی در دسترس نیستند',
                'error_code' => 'PRODUCT_NOT_ELIGIBLE_FOR_NOPAY',
            ], 422);
        }

        $requestedDiscountCode = filled($validated['discount_code'] ?? null)
            ? Str::upper(trim($validated['discount_code']))
            : null;
        $result = DB::transaction(function () use ($validated, $user, $cartItems, $requestedDiscountCode, $discountCodeService) {
            $address = empty($validated['user_address_id'])
                ? null
                : $user->addresses()->with(['province', 'city'])->find($validated['user_address_id']);

            if ($requestedDiscountCode !== null) {
                DiscountCode::query()
                    ->where('code', $requestedDiscountCode)
                    ->lockForUpdate()
                    ->first();
            }

            $existingOrder = $this->findExistingSimilarOrder(
                $user,
                $cartItems,
                $validated,
                $requestedDiscountCode,
                $discountCodeService,
                $address,
            );

            if ($existingOrder) {
                return ['order' => $existingOrder, 'is_existing' => true];
            }

            return $this->createOrder($validated, $user, $cartItems, $requestedDiscountCode, $discountCodeService, $address);
        }, attempts: 3);

        if (isset($result['error'])) {
            return response()->json([
                'message' => $result['error'],
                'error_code' => 'DISCOUNT_CODE_INVALID',
            ], 422);
        }

        if ($result['is_existing'] ?? false) {
            return response()->json([
                'message' => 'سفارش مشابه قبلی شما برگردانده شد',
                'order' => new OrderResource($result['order']->load(['shipping', 'items'])),
                'is_existing' => true,
            ]);
        }

        return response()->json([
            'message' => 'سفارش با موفقیت ثبت شد',
            'order' => new OrderResource($result['order']),
        ], 201);
    }

    private function validateCartStock($cartItems): array
    {
        if (! config('hesabfa.enable_reserved_stock', false)) {
            return ['valid' => true];
        }

        $insufficient = [];
        $items = [];

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->product;
            if (! $product) {
                continue;
            }

            $sellable = $product->sellable_stock;

            if ($cartItem->quantity > $sellable) {
                $insufficient[] = "{$product->name}: موجودی {$sellable}، درخواست {$cartItem->quantity}";
                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'requested' => (int) $cartItem->quantity,
                    'available' => $sellable,
                ];
            }
        }

        if (! empty($insufficient)) {
            return [
                'valid' => false,
                'message' => 'موجودی برخی محصولات کافی نیست: '.implode(' | ', $insufficient),
                'product' => implode(', ', $insufficient),
                'items' => $items,
            ];
        }

        return ['valid' => true];
    }

    private function createOrder(
        array $validated,
        $user,
        $cartItems,
        ?string $requestedDiscountCode,
        DiscountCodeService $discountCodeService,
        ?UserAddress $address,
    ): array {
        $discountCode = null;
        if ($requestedDiscountCode !== null) {
            $discountCodeResult = $discountCodeService->validate(
                $requestedDiscountCode,
                $user,
                Platform::fromRequest(),
                lock: true,
            );

            if ($discountCodeResult['error']) {
                return ['error' => $discountCodeResult['error']];
            }

            $discountCode = $discountCodeResult['code'];
        }

        $orderData = $this->calculateOrderData(
            $cartItems,
            $validated,
            $address,
            (int) ($discountCode?->discount_amount ?? 0),
            (int) $user->id,
        );
        $notesData = [
            'name' => $address?->receiver_name ?: $user->name,
            'phone' => $user->phone,
            'national_code' => $user->national_code,
        ];

        if ($orderData['installment_fee'] > 0) {
            $notesData['installment_fee'] = $orderData['installment_fee'];
        }

        $order = Order::create([
            'user_id' => $user->id,
            'platform' => Platform::fromRequest(),
            'status' => 'pending',
            'total_amount' => $orderData['total_amount'],
            'discount_code_id' => $discountCode?->id,
            'discount_code_snapshot' => $discountCode?->code,
            'discount_amount' => $orderData['discount_amount'],
            'payment_method' => $orderData['payment_method'],
            'payment_status' => 'pending',
            'user_address_id' => $validated['user_address_id'] ?? null,
            'notes' => json_encode($notesData),
        ]);

        foreach ($orderData['items'] as $orderItem) {
            $order->items()->create($orderItem);
        }

        $discountCodeService->reserveUsage($order);

        $method = ShippingMethod::find($validated['shipping_method_id']);
        OrderShipping::create([
            'order_id' => $order->id,
            'shipping_method_id' => $validated['shipping_method_id'],
            'shipping_method_name' => $method?->name ?? '',
            'shipping_cost' => $orderData['shipping_cost'],
            'tax_amount' => $orderData['tax_amount'],
            'tax_rate' => $orderData['tax_rate'],
        ]);

        return ['order' => $order->load(['shipping', 'items']), 'is_existing' => false];
    }

    private function calculateOrderData(
        $cartItems,
        array $validated,
        ?object $address = null,
        int $requestedDiscountAmount = 0,
        ?int $userId = null,
    ): array {
        $baseAmount = 0;
        $orderItems = [];

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->product;
            $unitPrice = $this->dynamicPrice->priceFor($product, $userId) ?? (float) $product->price;
            $subtotal = $unitPrice * $cartItem->quantity;
            $baseAmount += $subtotal;

            $priceBoardItem = $product->price_board_item;
            $weight = $product->weight;
            $boardPrice = null;
            $laborCoefficient = null;
            $laborCost = null;
            $taxes = null;
            $finalPrice = $unitPrice;

            if ($this->dynamicPrice->tokenikoPriceFor($product) === null
                && $priceBoardItem && $weight > 0 && $this->dynamicPrice->isEligibleProduct($product)) {
                $details = $this->dynamicPrice->detailsFor($product);
                if ($details) {
                    $boardPrice = $details['base_price'];
                    $weight = (float) $weight;
                    $laborCoefficient = $details['coefficient'];
                    $laborCost = $details['coefficient'] * $weight * $details['base_price'] * $product->pack_size;
                    $taxes = $details['taxes'] ?? [];
                    $finalPrice = $details['calculated_price'];
                }
            }

            $orderItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_price' => $unitPrice,
                'quantity' => $cartItem->quantity,
                'subtotal' => $subtotal,
                'price_board_item' => $priceBoardItem,
                'board_price' => $boardPrice,
                'weight' => $weight,
                'labor_coefficient' => $laborCoefficient,
                'labor_cost' => $laborCost,
                'taxes' => $taxes,
                'final_price' => $finalPrice,
            ];
        }

        $shippingResult = $this->calculateShippingCost(
            $validated['shipping_method_id'],
            $cartItems,
            $baseAmount,
            $address?->province_id,
            $address?->city_id
        );

        $gateway = $validated['gateway'] ?? 'parsian';
        $paymentMethod = InstallmentService::isInstallmentGateway($gateway)
            ? (InstallmentService::isFeeGateway($gateway) ? 'installment' : 'installment_nofee')
            : 'online';

        $discountAmount = min(max(0, $requestedDiscountAmount), $baseAmount + $shippingResult['total']);
        $totalAmount = $baseAmount + $shippingResult['total'] - $discountAmount;
        $installmentFee = 0;

        if (InstallmentService::isFeeGateway($gateway)) {
            $installmentFee = InstallmentService::calculateFee((int) $totalAmount);
            $totalAmount += $installmentFee;
        }

        return [
            'items' => $orderItems,
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'payment_method' => $paymentMethod,
            'installment_fee' => $installmentFee,
            'shipping_cost' => $shippingResult['total'],
            'tax_amount' => $shippingResult['tax_amount'],
            'tax_rate' => $shippingResult['tax_rate'],
        ];
    }

    private function findExistingSimilarOrder(
        $user,
        $cartItems,
        array $validated,
        ?string $requestedDiscountCode,
        DiscountCodeService $discountCodeService,
        ?UserAddress $address,
    ): ?Order {
        $cartItemData = collect($cartItems)->map(fn (Cart $item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'product_price' => (int) ($this->dynamicPrice->priceFor($item->product, (int) $user->id) ?? $item->product->price),
        ])->sortBy('product_id')->values()->toArray();

        $pendingOrders = Order::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereIn('payment_status', ['pending', 'failed'])
            ->where('created_at', '>=', now()->subMinutes(20))
            ->with(['items' => function ($query) {
                $query->select('id', 'order_id', 'product_id', 'quantity', 'product_price');
            }])
            ->get();

        foreach ($pendingOrders as $order) {
            $orderItemData = $order->items
                ->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'product_price' => (int) $item->product_price,
                ])
                ->sortBy('product_id')
                ->values()
                ->toArray();

            if ($cartItemData === $orderItemData) {
                if ($order->discount_code_snapshot !== $requestedDiscountCode) {
                    continue;
                }

                if ($order->discount_code_id && (int) $order->discount_amount > 0) {
                    $discountCode = DiscountCode::query()
                        ->whereKey($order->discount_code_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $discountCode) {
                        continue;
                    }

                    $validation = $discountCodeService->validate(
                        $order->discount_code_snapshot,
                        $user,
                        (string) $order->platform,
                        lock: true,
                        excludeOrderId: $order->id,
                    );

                    if ($validation['error'] || ! $validation['code'] || $validation['code']->id !== $discountCode->id) {
                        continue;
                    }
                }

                $orderData = $this->calculateOrderData(
                    $cartItems,
                    $validated,
                    $address,
                    (int) $order->discount_amount,
                    (int) $user->id,
                );
                $notesData = json_decode($order->notes ?? '', true);
                $notesData = is_array($notesData) ? $notesData : [];

                if ($orderData['installment_fee'] > 0) {
                    $notesData['installment_fee'] = $orderData['installment_fee'];
                } else {
                    unset($notesData['installment_fee']);
                }

                $shippingMethod = ShippingMethod::find($validated['shipping_method_id']);
                $order->update([
                    'total_amount' => $orderData['total_amount'],
                    'payment_method' => $orderData['payment_method'],
                    'user_address_id' => $address?->id,
                    'notes' => json_encode($notesData),
                ]);
                $order->shipping()->updateOrCreate([], [
                    'shipping_method_id' => $validated['shipping_method_id'],
                    'shipping_method_name' => $shippingMethod?->name ?? '',
                    'shipping_cost' => $orderData['shipping_cost'],
                    'tax_amount' => $orderData['tax_amount'],
                    'tax_rate' => $orderData['tax_rate'],
                ]);

                return $order;
            }
        }

        return null;
    }

    private function calculateShippingCost(int $shippingMethodId, $cartItems, int $cartTotal, ?int $provinceId, ?int $cityId): array
    {
        $method = ShippingMethod::active()->with('rates')->find($shippingMethodId);

        if (! $method || $method->rates->isEmpty()) {
            return ['total' => 0, 'tax_amount' => 0, 'tax_rate' => 0];
        }

        if ($method->is_pickup) {
            return ['total' => 0, 'tax_amount' => 0, 'tax_rate' => 0];
        }

        $totalWeight = 0;
        foreach ($cartItems as $item) {
            $totalWeight += ($item->product->weight ?? 0) * $item->product->pack_size * $item->quantity;
        }

        $rate = $this->findMatchingRate($method, $totalWeight, $cartTotal, $provinceId, $cityId);

        if (! $rate) {
            return ['total' => 0, 'tax_amount' => 0, 'tax_rate' => 0];
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

        $taxRate = (float) ($rate->tax_rate ?? 0);
        $taxAmount = 0;
        if ($taxRate > 0 && ! $freeShipping) {
            $taxAmount = (int) round($shippingCost * $taxRate / 100);
        }

        return [
            'total' => $shippingCost + $taxAmount,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
        ];
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
