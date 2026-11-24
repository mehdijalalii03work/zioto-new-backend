<?php

namespace Tests\Feature\Services;

use App\Services\RahkaranInventoryService;
use App\Services\RahkaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Tests\TestCase;

class RahkaranInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_sync_includes_published_private_products_and_skips_drafts(): void
    {
        config([
            'rahkaran.inventory_id' => 1,
            'rahkaran.plant_id' => 2,
        ]);

        $privateProduct = $this->product('PRIVATE-1', 'published', 'private', 101);
        $publicProduct = $this->product('PUBLIC-1', 'published', 'public', 102);
        $draftProduct = $this->product('DRAFT-1', 'draft', 'private', 103);

        $rahkaran = $this->mock(RahkaranService::class);
        $rahkaran->shouldReceive('isConfigured')->once()->andReturnTrue();
        $rahkaran->shouldReceive('getStock')->once()->with(101, 1, 2)->andReturn(7.0);
        $rahkaran->shouldReceive('getStock')->once()->with(102, 1, 2)->andReturn(9.0);

        $result = app(RahkaranInventoryService::class)->syncAllSerials();

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['updated_products']);
        $this->assertSame(7, $privateProduct->refresh()->rahkaran_physical_stock);
        $this->assertSame(7, $privateProduct->stock_quantity);
        $this->assertSame(9, $publicProduct->refresh()->rahkaran_physical_stock);
        $this->assertSame(4, $draftProduct->refresh()->stock_quantity);
        $this->assertNull($draftProduct->rahkaran_physical_stock);
    }

    private function product(string $sku, string $status, string $visibility, int $partRef): Product
    {
        return Product::query()->create([
            'name' => $sku,
            'slug' => strtolower($sku),
            'sku' => $sku,
            'status' => $status,
            'visibility' => $visibility,
            'price' => 100,
            'stock_quantity' => 4,
            'rahkaran_part_ref' => $partRef,
        ]);
    }
}
