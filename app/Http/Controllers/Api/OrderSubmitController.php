<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderShipping;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with(['shipping', 'items', 'address.province', 'address.city', 'notes'])
            ->where('id', $orderId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'order' => $order,
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
            'notes' => $notes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'national_id' => 'required|string|size:10',
            'employee_id' => 'required|string|max:50',
            'payment_method' => 'required|in:online,installment',
            'user_address_id' => 'nullable|integer|exists:user_addresses,id',
            'shipping_address_snapshot' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_method_id' => 'required|integer|exists:shipping_methods,id',
            'shipping_cost' => 'required|numeric|min:0',
        ]);

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

        $orderNumber = 'ZT-'.now()->format('YmdHis');

        $order = Order::create([
            'user_id' => $request->user()?->id,
            'order_number' => $orderNumber,
            'status' => 'pending',
            'total_amount' => $totalAmount,
            'payment_method' => $validated['payment_method'],
            'payment_status' => 'pending',
            'user_address_id' => $validated['user_address_id'] ?? null,
            'shipping_address_snapshot' => $validated['shipping_address_snapshot'] ?? null,
            'notes' => json_encode([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'national_id' => $validated['national_id'],
                'employee_id' => $validated['employee_id'],
            ]),
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

        return response()->json([
            'message' => 'سفارش با موفقیت ثبت شد',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'shipping_method_name' => $method?->name ?? '',
                'shipping_cost' => $validated['shipping_cost'],
            ],
        ], 201);
    }
}
