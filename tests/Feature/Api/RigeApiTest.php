<?php

namespace Tests\Feature\Api;

use App\Enums\Product\Ayar;
use App\Enums\Product\MetalType;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;
use Tests\TestCase;

class RigeApiTest extends TestCase
{
    use RefreshDatabase;

    private function createPublishedProduct(array $overrides = []): Product
    {
        $category = ProductCategory::create([
            'name' => 'انگشتر',
            'slug' => 'ring-'.uniqid(),
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Cartier',
            'slug' => 'cartier-'.uniqid(),
            'is_active' => true,
        ]);

        return Product::create(array_merge([
            'name' => 'انگشتر طلای ۱۸ عیار',
            'slug' => 'gold-ring-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'status' => 'published',
            'visibility' => 'public',
            'price' => 10000000,
            'stock_quantity' => 5,
            'metal_type' => MetalType::Gold,
            'ayar' => Ayar::P999,
            'weight' => 3.4,
            'category_id' => $category->id,
            'brand_id' => $brand->id,
        ], $overrides));
    }

    public function test_products_endpoint_returns_rige_format(): void
    {
        $product = $this->createPublishedProduct();

        $response = $this->getJson('/api/rige/products');

        $response->assertOk();
        $response->assertJsonStructure([
            '*' => [
                'code',
                'name',
                'description',
                'category_id',
                'weight',
                'wage',
                'discount',
                'in_stock',
                'used',
                'production_code',
                'variants' => [
                    'material',
                    'caret',
                    'gender',
                    'color',
                    'brand',
                    'model',
                ],
            ],
        ]);

        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame($product->sku, $data[0]['code']);
        $this->assertSame($product->sku, $data[0]['production_code']);
        $this->assertSame(1, $data[0]['variants']['material']);
        $this->assertSame(2, $data[0]['variants']['caret']);
        $this->assertSame('Cartier', $data[0]['variants']['brand']);
        $this->assertTrue($data[0]['in_stock']);
        $this->assertFalse($data[0]['used']);
        $this->assertSame(0, $data[0]['wage']);
        $this->assertSame(0, $data[0]['discount']);
    }

    public function test_products_excludes_draft_products(): void
    {
        $this->createPublishedProduct(['status' => 'draft']);

        $response = $this->getJson('/api/rige/products');

        $response->assertOk();
        $this->assertEmpty($response->json());
    }

    public function test_products_excludes_private_products(): void
    {
        $this->createPublishedProduct(['visibility' => 'private']);

        $response = $this->getJson('/api/rige/products');

        $response->assertOk();
        $this->assertEmpty($response->json());
    }

    public function test_categories_endpoint(): void
    {
        ProductCategory::create([
            'name' => 'انگشتر',
            'slug' => 'ring-'.uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/api/rige/categories');

        $response->assertOk();
        $response->assertJsonStructure([
            'ok',
            'data' => [
                '*' => ['id', 'name', 'identifier', 'display_priority', 'image'],
            ],
        ]);

        $data = $response->json();
        $this->assertTrue($data['ok']);
        $this->assertCount(1, $data['data']);
        $this->assertNull($data['data'][0]['image']);
    }

    public function test_variants_material_endpoint(): void
    {
        $response = $this->getJson('/api/rige/variants/material');

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'data' => [
                ['id' => 1, 'name' => 'طلا'],
                ['id' => 2, 'name' => 'نقره'],
            ],
        ]);
    }

    public function test_variants_caret_endpoint(): void
    {
        $response = $this->getJson('/api/rige/variants/caret');

        $response->assertOk();
        $data = $response->json();
        $this->assertTrue($data['ok']);
        $this->assertCount(3, $data['data']);
    }

    public function test_variants_brand_endpoint_returns_brands_from_db(): void
    {
        Brand::create(['name' => 'Cartier', 'slug' => 'cartier', 'is_active' => true]);
        Brand::create(['name' => 'Tiffany', 'slug' => 'tiffany', 'is_active' => true]);

        $response = $this->getJson('/api/rige/variants/brand');

        $response->assertOk();
        $data = $response->json();
        $this->assertTrue($data['ok']);
        $this->assertCount(2, $data['data']);
    }

    public function test_variants_invalid_identifier_returns_404(): void
    {
        $response = $this->getJson('/api/rige/variants/invalid');

        $response->assertStatus(404);
    }

    public function test_variants_gender_returns_empty(): void
    {
        $response = $this->getJson('/api/rige/variants/gender');

        $response->assertOk();
        $response->assertJson(['ok' => true, 'data' => []]);
    }
}
