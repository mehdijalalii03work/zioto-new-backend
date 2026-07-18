<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderNoteResource;
use App\Http\Resources\OrderResource;
use App\Models\OrderShipping;
use App\Models\ShippingMethod;
use App\Services\InstallmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;

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
        $order = Order::with(['shipping', 'items', 'address.province', 'address.city', 'notes'])
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

        $notes = $order->notes()
            ->where('is_customer_note', true)
            ->get();

        return response()->json([
            'notes' => OrderNoteResource::collection($notes),
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated, $request) {
            $totalAmount = 0;
            $orderItems = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $subtotal = $product->price * $item['quantity'];
                $totalAmount += $subtotal;
                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_price' => $product->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ];
            }

            $shippingCost = $validated['shipping_cost'] ?? 0;
            $totalAmount += $shippingCost;

            $gateway = $validated['gateway'] ?? 'parsian';
            $paymentMethod = InstallmentService::isInstallmentGateway($gateway) ? 'installment' : 'online';
            $installmentFee = 0;

            if (InstallmentService::isInstallmentGateway($gateway)) {
                $installmentFee = InstallmentService::calculateFee((int) $totalAmount);
                $totalAmount += $installmentFee;
            }

            $notesData = [
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'national_id' => $validated['national_id'],
                'employee_id' => $validated['employee_id'],
            ];

            if ($installmentFee > 0) {
                $notesData['installment_fee'] = $installmentFee;
            }

            $order = Order::create([
                'user_id' => $request->user()?->id,
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'user_address_id' => $validated['user_address_id'] ?? null,
                'shipping_address_snapshot' => $validated['shipping_address_snapshot'] ?? null,
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
                'shipping_cost' => $validated['shipping_cost'],
            ]);

            return [
                'order' => $order->load(['shipping', 'items']),
                'method' => $method,
                'installmentFee' => $installmentFee,
                'shippingCost' => $validated['shipping_cost'],
            ];
        });

        return response()->json([
            'message' => 'سفارش با موفقیت ثبت شد',
            'order' => new OrderResource($result['order']),
        ], 201);
    }
}
