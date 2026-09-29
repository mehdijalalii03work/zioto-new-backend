<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartRequest;
use App\Http\Requests\UpdateCartRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Services\Pricing\SalesRestrictionService;
use App\Support\Platform;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Product\Models\Product;

class CartController extends Controller
{
    public function __construct(
        private readonly SalesRestrictionService $salesRestrictions,
    ) {}

    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $items = Cart::where('user_id', $userId)
            ->with('product:id,name,price,weight,stock_quantity,price_board_item,price_type,labor_coefficients,category_id,metal_type')
            ->get();

        return response()->json([
            'data' => CartResource::collection($items),
        ]);
    }

    public function store(StoreCartRequest $request): JsonResponse
    {
        $userId = Auth::id();
        $validated = $request->validated();
        $productId = $validated['product_id'];
        $quantity = (int) ($validated['quantity'] ?? 1);

        $product = Product::find($productId);

        if ($product && ($reason = $this->salesRestrictions->blockedReason($product)) !== null) {
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

        if (Platform::isNopay($request) && ! Product::nopayEligible()->whereKey($productId)->exists()) {
            return response()->json([
                'message' => 'این محصول برای خرید اقساطی نوپی در دسترس نیست',
                'error_code' => 'PRODUCT_NOT_ELIGIBLE_FOR_NOPAY',
            ], 422);
        }

        Cart::updateOrCreate(
            ['user_id' => $userId, 'product_id' => $productId, 'platform' => Platform::fromRequest($request)],
            ['quantity' => $quantity]
        );

        return response()->json(['message' => 'به سبد اضافه شد']);
    }

    public function update(UpdateCartRequest $request, int $productId): JsonResponse
    {
        $userId = Auth::id();
        $quantity = (int) $request->validated('quantity');

        $cart = Cart::where('user_id', $userId)->where('product_id', $productId)->first();

        if (! $cart) {
            return response()->json([
                'message' => 'آیتم در سبد یافت نشد',
                'error_code' => 'CART_ITEM_NOT_FOUND',
            ], 404);
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
