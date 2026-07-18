<?php

namespace App\Console\Commands\Tokeniko;

use App\Events\PriceBoardUpdated;
use App\Events\ProductsUpdated;
use App\Models\Setting;
use App\Services\PriceBoardService;
use App\Services\TapsiShopService;
use App\Services\TokenikoShopService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;

#[Signature('priceboard:sync')]
#[Description('Fetch prices from Tokeniko, broadcast to clients, and recalculate product prices')]
class SyncPriceBoard extends Command
{
    public function handle(
        PriceBoardService $priceBoard,
        TokenikoShopService $tokenikoShop,
        TapsiShopService $tapsiShop,
    ): int {
        $mode = config('pricing.mode', 'dynamic');

        if ($mode === 'direct') {
            return $this->syncDirect($tokenikoShop, $tapsiShop);
        }

        $this->info('Syncing price board...');

        $prices = $priceBoard->fetchAndStore();

        if (empty($prices)) {
            $this->warn('No prices received from API or cache.');

            return self::FAILURE;
        }

        $lastSync = $priceBoard->getLastSyncAt();
        $fromApi = $lastSync && $lastSync->diffInSeconds(now()) < 60;

        if (! $fromApi) {
            $this->warn('Using cached prices (API unavailable).');
        }

        broadcast(new PriceBoardUpdated($prices));

        $updated = $this->recalculateProductPrices();

        $this->info("Recalculated prices for {$updated} products.");

        if ($updated > 0) {
            $this->broadcastProducts();
            $redis = Cache::getStore()->getRedis();
            $keys = $redis->keys('api:products:*');
            if ($keys) {
                $redis->del($keys);
            }
            Cache::forget('priceboard:prices');
            Cache::forget('priceboard:last_sync_at');
        }

        Log::info('[PriceBoard] Sync completed', [
            'products_updated' => $updated,
            'from_cache' => ! $fromApi,
        ]);

        return self::SUCCESS;
    }

    private function recalculateProductPrices(): int
    {
        $products = Product::whereNotNull('price_board_item')->get();

        $updates = [];

        foreach ($products as $product) {
            $newPrice = $product->calculatePrice();

            if ($newPrice !== null && (float) $product->price !== $newPrice) {
                $updates[$product->id] = ['price' => $newPrice];
                $this->line("  Will update {$product->name}: {$product->price} -> {$newPrice}");
            }
        }

        if (empty($updates)) {
            return 0;
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $id => $data) {
                Product::where('id', $id)->update($data);
            }
        });

        return count($updates);
    }

    private function broadcastProducts(): void
    {
        $mode = config('pricing.mode', 'dynamic');
        $query = Product::query()
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'images']);

        if ($mode === 'direct') {
            $query->whereNotNull('tokeniko_sku')
                ->where('tokeniko_sku', '!=', '');
        } else {
            $query->whereNotNull('price_board_item');
        }

        $products = $query->get()
            ->map(fn (Product $p) => $this->formatProduct($p))
            ->toArray();

        broadcast(new ProductsUpdated($products));
    }

    private function formatProduct(Product $p): array
    {
        $price = (int) $p->price;
        $primaryImage = $p->images->firstWhere('is_primary', true) ?? $p->images->first();

        if ($p->price_board_item) {
            $taxKey = str_starts_with($p->price_board_item, 'Gold') ? 'tax_gold' : 'tax_silver';
        } else {
            $taxKey = $p->metal_type?->value === 'gold' ? 'tax_gold' : 'tax_silver';
        }

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

    private function syncDirect(TokenikoShopService $tokenikoShop, TapsiShopService $tapsiShop): int
    {
        $this->info('Direct mode: syncing from Tokeniko shop API...');

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

        $this->info('Updated '.count($updates).' products in DB.');

        if (! empty($tapsiProducts) && ! config('tapsi.enabled')) {
            $this->warn('Tapsi sync disabled — skipped sending '.count($tapsiProducts).' products.');
        }

        $this->broadcastProducts();

        Log::info('[PriceBoard] Direct sync completed', [
            'products_updated' => count($updates),
            'tapsi_sent' => count($tapsiProducts),
        ]);

        return self::SUCCESS;
    }
}
