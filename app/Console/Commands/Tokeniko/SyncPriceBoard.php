<?php

namespace App\Console\Commands\Tokeniko;

use App\Events\PriceBoardUpdated;
use App\Events\ProductsUpdated;
use App\Models\Setting;
use App\Services\PriceBoardService;
use App\Services\TokenikoDirectSyncService;
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
        TokenikoDirectSyncService $sync,
    ): int {
        $mode = config('pricing.mode', 'dynamic');

        if ($mode === 'direct') {
            return $this->syncDirect($priceBoard, $sync);
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

    private function syncDirect(PriceBoardService $priceBoard, TokenikoDirectSyncService $sync): int
    {
        $this->info('Direct mode: syncing from Tokeniko shop API...');

        $priceBoard->fetchAndStore();

        $result = $sync->sync();

        if ($result['status'] === 'skipped') {
            $this->warn('Previous sync job is still active. Skipping.');

            return self::SUCCESS;
        }

        if ($result['status'] === 'failure') {
            $this->warn('No prices received from Tokeniko API.');

            return self::FAILURE;
        }

        if ($result['emergency_active']) {
            $this->warn('EMERGENCY LOCK ACTIVE — all Tapsi stock sent as 0.');
        }

        $this->info('Updated '.$result['updated'].' products in DB.');

        if ($result['tapsi_sent'] > 0) {
            $outcome = $result['tapsi_success'] ? 'success' : 'failed';
            $this->info("Sent {$result['tapsi_sent']} products to Tapsi Shop ({$outcome}).");
        } elseif (! config('tapsi.enabled')) {
            $this->warn('Tapsi sync disabled — skipped sending to Tapsi.');
        }

        Log::info('[PriceBoard] Direct sync completed', [
            'products_updated' => $result['updated'],
            'tapsi_sent' => $result['tapsi_sent'],
            'tapsi_success' => $result['tapsi_success'],
        ]);

        return self::SUCCESS;
    }
}
