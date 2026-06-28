<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Product\Models\Product;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $wishlistItems = Wishlist::where('user_id', $user->id)
            ->with(['product.category:id,name,slug', 'product.brand:id,name,slug', 'product.images'])
            ->get()
            ->map(fn (Wishlist $item) => $item->product ? $this->formatProduct($item->product) : null)
            ->filter()
            ->values();

        return response()->json(['data' => $wishlistItems]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'این محصول قبلاً به علاقه‌مندی‌ها اضافه شده است',
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
            ], 404);
        }

        return response()->json([
            'message' => 'محصول از علاقه‌مندی‌ها حذف شد',
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

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

    private function formatProduct(Product $p): array
    {
        $price = (int) $p->price;

        $primaryImage = $p->images->firstWhere('is_primary', true) ?? $p->images->first();

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'sub' => $p->category?->name ?? '',
            'cat' => $p->category?->name ?? '',
            'cat_slug' => $p->category?->slug ?? '',
            'brand' => $p->brand?->name ?? '',
            'brand_slug' => $p->brand?->slug ?? '',
            'weight' => $p->weight ? $p->weight.' گرم' : '',
            'price' => $price,
            'stock' => $p->stock_quantity > 0,
            'desc' => strip_tags($p->description ?? ''),
            'image' => $primaryImage ? asset('storage/'.$primaryImage->image_path) : null,
        ];
    }
}
