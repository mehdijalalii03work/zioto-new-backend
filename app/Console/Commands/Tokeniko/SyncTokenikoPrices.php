<?php

namespace App\Console\Commands\Tokeniko;

use App\Events\ProductsUpdated;
use App\Models\Setting;
use App\Services\TapsiShopService;
use App\Services\TokenikoShopService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;

#[Signature('tokeniko:sync-direct')]
#[Description('Fetch prices from Tokeniko shop API, update DB, send to Tapsi Shop')]
class SyncTokenikoPrices extends Command
{
    public function handle(
        TokenikoShopService $tokenikoShop,
        TapsiShopService $tapsiShop,
    ): int {
        $this->info('Syncing prices from Tokeniko shop API...');

        $prices = $tokenikoShop->fetchAndStore();

        if (empty($prices)) {
            $this->warn('No prices received from Tokeniko API.');

            return self::FAILURE;
        }

        $emergencyActive = Setting::getValue('tapsi_emergency_status', 'open') === 'closed';

        if ($emergencyActive) {
            $this->warn('EMERGENCY LOCK ACTIVE — all Tapsi stock will be 0.');
        }

        $products = Product::whereNotNull('tokeniko_sku')
            ->where('tokeniko_sku', '!=', '')
            ->get();

        $updates = [];
        $tapsiProducts = [];

        foreach ($products as $product) {
            $sku = mb_strtolower(trim($product->tokeniko_sku));

            if (! isset($prices[$sku])) {
                continue;
            }

            $newPrice = (float) $prices[$sku];
            $currentPrice = (float) $product->price;

            if ($currentPrice !== $newPrice) {
                $updates[$product->id] = ['price' => $newPrice];
                $this->line("  {$product->name}: {$currentPrice} -> {$newPrice}");
            }

            if (! empty($product->tapsi_product_id)) {
                $tapsiPrice = $tapsiShop->calculateTapsiPrice($newPrice);
                $availableStock = $emergencyActive ? 0 : ($product->sellable_stock ?? $product->stock_quantity ?? 0);

                $tapsiProducts[] = [
                    'id' => $product->tapsi_product_id,
                    'price' => $tapsiPrice,
                    'specialprice' => $tapsiPrice,
                    'stock' => (int) $availableStock,
                    'referenceCode' => 'laravel_sync_'.$product->id.'_'.time(),
                ];
            }
        }

        if (! empty($updates)) {
            DB::transaction(function () use ($updates) {
                foreach ($updates as $id => $data) {
                    Product::where('id', $id)->update($data);
                }
            });
        }

        if (! empty($tapsiProducts) && config('tapsi.enabled')) {
            $tapsiShop->sendBatch($tapsiProducts);
        }

        if (! empty($tapsiProducts) && ! config('tapsi.enabled')) {
            $this->warn('Tapsi sync disabled — skipped sending '.count($tapsiProducts).' products.');
        }

        $this->info('Updated '.count($updates).' products in DB.');

        $this->broadcastProducts();

        Log::info('[TokenikoSyncDirect] Completed', [
            'products_updated' => count($updates),
            'tapsi_sent' => count($tapsiProducts),
        ]);

        return self::SUCCESS;
    }

    private function broadcastProducts(): void
    {
        $products = Product::query()
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'images'])
            ->whereNotNull('tokeniko_sku')
            ->get()
            ->map(fn (Product $p) => $this->formatProduct($p))
            ->toArray();

        if (! empty($products)) {
            broadcast(new ProductsUpdated($products));
        }
    }

    private function formatProduct(Product $p): array
    {
        $price = (int) $p->price;
        $primaryImage = $p->images->firstWhere('is_primary', true) ?? $p->images->first();

        $taxKey = $p->metal_type?->value === 'gold' ? 'tax_gold' : 'tax_silver';
        $taxRate = (float) Setting::getValue($taxKey, 0);
        $priceBeforeTax = $taxRate > 0 ? round($price / (1 + $taxRate / 100)) : $price;
        $taxAmount = $price - $priceBeforeTax;

        return [
            'id' => $p->id,
            'price' => $price,
            'price_before_tax' => $priceBeforeTax,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'old' => null,
        ];
    }
}
