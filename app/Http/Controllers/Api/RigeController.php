<?php

namespace App\Http\Controllers\Api;

use App\Enums\Product\Ayar;
use App\Enums\Product\MetalType;
use App\Http\Controllers\Controller;
use App\Http\Resources\RigeProductResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;

class RigeController extends Controller
{
    public function products(): JsonResponse
    {
        $products = Cache::remember('rige:products', 60, function () {
            return Product::publiclyListed()
                ->with(['category:id,name', 'brand:id,name'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Product $p) => (new RigeProductResource($p))->toArray(request()))
                ->toArray();
        });

        return response()->json($products);
    }

    public function categories(): JsonResponse
    {
        $categories = Cache::remember('rige:categories', 60, function () {
            return ProductCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (ProductCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'identifier' => null,
                    'display_priority' => $c->sort_order,
                    'image' => null,
                ])
                ->toArray();
        });

        return response()->json([
            'ok' => true,
            'data' => $categories,
        ]);
    }

    public function variants(string $identifier): JsonResponse
    {
        $data = match ($identifier) {
            'material' => self::getMaterialVariants(),
            'caret' => self::getCaretVariants(),
            'gender' => [],
            'color' => [],
            'brand' => self::getBrandVariants(),
            'model' => [],
            default => null,
        };

        if ($data === null) {
            return response()->json(['message' => 'ویژگی یافت نشد', 'error_code' => 'VARIANT_NOT_FOUND'], 404);
        }

        return response()->json([
            'ok' => true,
            'data' => $data,
        ]);
    }

    private static function getMaterialVariants(): array
    {
        return collect(MetalType::cases())
            ->map(fn (MetalType $type) => [
                'id' => match ($type) {
                    MetalType::Gold => 1,
                    MetalType::Silver => 2,
                },
                'name' => $type->label(),
            ])
            ->toArray();
    }

    private static function getCaretVariants(): array
    {
        return collect(Ayar::cases())
            ->map(fn (Ayar $ayar, int $index) => [
                'id' => $index + 1,
                'name' => $ayar->label(),
            ])
            ->values()
            ->toArray();
    }

    private static function getBrandVariants(): array
    {
        return Cache::remember('rige:brands', 60, function () {
            return Brand::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Brand $brand) => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                ])
                ->toArray();
        });
    }
}
