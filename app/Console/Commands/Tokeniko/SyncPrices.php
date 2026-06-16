<?php

namespace App\Console\Commands\Tokeniko;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;

#[Signature('app:sync-prices')]
#[Description('Sync product prices from Tokeniko API every minute')]
class SyncPrices extends Command
{
    private const LOCK_KEY = 'tokeniko_sync_lock';

    private const LOCK_TTL = 300;

    private const API_URL = 'https://apigateway.tokeniko.com/shop/api/Category/getPrices';

    private const SKU_MAP = [
        'zioto-gold-bar-0.5gram-995' => 'ZGB5-0000-5',
        'zioto-gold-bar-1gram-995' => 'ZGB5-0001-0',
        'ziotoplus-gold-bar-1gram-9999' => 'ZPGB5-0001-0',
        'zioto-silver-bar-2.5gram' => 'ZSB9-0002-5',
        'zioto-silver-bar-5gram' => 'ZSB9-0005-0',
        'zioto-silver-bar-10gram' => 'ZSB9-0010-0',
        'zioto-silver-bar-15gram' => 'ZSB9-0015-0',
        'zioto-silver-bar-1oz' => 'ZSB9-0031-1',
        'zioto-silver-bar-50gram' => 'ZSB9-0050-0',
    ];

    public function handle(): int
    {
        if (Cache::has(self::LOCK_KEY)) {
            $this->warn('Previous sync job is still active. Skipping.');

            return self::SUCCESS;
        }

        Cache::put(self::LOCK_KEY, true, self::LOCK_TTL);

        try {
            $response = Http::timeout(15)->withoutVerifying()->get(self::API_URL);

            if ($response->failed()) {
                Log::error('[Tokeniko Sync] API request failed', ['status' => $response->status()]);
                $this->error('API request failed with status: '.$response->status());

                return self::FAILURE;
            }

            $data = $response->json();

            if (! is_array($data) || empty($data['Model'])) {
                Log::error('[Tokeniko Sync] Invalid API response structure');
                $this->error('Invalid API response structure');

                return self::FAILURE;
            }

            $apiPrices = [];
            foreach ($data['Model'] as $item) {
                if (empty($item['Name']) || ! isset($item['SellPrice'])) {
                    continue;
                }
                $key = mb_strtolower(trim($item['Name']));
                $apiPrices[$key] = (string) round(((float) $item['SellPrice']));
            }

            $skus = array_values(self::SKU_MAP);
            $products = Product::whereIn('sku', $skus)->get()->keyBy('sku');

            $updated = 0;

            foreach (self::SKU_MAP as $tokenikoName => $sku) {
                if (! isset($apiPrices[$tokenikoName])) {
                    continue;
                }

                $product = $products->get($sku);

                if (! $product) {
                    continue;
                }

                $newPrice = $apiPrices[$tokenikoName];
                $currentPrice = (string) $product->price;

                if ($currentPrice !== $newPrice) {
                    $product->update(['price' => $newPrice]);
                    $updated++;
                    $this->line("Updated {$sku}: {$currentPrice} -> {$newPrice}");
                }
            }

            $this->info("Sync complete. Updated {$updated} products.");
            Log::info('[Tokeniko Sync] Completed', ['updated' => $updated]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('[Tokeniko Sync] Exception: '.$e->getMessage());
            $this->error('Exception: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            Cache::forget(self::LOCK_KEY);
        }
    }
}
