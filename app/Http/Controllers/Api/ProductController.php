<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\User;
use App\Services\Pricing\DynamicPriceService;
use App\Support\Platform;
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
                ->map(function (Product $p) {
                    $data = ProductResource::withoutImages(new ProductResource($p));

                    // Cache always stores guest/basic pricing; per-user prices are applied after read.
                    $guestPrice = app(DynamicPriceService::class)->priceFor($p, null);
                    if ($guestPrice !== null) {
                        $data['price'] = (int) $guestPrice;
                        $taxRate = (float) ($data['tax_rate'] ?? 0);
                        $priceBeforeTax = $taxRate > 0 ? round($data['price'] / (1 + $taxRate / 100)) : $data['price'];
                        $data['price_before_tax'] = $priceBeforeTax;
                        $data['tax_amount'] = $data['price'] - $priceBeforeTax;
                    }

                    return $data;
                })
                ->toArray();
        });

        $token = request()->bearerToken();
        $wishlistIds = [];
        $userId = null;

        if ($token) {
            $tokenHash = hash('sha256', $token);
            $user = User::withoutTenantScope()
                ->where('api_token_hash', $tokenHash)
                ->where('platform', Platform::fromRequest())
                ->first();
            if ($user) {
                $wishlistIds = $user->wishlists()->pluck('products.id')->toArray();
                $userId = $user->id;
            }
        }

        $wishlistSet = array_flip($wishlistIds);
        $dynamicPrice = app(DynamicPriceService::class);
        $productModels = null;

        if ($userId !== null) {
            $productModels = Product::whereIn('id', array_column($products, 'id'))->get()->keyBy('id');
        }

        $products = array_map(function (array $p) use ($wishlistSet, $dynamicPrice, $userId, $productModels) {
            $productId = $p['id'];

            if ($userId !== null && $productModels !== null) {
                $model = $productModels->get($productId);

                if ($model) {
                    $livePrice = $dynamicPrice->priceFor($model, $userId);
                    if ($livePrice !== null) {
                        $p['price'] = (int) $livePrice;
                        $taxRate = (float) ($p['tax_rate'] ?? 0);
                        $priceBeforeTax = $taxRate > 0 ? round($p['price'] / (1 + $taxRate / 100)) : $p['price'];
                        $p['price_before_tax'] = $priceBeforeTax;
                        $p['tax_amount'] = $p['price'] - $priceBeforeTax;
                    }
                }
            }

            return array_merge($p, [
                'is_wishlist' => isset($wishlistSet[$productId]),
            ]);
        }, $products);

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
                ->where('platform', Platform::fromRequest())
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
