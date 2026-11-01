<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;

class ProductCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $records = ProductCategory::query()
            ->where('is_active', true)
            ->withCount(['products as products_count' => fn ($query) => $query->publiclyListed()])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $counts = $records->mapWithKeys(fn (ProductCategory $category): array => [$category->id => $category->products_count]);
        foreach ($records as $category) {
            $parent = $category->parent_id;
            while ($parent !== null && $records->contains('id', $parent)) {
                $counts[$parent] += $category->products_count;
                $parent = $records->firstWhere('id', $parent)?->parent_id;
            }
        }
        $categories = $records->map(fn (ProductCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'products_count' => $counts[$category->id],
            ]);

        return response()->json(['data' => $categories]);
    }

    public function show(string $slug): JsonResponse
    {
        $category = ProductCategory::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with(['children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->first();

        if (! $category) {
            $oldSlugOwner = ProductCategory::query()
                ->where('is_active', true)
                ->whereJsonContains('previous_slugs', $slug)
                ->first();

            if ($oldSlugOwner) {
                return response()->json(['redirect' => '/category/'.$oldSlugOwner->slug.'/'], 301)
                    ->header('Location', 'https://sawiss.com/category/'.$oldSlugOwner->slug.'/');
            }

            return response()->json(['message' => 'دسته‌بندی یافت نشد'], 404);
        }

        $descendantIds = $this->descendantIds($category);
        $products = Product::query()
            ->publiclyListed()
            ->whereIn('category_id', [$category->id, ...$descendantIds])
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'images', 'baseProduct:id,stock_quantity,hesabfa_physical_stock,hesabfa_reserved_stock,hesabfa_manual_reserved'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product): array => (new ProductResource($product))->toListingArray(request()));

        $ancestorChain = [];
        $ancestor = $category->parent;
        while ($ancestor) {
            if ($ancestor->is_active) {
                $ancestorChain[] = $ancestor;
            }
            $ancestor = $ancestor->parent;
        }
        $breadcrumbs = collect([...array_reverse($ancestorChain), $category])
            ->map(fn (ProductCategory $item): array => ['name' => $item->name, 'slug' => $item->slug]);

        $formatCategory = fn (ProductCategory $item): array => [
            'id' => $item->id,
            'name' => $item->name,
            'slug' => $item->slug,
            'products_count' => Product::query()
                ->publiclyListed()
                ->whereIn('category_id', [$item->id, ...$this->descendantIds($item)])
                ->count(),
        ];

        return response()->json(['data' => [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'seo_title' => $category->seo_title,
            'seo_description' => $category->seo_description,
            'header_image' => $category->header_image ? asset('storage/'.$category->header_image) : null,
            'guide_content' => $category->guide_content,
            'breadcrumbs' => $breadcrumbs,
            'children' => $category->children->map($formatCategory)->values(),
            'products_count' => $products->count(),
            'products' => $products,
        ]]);
    }

    /** @return list<int> */
    private function descendantIds(ProductCategory $category): array
    {
        $ids = [];
        $children = ProductCategory::query()->where('parent_id', $category->id)->where('is_active', true)->get();

        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = [...$ids, ...$this->descendantIds($child)];
        }

        return $ids;
    }
}
