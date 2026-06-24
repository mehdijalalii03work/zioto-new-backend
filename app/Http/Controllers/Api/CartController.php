<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;

class CartController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $items = Cart::where('user_id', $userId)
            ->with('product:id,name,price,weight,stock_quantity')
            ->get()
            ->map(fn (Cart $item) => [
                'id' => $item->product_id,
                'qty' => $item->quantity,
                'name' => $item->product->name ?? '',
                'price' => (int) ($item->product->price ?? 0),
                'weight' => $item->product->weight ?? '',
                'stock' => ($item->product->stock_quantity ?? 0) > 0,
            ]);

        return response()->json(['data' => $items]);
    }

    public function store(): JsonResponse
    {
        $userId = Auth::id();
        $productId = request()->input('product_id');
        $quantity = request()->input('quantity', 1);

        if (! $productId) {
            return response()->json(['message' => 'شناسه محصول الزامی است'], 422);
        }

        $product = Product::find($productId);
        if (! $product) {
            return response()->json(['message' => 'محصول یافت نشد'], 404);
        }

        Cart::updateOrCreate(
            ['user_id' => $userId, 'product_id' => $productId],
            ['quantity' => DB::raw("GREATEST(quantity + {$quantity}, 1)")]
        );

        return response()->json(['message' => 'به سبد اضافه شد']);
    }

    public function update(int $productId): JsonResponse
    {
        $userId = Auth::id();
        $quantity = request()->input('quantity', 1);

        $cart = Cart::where('user_id', $userId)->where('product_id', $productId)->first();

        if (! $cart) {
            return response()->json(['message' => 'آیتم در سبد یافت نشد'], 404);
        }

        if ($quantity <= 0) {
            $cart->delete();

            return response()->json(['message' => 'از سبد حذف شد']);
        }

        $cart->update(['quantity' => $quantity]);

        return response()->json(['message' => 'سبد بروزرسانی شد']);
    }

    public function destroy(int $productId): JsonResponse
    {
        $userId = Auth::id();

        Cart::where('user_id', $userId)->where('product_id', $productId)->delete();

        return response()->json(['message' => 'از سبد حذف شد']);
    }

    public function clear(): JsonResponse
    {
        $userId = Auth::id();

        Cart::where('user_id', $userId)->delete();

        return response()->json(['message' => 'سبد خرید خالی شد']);
    }
}
