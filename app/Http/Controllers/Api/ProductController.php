<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\User;
use App\Services\Pricing\DynamicPriceService;
use App\Services\Pricing\PricingSettings;
use App\Support\Platform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $slugs = request()->input('slugs');
        $skus = request()->input('skus');
        $includeSortMetrics = config('product-analytics.sort_metrics_enabled')
            && request()->boolean('include_sort_metrics');

        $priceMode = PricingSettings::tokenikoProductPricesEnabled() ? 'tokeniko' : 'calculated';
        $cacheKey = 'api:products:v2:'.$priceMode.':'.($slugs ? md5($slugs) : ($skus ? md5($skus) : 'all'));

        $products = Cache::remember($cacheKey, 60, function () use ($slugs, $skus) {
            $query = Product::publiclyListed()
                ->with(['category:id,name,slug', 'brand:id,name,slug', 'images', 'baseProduct:id,stock_quantity,hesabfa_physical_stock,hesabfa_reserved_stock,hesabfa_manual_reserved']);

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
                    $data = (new ProductResource($p))->toListingArray(request());

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
        $salesEnabled = PricingSettings::salesEnabled();
        $productIds = array_column($products, 'id');
        $viewCounts = collect();
        $salesCounts = collect();

        if ($includeSortMetrics && $productIds !== []) {
            $viewCounts = Product::query()
                ->whereIn('id', $productIds)
                ->pluck('views_count', 'id');
            $salesCounts = OrderItem::query()
                ->whereIn('product_id', $productIds)
                ->whereHas('order', function (Builder $query): void {
                    $query->whereIn('status', ['confirmed', 'completed'])
                        ->whereDoesntHave('items.product', static fn (Builder $itemsQuery): Builder => $itemsQuery->where('slug', 'test'));
                })
                ->select('product_id')
                ->selectRaw('SUM(quantity) as sales_count')
                ->groupBy('product_id')
                ->pluck('sales_count', 'product_id');
        }

        if ($userId !== null) {
            $productModels = Product::whereIn('id', array_column($products, 'id'))->get()->keyBy('id');
        }

        $products = array_map(function (array $p) use ($wishlistSet, $dynamicPrice, $userId, $productModels, $salesEnabled, $includeSortMetrics, $viewCounts, $salesCounts) {
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

            if (! $salesEnabled) {
                $p['purchasable'] = false;
                $p['unavailable_reason'] = 'sales_closed';
            }

            $product = array_merge($p, [
                'is_wishlist' => isset($wishlistSet[$productId]),
            ]);

            if ($includeSortMetrics) {
                $product['views_count'] = (int) ($viewCounts->get($productId) ?? 0);
                $product['sales_count'] = (int) ($salesCounts->get($productId) ?? 0);
            }

            return $product;
        }, $products);

        return response()->json([
            'data' => $products,
            'sales_enabled' => $salesEnabled,
        ]);
    }

    public function show(string $slugOrId): JsonResponse
    {
        $product = Product::published()
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'images', 'baseProduct:id,stock_quantity,hesabfa_physical_stock,hesabfa_reserved_stock,hesabfa_manual_reserved'])
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

        $salesEnabled = PricingSettings::salesEnabled();
        if (! $salesEnabled) {
            $data['purchasable'] = false;
            $data['unavailable_reason'] = 'sales_closed';
        }

        return response()->json([
            'data' => $data,
            'sales_enabled' => $salesEnabled,
        ]);
    }

    public function recordView(Request $request, string $slugOrId): JsonResponse
    {
        if (! config('product-analytics.view_tracking_enabled')) {
            return response()->noContent();
        }

        $validated = $request->validate([
            'visitor_id' => ['required', 'uuid'],
        ]);

        $product = Product::publiclyListed()
            ->where(function (Builder $query) use ($slugOrId): void {
                $query->where('slug', $slugOrId)
                    ->orWhere('id', $slugOrId);
            })
            ->first();

        if (! $product) {
            return response()->json(['message' => 'محصول یافت نشد', 'error_code' => 'PRODUCT_NOT_FOUND'], 404);
        }

        $dedupeKey = 'api:product-view:'.$product->id.':'.hash('sha256', $validated['visitor_id']);
        if (Cache::add($dedupeKey, true, now()->addDay())) {
            $product->increment('views_count');
        }

        return response()->noContent();
    }
}
