<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Modules\Product\Models\Product;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $slugs = request()->input('slugs');
        $skus = request()->input('skus');

        $cacheKey = 'api:products:'.($slugs ? md5($slugs) : ($skus ? md5($skus) : 'all'));

        $products = Cache::remember($cacheKey, 60, function () use ($slugs, $skus) {
            $query = Product::publiclyListed()
                ->with(['category:id,name,slug', 'brand:id,name,slug', 'images']);

            if ($slugs) {
                $slugList = array_map('trim', explode(',', $slugs));
                $query->whereIn('slug', $slugList);
            } elseif ($skus) {
                $skuList = array_map('trim', explode(',', $skus));
                $query->whereIn('sku', $skuList);
            }

            return $query
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Product $p) => ProductResource::withoutImages(new ProductResource($p)))
                ->toArray();
        });

        $token = request()->bearerToken();
        $wishlistIds = [];

        if ($token) {
            $tokenHash = hash('sha256', $token);
            $user = User::withoutTenantScope()
                ->where('api_token_hash', $tokenHash)
                ->where('platform', \App\Support\Platform::fromRequest())
                ->first();
            if ($user) {
                $wishlistIds = $user->wishlists()->pluck('products.id')->toArray();
            }
        }

        $wishlistSet = array_flip($wishlistIds);

        $products = array_map(fn (array $p) => array_merge($p, [
            'is_wishlist' => isset($wishlistSet[$p['id']]),
        ]), $products);

        return response()->json(['data' => $products]);
    }

    public function show(string $slugOrId): JsonResponse
    {
        $product = Product::published()
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'images'])
            ->where(function ($q) use ($slugOrId) {
                $q->where('slug', $slugOrId)
                    ->orWhere('id', $slugOrId);
            })
            ->first();

        if (! $product) {
            return response()->json(['message' => 'محصول یافت نشد', 'error_code' => 'PRODUCT_NOT_FOUND'], 404);
        }

        $data = (new ProductResource($product))->toArray(request());

        $token = request()->bearerToken();
        $data['is_wishlist'] = false;

        if ($token) {
            $tokenHash = hash('sha256', $token);
            $user = User::withoutTenantScope()
                ->where('api_token_hash', $tokenHash)
                ->where('platform', \App\Support\Platform::fromRequest())
                ->first();
            if ($user) {
                $data['is_wishlist'] = $user->wishlists()
                    ->where('products.id', $product->id)
                    ->exists();
            }
        }

        return response()->json(['data' => $data]);
    }
}
