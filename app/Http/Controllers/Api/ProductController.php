<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Modules\Product\Models\Product;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Cache::remember('api:products', 300, function () {
            return Product::query()
                ->with(['category:id,name,slug', 'brand:id,name,slug'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sub' => $p->category?->name ?? '',
                    'cat' => $p->category?->name ?? '',
                    'cat_slug' => $p->category?->slug ?? '',
                    'brand' => $p->brand?->name ?? '',
                    'brand_slug' => $p->brand?->slug ?? '',
                    'weight' => $p->weight ? $p->weight . ' گرم' : '',
                    'price' => (int) $p->price,
                    'old' => null,
                    'badge' => null,
                    'stock' => $p->stock_quantity > 0,
                    'desc' => strip_tags($p->description ?? ''),
                ])
                ->toArray();
        });

        return response()->json(['data' => $products]);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::query()
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'images'])
            ->find($id);

        if (! $product) {
            return response()->json(['message' => 'محصول یافت نشد'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'sub' => $product->category?->name ?? '',
                'cat' => $product->category?->name ?? '',
                'cat_slug' => $product->category?->slug ?? '',
                'brand' => $product->brand?->name ?? '',
                'brand_slug' => $product->brand?->slug ?? '',
                'weight' => $product->weight ? $product->weight . ' گرم' : '',
                'price' => (int) $product->price,
                'old' => null,
                'badge' => null,
                'stock' => $product->stock_quantity > 0,
                'desc' => strip_tags($product->description ?? ''),
                'full_desc' => $product->description ?? '',
                'sku' => $product->sku,
                'images' => $product->images->map(fn ($img) => [
                    'id' => $img->id,
                    'path' => $img->image_path,
                    'is_primary' => $img->is_primary,
                ]),
            ],
        ]);
    }
}
