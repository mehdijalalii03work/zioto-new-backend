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

    public function test_bulk_serial_sync_reconciles_serials_and_checks_omitted_products_for_zero_stock(): void
    {
        config(['rahkaran.inventory_id' => 21, 'rahkaran.plant_id' => 1]);
        $present = $this->product('SERIAL', 'published', 'public', 101);
        $zero = $this->product('ZERO', 'published', 'public', 102);
        $unknown = $this->product('UNKNOWN', 'published', 'public', 103);
        foreach ([$present, $zero, $unknown] as $product) {
            $product->update(['rahkaran_serial_tracked' => true, 'rahkaran_physical_stock' => 4]);
            RahkaranSerial::create(['product_id' => $product->id, 'serial' => 'OLD-'.$product->id, 'status' => 'available']);
        }

        $order = Order::factory()->create();
        $item = $order->items()->create(['product_id' => $present->id, 'product_name' => $present->name, 'product_price' => 100, 'quantity' => 1, 'subtotal' => 100]);
        $reserved = RahkaranSerial::create(['product_id' => $present->id, 'serial' => 'HELD', 'status' => 'reserved', 'order_item_id' => $item->id]);
        $rahkaran = $this->mock(RahkaranService::class);
        $rahkaran->shouldReceive('isConfigured')->once()->andReturnTrue();
        $rahkaran->shouldReceive('getSerialsForParts')->once()->with([101, 102, 103])->andReturn([101 => [['Serial' => 'NEW']]]);
        $rahkaran->shouldReceive('getStock')->once()->with(101, 21, 1)->andReturn(1.0);
        $rahkaran->shouldReceive('getStock')->once()->with(102, 21, 1)->andReturn(0.0);
        $rahkaran->shouldReceive('getStock')->once()->with(103, 21, 1)->andReturn(2.0);

        $inventory = app(RahkaranInventoryService::class);
        $result = $inventory->syncAllSerials();

        $this->assertSame(2, $result['updated_products']);
        $this->assertSame(['UNKNOWN'], array_keys($result['errors']));
        $this->assertSame(0, $zero->refresh()->rahkaran_physical_stock);
        $this->assertDatabaseHas('rahkaran_serials', ['product_id' => $present->id, 'serial' => 'NEW', 'status' => 'available']);
        $this->assertDatabaseHas('rahkaran_serials', ['product_id' => $present->id, 'serial' => 'OLD-'.$present->id, 'status' => 'unavailable']);
        $this->assertDatabaseHas('rahkaran_serials', ['product_id' => $unknown->id, 'serial' => 'OLD-'.$unknown->id, 'status' => 'available']);
        $this->assertSame('reserved', $reserved->refresh()->status);
        $inventory->releaseOrder($order);
        $this->assertSame('unavailable', $reserved->refresh()->status);
    }

    public function test_experimental_quantity_source_counts_fresh_serials(): void
    {
        Setting::create(['key' => 'rahkaran_stock_quantity_source', 'value' => 'live_serials', 'type' => 'string', 'category' => 'inventory', 'label' => 'Quantity source']);
        config(['rahkaran.inventory_id' => 21, 'rahkaran.plant_id' => 1]);
        $product = $this->product('SERIAL', 'published', 'public', 101);
        $product->update(['rahkaran_serial_tracked' => true]);
        $rahkaran = $this->mock(RahkaranService::class);
        $rahkaran->shouldReceive('isConfigured')->once()->andReturnTrue();
        $rahkaran->shouldReceive('getSerialsForParts')->once()->with([101])->andReturn([101 => [['Serial' => '001'], ['Serial' => '002']]]);
        $rahkaran->shouldReceive('getStock')->never();

        $result = app(RahkaranInventoryService::class)->syncAllSerials();

        $this->assertTrue($result['success']);
        $this->assertSame(2, $product->refresh()->rahkaran_physical_stock);
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
