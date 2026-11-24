<?php

namespace Tests\Feature\Services;

use App\Models\RahkaranSerial;
use App\Models\Setting;
use App\Services\RahkaranInventoryService;
use App\Services\RahkaranService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function reservationTypes(): array
    {
        return [
            'serial' => [true, false],
            'quantity' => [false, false],
            'pack' => [false, true],
        ];
    }

    #[DataProvider('reservationTypes')]
    public function test_expired_order_releases_its_exclusive_reservation(bool $serialTracked, bool $pack): void
    {
        Setting::create(['key' => 'inventory_source', 'value' => 'rahkaran', 'type' => 'string', 'category' => 'inventory', 'label' => 'Inventory']);
        $base = $this->product('LIMITED', 'published', 'public', 101);
        $base->update(['rahkaran_physical_stock' => $pack ? 2 : 1, 'rahkaran_serial_tracked' => $serialTracked]);
        $product = $pack ? $this->product('PACK', 'published', 'public', 102) : $base;
        if ($pack) {
            $product->update(['base_product_id' => $base->id, 'pack_size' => 2]);
        }

        if ($serialTracked) {
            RahkaranSerial::create(['product_id' => $base->id, 'serial' => 'ONLY-ONE', 'status' => 'available']);
        }

        $first = Order::factory()->create(['order_number' => '10001', 'created_at' => now()->subMinutes(21)]);
        $second = Order::factory()->create(['order_number' => '10002']);
        foreach ([$first, $second] as $order) {
            $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'product_price' => 100, 'quantity' => 1, 'subtotal' => 100]);
        }

        $inventory = app(RahkaranInventoryService::class);
        $inventory->reserveForOrder($first);
        $inventory->reserveForOrder($first);
        try {
            $inventory->reserveForOrder($second);
            $this->fail('The second order must not reserve the last unit.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('موجود', $exception->getMessage());
        }

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $first->refresh()->status);
        $this->assertTrue($inventory->reserveForOrder($second));
        if ($serialTracked) {
            $this->assertDatabaseHas('rahkaran_serials', ['serial' => 'ONLY-ONE', 'status' => 'reserved', 'order_item_id' => $second->items()->firstOrFail()->id]);
        } else {
            $this->assertSame($pack ? 2 : 1, $base->refresh()->rahkaran_reserved_stock);
        }
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
