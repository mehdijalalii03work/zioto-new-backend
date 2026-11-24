<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Product\Models\Product;
use Tests\TestCase;

class BackfillRahkaranItemCodesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_previews_and_only_applies_unique_codes_for_empty_base_products(): void
    {
        config(['rahkaran.base_url' => 'https://rahkaran.example.test', 'rahkaran.username' => 'test', 'rahkaran.password' => 'test']);
        Cache::put('rahkaran:session-cookies', ['sg-auth-sg' => 'test-session']);
        Http::preventStrayRequests();
        $url = 'https://rahkaran.example.test/Sales/ProductManagement/Services/ProductManagementService.svc/GetProducts';
        Http::fake([$url => Http::response([
            ['id' => '8718', 'number' => '011057'],
            ['id' => '8719', 'number' => '11058'],
            ['id' => '8720', 'number' => '11058'],
        ])]);
        $unique = $this->product(8718);
        $conflict = $this->product(8719);
        $existing = $this->product(8720, 'existing');
        $this->product(8721, '11058');

        $this->artisan('rahkaran:backfill-item-codes')->expectsOutputToContain('پیش‌نمایش')->assertSuccessful();
        $this->assertNull($unique->refresh()->rahkaran_item_code);

        $this->artisan('rahkaran:backfill-item-codes', ['--apply' => true])->assertSuccessful();

        $this->assertSame('011057', $unique->refresh()->rahkaran_item_code);
        $this->assertNull($conflict->refresh()->rahkaran_item_code);
        $this->assertSame('existing', $existing->refresh()->rahkaran_item_code);
        Http::assertSent(fn (Request $request): bool => $request->url() === $url
            && $request['filter']['PageIndex'] === 0 && $request['filter']['TrackingFactorFilter'] === 'All');
    }

    private function product(int $salesId, ?string $code = null): Product
    {
        return Product::create([
            'name' => 'Product '.$salesId, 'slug' => 'product-'.$salesId, 'sku' => 'SKU-'.$salesId,
            'price' => 100, 'rahkaran_part_ref' => $salesId, 'rahkaran_item_code' => $code,
        ]);
    }
}
