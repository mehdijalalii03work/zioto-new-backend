<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;
use Tests\TestCase;

class ProductCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_detail_includes_public_products_from_all_active_descendants(): void
    {
        $root = ProductCategory::factory()->create(['name' => 'طلا', 'slug' => 'gold']);
        $child = ProductCategory::factory()->childOf($root)->create(['name' => 'شمش', 'slug' => 'gold-bars']);
        $inactiveChild = ProductCategory::factory()->childOf($root)->inactive()->create();

        Product::create(['category_id' => $root->id, 'name' => 'محصول والد', 'slug' => 'parent-product', 'price' => 1000, 'status' => 'published', 'visibility' => 'public']);
        Product::create(['category_id' => $child->id, 'name' => 'محصول فرزند', 'slug' => 'child-product', 'price' => 1000, 'status' => 'published', 'visibility' => 'public']);
        Product::create(['category_id' => $inactiveChild->id, 'name' => 'محصول دسته غیرفعال', 'slug' => 'inactive-child-product', 'price' => 1000, 'status' => 'published', 'visibility' => 'public']);
        Product::create(['category_id' => $child->id, 'name' => 'محصول پیش‌نویس', 'slug' => 'draft-product', 'price' => 1000, 'status' => 'draft', 'visibility' => 'public']);
        Product::create(['category_id' => $child->id, 'name' => 'محصول خصوصی', 'slug' => 'private-product', 'price' => 1000, 'status' => 'published', 'visibility' => 'private']);

        $this->getJson('/api/categories/gold')
            ->assertOk()
            ->assertJsonPath('data.name', 'طلا')
            ->assertJsonPath('data.products_count', 2)
            ->assertJsonPath('data.breadcrumbs.0.slug', 'gold')
            ->assertJsonPath('data.children.0.slug', 'gold-bars')
            ->assertJsonFragment(['slug' => 'parent-product'])
            ->assertJsonFragment(['slug' => 'child-product'])
            ->assertJsonMissing(['slug' => 'inactive-child-product'])
            ->assertJsonMissing(['slug' => 'draft-product'])
            ->assertJsonMissing(['slug' => 'private-product']);
    }

    public function test_category_endpoints_hide_inactive_categories_and_count_descendant_products(): void
    {
        $root = ProductCategory::factory()->create(['slug' => 'category-parent']);
        $child = ProductCategory::factory()->childOf($root)->create(['slug' => 'category-child']);
        ProductCategory::factory()->inactive()->create(['slug' => 'category-inactive']);
        Product::create(['category_id' => $child->id, 'name' => 'محصول', 'slug' => 'category-product', 'price' => 1000, 'status' => 'published', 'visibility' => 'public']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.products_count', 1)
            ->assertJsonMissing(['slug' => 'category-inactive']);

        $this->getJson('/api/categories/category-inactive')->assertNotFound();
        $this->getJson('/api/categories/unknown-category')->assertNotFound();
    }
}
