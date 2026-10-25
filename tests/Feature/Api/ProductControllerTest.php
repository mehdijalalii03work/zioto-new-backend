<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_response_exposes_the_current_sales_switch(): void
    {
        Product::create([
            'name' => 'محصول نمایشی توقف فروش',
            'slug' => 'sales-closed-product',
            'price' => 100000,
            'stock_quantity' => 1,
            'status' => 'published',
            'visibility' => 'public',
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('sales_enabled', true)
            ->assertJsonPath('data.0.purchasable', true);

        Setting::updateOrCreate(['key' => 'zioto_pricing_sales_enabled'], [
            'value' => 'false',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'توقف فروش محصولات',
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('sales_enabled', false)
            ->assertJsonPath('data.0.purchasable', false)
            ->assertJsonPath('data.0.unavailable_reason', 'sales_closed');

        $this->getJson('/api/products/sales-closed-product')
            ->assertOk()
            ->assertJsonPath('sales_enabled', false)
            ->assertJsonPath('data.purchasable', false)
            ->assertJsonPath('data.unavailable_reason', 'sales_closed');

        Setting::updateOrCreate(['key' => 'zioto_pricing_sales_enabled'], [
            'value' => 'true',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'توقف فروش محصولات',
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('sales_enabled', true)
            ->assertJsonPath('data.0.purchasable', true)
            ->assertJsonPath('data.0.unavailable_reason', null);
    }
}
