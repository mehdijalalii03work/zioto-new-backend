<?php

namespace Tests\Feature\Services;

use App\Models\Setting;
use App\Services\Pricing\DynamicPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Product\Models\Product;
use Tests\TestCase;

class DynamicPriceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedBoard(string $metalKey, float $value): void
    {
        Cache::put('zioto:payload', [
            'prices' => [
                $metalKey => [
                    'name' => 'قیمت فروش طلای ۷۵۰',
                    'value' => $value,
                    'price_type' => 'sell',
                    'base_metal' => 'Gold750',
                    'trend' => 'stable',
                    'updated_at' => now()->timestamp,
                ],
            ],
            'debug' => [],
            'source_status' => [],
            'raw' => ['persian' => [], 'tala' => []],
            'used_fallback' => false,
        ], 60);
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Product '.uniqid(),
            'slug' => 'product-'.uniqid(),
            'price_type' => 'dynamic',
            'price_board_item' => 'Gold750_Sell',
            'weight' => '2.00',
            'price' => 1,
        ], $overrides));
    }

    public function test_price_is_returned_in_rial_while_board_is_toman(): void
    {
        $this->seedBoard('Gold750_Sell', 1000);
        $product = $this->product();

        // 2g × 1000 Toman × (1 + 1.0 default coefficient) = 4000 Toman = 40000 Rial
        $this->assertSame(40000.0, app(DynamicPriceService::class)->priceFor($product));
    }

    public function test_details_for_preview_stays_in_toman(): void
    {
        $this->seedBoard('Gold750_Sell', 1000);
        $product = $this->product();

        $details = app(DynamicPriceService::class)->detailsFor($product, 'basic', 'daily');

        $this->assertNotNull($details);
        $this->assertSame(4000.0, (float) $details['calculated_price']);
        $this->assertSame(1000.0, (float) $details['base_price']);
    }

    public function test_returns_null_when_dynamic_pricing_is_disabled(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_enable_dynamic_pricing',
            'value' => 'false',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'فعال‌سازی قیمت‌گذاری پویا',
        ]);

        $this->seedBoard('Gold750_Sell', 1000);
        $product = $this->product();

        $this->assertNull(app(DynamicPriceService::class)->priceFor($product));
    }

    public function test_tax_and_step_rounding_are_applied_in_toman_before_converting(): void
    {
        Setting::create([
            'key' => 'tax_gold',
            'value' => '10',
            'type' => 'number',
            'category' => 'pricing',
            'label' => 'مالیات طلا',
        ]);
        Setting::create([
            'key' => 'zioto_pricing_round_prices',
            'value' => 'true',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'گردکردن قیمت محصول نهایی',
        ]);
        Setting::create([
            'key' => 'zioto_pricing_round_to',
            'value' => '1000',
            'type' => 'integer',
            'category' => 'pricing',
            'label' => 'گردکردن به مضرب',
        ]);

        $this->seedBoard('Gold750_Sell', 1000);
        $product = $this->product();

        // 4000 Toman → +10% tax = 4400 → step 1000 = 4000 Toman → 40000 Rial
        $this->assertSame(40000.0, app(DynamicPriceService::class)->priceFor($product));
    }

    public function test_gold_labor_tax_is_added_on_the_labor_of_dynamic_products(): void
    {
        Setting::create([
            'key' => 'tax_gold_labor',
            'value' => '10',
            'type' => 'number',
            'category' => 'tax',
            'label' => 'درصد مالیات اجرت طلا',
        ]);

        $this->seedBoard('Gold750_Sell', 1000);
        $product = $this->product();

        // 4000 Toman, labor = 2000 → +10% labor tax = 200 → 4200 Toman → 42000 Rial
        $this->assertSame(42000.0, app(DynamicPriceService::class)->priceFor($product));

        $details = app(DynamicPriceService::class)->detailsFor($product, 'basic', 'daily');

        $this->assertSame(4200.0, (float) $details['calculated_price']);

        $matrix = app(DynamicPriceService::class)->previewMatrix($product, [['slug' => 'daily']], [['slug' => 'basic']]);

        $this->assertSame(4200.0, (float) $matrix['daily']['basic']['final_price']);

        $taxes = $matrix['daily']['basic']['taxes'];

        $this->assertCount(1, $taxes);
        $this->assertSame('labor', $taxes[0]['scope']);
        $this->assertSame(10.0, $taxes[0]['rate']);
        $this->assertSame(200.0, $taxes[0]['amount']);
    }

    public function test_gold_labor_tax_is_skipped_for_silver_and_static_products(): void
    {
        Setting::create([
            'key' => 'tax_gold_labor',
            'value' => '10',
            'type' => 'number',
            'category' => 'tax',
            'label' => 'درصد مالیات اجرت طلا',
        ]);

        $this->seedBoard('Silver9999_Sell', 1000);
        $silver = $this->product(['price_board_item' => 'Silver9999_Sell']);

        $this->assertSame(40000.0, app(DynamicPriceService::class)->priceFor($silver));

        $silverMatrix = app(DynamicPriceService::class)->previewMatrix($silver, [['slug' => 'daily']], [['slug' => 'basic']]);

        $this->assertSame([], $silverMatrix['daily']['basic']['taxes']);

        $this->seedBoard('Gold750_Sell', 1000);
        $static = $this->product(['price_type' => 'fixed']);

        $this->assertNull(app(DynamicPriceService::class)->priceFor($static));
    }
}
