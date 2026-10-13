<?php

namespace Tests\Feature\Inventory;

use App\Models\ProductInventoryAudit;
use App\Models\User;
use App\Services\Inventory\ProductInventorySettingsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Tests\TestCase;

class ProductInventoryModeSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function manager(): User
    {
        return User::factory()->create()->givePermissionTo('product.edit', 'serial.manage');
    }

    public function test_switching_to_serial_zeroes_manual_capacity_and_audits_it(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $updated = app(ProductInventorySettingsService::class)->update(
            $product,
            ['inventory_mode' => 'serial', 'serial_prefix' => 'ton9', 'track_serials' => true],
            $this->manager(),
            'switch to serial tracking',
        );

        $this->assertSame('serial', $updated->inventory_mode->value);
        $this->assertSame(0, $updated->stock_quantity);

        $audit = ProductInventoryAudit::where('product_id', $product->id)->latest('id')->firstOrFail();
        $this->assertSame(5, $audit->before['stock_quantity']);
        $this->assertSame(0, $audit->after['stock_quantity']);
        $this->assertSame('serial', $audit->after['inventory_mode']);
    }

    public function test_manual_capacity_edit_without_mode_change_is_preserved(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 5]);

        $updated = app(ProductInventorySettingsService::class)->update(
            $product,
            ['stock_quantity' => 3],
            $this->manager(),
            'capacity adjustment',
        );

        $this->assertSame('manual', $updated->inventory_mode->value);
        $this->assertSame(3, $updated->stock_quantity);
    }

    public function test_stale_capacity_on_serial_product_is_healed_on_next_save(): void
    {
        $product = Product::factory()->serial('ton')->create();
        $product->forceFill(['stock_quantity' => 7])->save();

        $updated = app(ProductInventorySettingsService::class)->update(
            $product,
            ['made_to_order' => true],
            $this->manager(),
            'toggle made to order',
        );

        $this->assertSame('serial', $updated->inventory_mode->value);
        $this->assertSame(0, $updated->stock_quantity);
    }
}
