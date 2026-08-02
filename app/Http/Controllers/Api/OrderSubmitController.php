<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderNoteResource;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\OrderShipping;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Services\InstallmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;

class OrderSubmitController extends Controller
{
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
            ->get();

        return response()->json([
            'notes' => OrderNoteResource::collection($notes),
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $cartItems = Cart::where('user_id', $user->id)
            ->with('product:id,name,price')
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'سبد خرید شما خالی است',
                'error_code' => 'CART_EMPTY',
            ], 422);
        }

        $result = DB::transaction(function () use ($validated, $user, $cartItems) {
            $address = empty($validated['user_address_id']) ? null : $user->addresses()->with(['province', 'city'])->find($validated['user_address_id']);

            $buyerName = $address?->receiver_name ?: $user->name;
            $buyerPhone = $address?->receiver_phone ?: $user->phone;
            $nationalId = $user->national_code;

            $totalAmount = 0;
            $orderItems = [];

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->product;
                $subtotal = $product->price * $cartItem->quantity;
                $totalAmount += $subtotal;
                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_price' => $product->price,
                    'quantity' => $cartItem->quantity,
                    'subtotal' => $subtotal,
                ];
            }

            $shippingResult = $this->calculateShippingCost(
                $validated['shipping_method_id'],
                $cartItems,
                $totalAmount,
                $address?->province_id,
                $address?->city_id
            );

            $shippingCost = $shippingResult['total'];
            $totalAmount += $shippingCost;

            $gateway = $validated['gateway'] ?? 'parsian';
            $paymentMethod = InstallmentService::isInstallmentGateway($gateway)
                ? (InstallmentService::isFeeGateway($gateway) ? 'installment' : 'installment_nofee')
                : 'online';
            $installmentFee = 0;

            if (InstallmentService::isFeeGateway($gateway)) {
                $installmentFee = InstallmentService::calculateFee((int) $totalAmount);
                $totalAmount += $installmentFee;
            }

            $notesData = [
                'name' => $buyerName,
                'phone' => $buyerPhone,
                'national_code' => $nationalId,
            ];

            if ($installmentFee > 0) {
                $notesData['installment_fee'] = $installmentFee;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'user_address_id' => $validated['user_address_id'] ?? null,
                'notes' => json_encode($notesData),
            ]);

            foreach ($orderItems as $orderItem) {
                $order->items()->create($orderItem);
            }

            $method = ShippingMethod::find($validated['shipping_method_id']);

            OrderShipping::create([
                'order_id' => $order->id,
                'shipping_method_id' => $validated['shipping_method_id'],
                'shipping_method_name' => $method?->name ?? '',
                'shipping_cost' => $shippingCost,
                'tax_amount' => $shippingResult['tax_amount'],
                'tax_rate' => $shippingResult['tax_rate'],
            ]);

            return [
                'order' => $order->load(['shipping', 'items']),
                'method' => $method,
                'installmentFee' => $installmentFee,
                'shippingCost' => $shippingCost,
            ];
        });

        return response()->json([
            'message' => 'سفارش با موفقیت ثبت شد',
            'order' => new OrderResource($result['order']),
        ], 201);
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
            $totalWeight += ($item->product->weight ?? 0) * $item->quantity;
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
        if ($rate->free_shipping_min && $cartTotal >= $rate->free_shipping_min) {
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
