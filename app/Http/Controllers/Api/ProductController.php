<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Modules\Product\Models\Product;

class ProductController extends Controller
{
    private function formatProduct(Product $p): array
    {
        $price = (int) $p->price;

        $primaryImage = $p->images->firstWhere('is_primary', true) ?? $p->images->first();

        $taxKey = str_starts_with($p->price_board_item ?? '', 'Gold') ? 'tax_gold' : 'tax_silver';
        $taxRate = (float) Setting::getValue($taxKey, 0);
        $priceBeforeTax = $taxRate > 0 ? round($price / (1 + $taxRate / 100)) : $price;
        $taxAmount = $price - $priceBeforeTax;

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'sub' => $p->category?->name ?? '',
            'cat' => $p->category?->name ?? '',
            'cat_slug' => $p->category?->slug ?? '',
            'brand' => $p->brand?->name ?? '',
            'brand_slug' => $p->brand?->slug ?? '',
            'metal_type' => $p->metal_type?->value ?? null,
            'metal_type_label' => $p->metal_type?->label() ?? null,
            'form' => $p->form?->value ?? null,
            'form_label' => $p->form?->label() ?? null,
            'ayar' => $p->ayar?->value ?? null,
            'ayar_label' => $p->ayar?->label() ?? null,
            'weight' => $p->weight ? $p->weight.' گرم' : '',
            'price' => $price,
            'price_before_tax' => $priceBeforeTax,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'old' => null,
            'badge' => null,
            'stock' => $p->stock_quantity > 0,
            'desc' => strip_tags($p->description ?? ''),
            'image' => $primaryImage ? asset('storage/'.$primaryImage->image_path) : null,
        ];
    }

    public function index(): JsonResponse
    {
        $slugs = request()->input('slugs');
        $skus = request()->input('skus');

        $cacheKey = 'api:products:'.($slugs ? md5($slugs) : ($skus ? md5($skus) : 'all'));

        $products = Cache::remember($cacheKey, 300, function () use ($slugs, $skus) {
            $query = Product::query()
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
                ->map(fn (Product $p) => $this->formatProduct($p))
                ->toArray();
        });

        return response()->json(['data' => $products]);
    }

    public function show(string $slugOrId): JsonResponse
    {
        $product = Product::query()
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'images'])
            ->where('slug', $slugOrId)
            ->orWhere('id', $slugOrId)
            ->first();

        if (! $product) {
            return response()->json(['message' => 'محصول یافت نشد'], 404);
        }

        $data = $this->formatProduct($product);
        $data['full_desc'] = $product->description ?? '';
        $data['sku'] = $product->sku;
        $data['images'] = $product->images->sortBy('sort_order')->map(fn ($img) => [
            'id' => $img->id,
            'path' => asset('storage/'.$img->image_path),
            'alt' => $img->alt ?? '',
            'is_primary' => $img->is_primary,
        ]);

        return response()->json(['data' => $data]);
    }
}
