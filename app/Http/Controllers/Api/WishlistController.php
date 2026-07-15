<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWishlistRequest;
use App\Http\Resources\ProductResource;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $wishlistItems = Wishlist::where('user_id', $user->id)
            ->with(['product.category:id,name,slug', 'product.brand:id,name,slug', 'product.images'])
            ->get()
            ->map(fn (Wishlist $item) => $item->product ? ProductResource::withoutImages(new ProductResource($item->product)) : null)
            ->filter()
            ->values();

        return response()->json(['data' => $wishlistItems]);
    }

    public function store(StoreWishlistRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'این محصول قبلاً به علاقه‌مندی‌ها اضافه شده است',
                'error_code' => 'WISHLIST_DUPLICATE',
            ], 422);
        }

        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $validated['product_id'],
        ]);

        return response()->json([
            'message' => 'محصول به علاقه‌مندی‌ها اضافه شد',
        ]);
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        $user = Auth::user();

        $deleted = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->delete();

        if (! $deleted) {
            return response()->json([
                'message' => 'محصول در علاقه‌مندی‌ها یافت نشد',
                'error_code' => 'WISHLIST_NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'message' => 'محصول از علاقه‌مندی‌ها حذف شد',
        ]);
    }

    public function toggle(StoreWishlistRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'message' => 'محصول از علاقه‌مندی‌ها حذف شد',
                'added' => false,
            ]);
        }

        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $validated['product_id'],
        ]);

        return response()->json([
            'message' => 'محصول به علاقه‌مندی‌ها اضافه شد',
            'added' => true,
        ]);
    }
}
