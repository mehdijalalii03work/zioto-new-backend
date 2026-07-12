<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Models\Product;

class ProductController extends Controller
{
    private function getResponsiveImages(string $imagePath): array
    {
        $disk = "public";
        $basePath = pathinfo($imagePath, PATHINFO_DIRNAME);
        $filename = pathinfo($imagePath, PATHINFO_FILENAME);

        $sizes = [
            "thumb"  => 300,
            "medium" => 600,
            "full"   => 1000,
        ];

        $variants = ["original" => asset("storage/" . $imagePath)];

        foreach ($sizes as $label => $maxWidth) {
            $variantPath = $basePath . "/" . $filename . "_" . $label . ".webp";
            if (Storage::disk($disk)->exists($variantPath)) {
                $variants[$label] = asset("storage/" . $variantPath);
            }
        }

        return $variants;
    }

    private function getSrcset(array $responsiveImages): string
    {
        $parts = [];
        $widthMap = ["thumb" => "300w", "medium" => "600w", "full" => "1000w", "original" => "1200w"];
        foreach ($responsiveImages as $label => $url) {
            if (isset($widthMap[$label])) {
                $parts[] = $url . " " . $widthMap[$label];
            }
        }
        return implode(", ", $parts);
    }

    private function formatProduct(Product $p): array
    {
        $price = (int) $p->price;

        $primaryImage = $p->images->firstWhere("is_primary", true) ?? $p->images->first();

        $taxKey = str_starts_with($p->price_board_item ?? "", "Gold") ? "tax_gold" : "tax_silver";
        $taxRate = (float) Setting::getValue($taxKey, 0);
        $priceBeforeTax = $taxRate > 0 ? round($price / (1 + $taxRate / 100)) : $price;
        $taxAmount = $price - $priceBeforeTax;

        return [
            "id" => $p->id,
            "name" => $p->name,
            "slug" => $p->slug,
            "sub" => $p->category?->name ?? "",
            "cat" => $p->category?->name ?? "",
            "cat_slug" => $p->category?->slug ?? "",
            "brand" => $p->brand?->name ?? "",
            "brand_slug" => $p->brand?->slug ?? "",
            "metal_type" => $p->metal_type?->value ?? null,
            "metal_type_label" => $p->metal_type?->label() ?? null,
            "form" => $p->form?->value ?? null,
            "form_label" => $p->form?->label() ?? null,
            "ayar" => $p->ayar?->value ?? null,
            "ayar_label" => $p->ayar?->label() ?? null,
            "weight" => $p->weight ? $p->weight." گرم" : "",
            "price" => $price,
            "price_before_tax" => $priceBeforeTax,
            "tax_amount" => $taxAmount,
            "tax_rate" => $taxRate,
            "old" => null,
            "badge" => null,
            "stock" => $p->stock_quantity > 0,
            "desc" => strip_tags($p->description ?? ""),
            "image" => $primaryImage ? asset("storage/".$primaryImage->image_path) : null,
            "image_responsive" => $primaryImage ? $this->getResponsiveImages($primaryImage->image_path) : null,
            "image_srcset" => $primaryImage ? $this->getSrcset($this->getResponsiveImages($primaryImage->image_path)) : null,
        ];
    }

    public function index(): JsonResponse
    {
        $slugs = request()->input("slugs");
        $skus = request()->input("skus");

        $cacheKey = "api:products:".($slugs ? md5($slugs) : ($skus ? md5($skus) : "all"));

        $products = Cache::remember($cacheKey, 300, function () use ($slugs, $skus) {
            $query = Product::query()
                ->with(["category:id,name,slug", "brand:id,name,slug", "images"]);

            if ($slugs) {
                $slugList = array_map("trim", explode(",", $slugs));
                $query->whereIn("slug", $slugList);
            } elseif ($skus) {
                $skuList = array_map("trim", explode(",", $skus));
                $query->whereIn("sku", $skuList);
            }

            return $query
                ->orderBy("sort_order")
                ->orderBy("id")
                ->get()
                ->map(fn (Product $p) => $this->formatProduct($p))
                ->toArray();
        });

        return response()->json(["data" => $products]);
    }

    public function show(string $slugOrId): JsonResponse
    {
        $product = Product::query()
            ->with(["category:id,name,slug", "brand:id,name,slug", "images"])
            ->where("slug", $slugOrId)
            ->orWhere("id", $slugOrId)
            ->first();

        if (! $product) {
            return response()->json(["message" => "محصول یافت نشد"], 404);
        }

        $data = $this->formatProduct($product);
        $data["full_desc"] = $product->description ?? "";
        $data["sku"] = $product->sku;
        $data["images"] = $product->images->sortBy("sort_order")->map(fn ($img) => [
            "id" => $img->id,
            "path" => asset("storage/".$img->image_path),
            "alt" => $img->alt ?? "",
            "is_primary" => $img->is_primary,
            "responsive" => $this->getResponsiveImages($img->image_path),
            "srcset" => $this->getSrcset($this->getResponsiveImages($img->image_path)),
        ]);

        return response()->json(["data" => $data]);
    }
}
