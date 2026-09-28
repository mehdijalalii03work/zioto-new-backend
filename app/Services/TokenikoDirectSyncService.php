<?php

namespace App\Services;

use App\Events\ProductsUpdated;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;

class TokenikoDirectSyncService
{
    /**
     * Cache key holding the payload we last delivered to Tapsi, so the next
     * dynamic run only pushes products whose price or stock actually changed.
     */
    private const LAST_PUSHED_CACHE_KEY = 'tapsi:last_pushed_payload';

    public function __construct(
        private readonly TokenikoShopService $tokenikoShop,
        private readonly TapsiShopService $tapsiShop,
    ) {}

    public function sync(): array
    {
        return $this->guarded(fn (): array => $this->run(updatePrices: true));
    }

    /**
     * Push the already-computed product prices and stock to Tapsi.
     *
     * Used by the dynamic flow, where products.price is owned by the price board
     * (PersianAPI + Tala) and must not be overwritten from the Tokeniko shop API.
     *
     * Only products whose price or stock differs from the last delivered payload
     * are sent; an emergency status flip changes every stock and therefore pushes all.
     */
    public function syncTapsi(): array
    {
        return $this->guarded(fn (): array => $this->run(updatePrices: false));
    }

    private function guarded(callable $callback): array
    {
        $lock = Cache::lock('tokeniko:direct-sync', 300);

        if (! $lock->get()) {
            Log::warning('[TokenikoDirectSync] Skipped: another sync job is still active.');

            return $this->result(status: 'skipped');
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function run(bool $updatePrices): array
    {
        $prices = $updatePrices ? $this->tokenikoShop->fetchAndStore() : [];

        if ($updatePrices && empty($prices)) {
            Log::warning('[TokenikoDirectSync] No prices received from Tokeniko API.');

            return $this->result(status: 'failure');
        }

        $emergencyActive = Setting::getValue('tapsi_emergency_status', 'open') === 'closed';

        $products = Product::whereNotNull('tokeniko_sku')
            ->where('tokeniko_sku', '!=', '')
            ->get();

        $updates = [];
        $candidates = [];

        foreach ($products as $product) {
            $sku = mb_strtolower(trim($product->tokeniko_sku));

            if ($updatePrices && ! isset($prices[$sku])) {
                continue;
            }

            $newPrice = $updatePrices ? (float) $prices[$sku] : (float) $product->price;

            if ($updatePrices && (float) $product->price !== $newPrice) {
                $updates[$product->id] = ['price' => $newPrice];
            }

            if (! empty($product->tapsi_product_id)) {
                $tapsiPrice = $this->tapsiShop->calculateTapsiPrice($newPrice);
                $availableStock = $emergencyActive ? 0 : ($product->sellable_stock ?? $product->stock_quantity ?? 0);

                $candidates[$product->id] = [
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

        $tapsiSkipped = $this->tapsiSkipReason();
        $tapsiProducts = [];
        $tapsiSuccess = null;

        if ($tapsiSkipped === null && $candidates !== []) {
            $tapsiProducts = $updatePrices
                ? array_values($candidates)
                : $this->changedSinceLastPush($candidates);

            if ($tapsiProducts !== []) {
                $tapsiSuccess = $this->tapsiShop->sendBatch($tapsiProducts);

                if ($tapsiSuccess === true) {
                    $this->rememberPushedPayload($candidates);
                }
            }
        }

        $this->clearProductCache();

        if ($updatePrices) {
            $this->broadcastProducts();
        }

        Log::info('[TokenikoDirectSync] Completed', [
            'mode' => $updatePrices ? 'direct' : 'tapsi',
            'products_updated' => count($updates),
            'tapsi_sent' => count($tapsiProducts),
            'tapsi_success' => $tapsiSuccess,
            'tapsi_skipped' => $tapsiSkipped,
            'emergency_active' => $emergencyActive,
        ]);

        return $this->result(
            status: 'success',
            updated: count($updates),
            tapsiSent: count($tapsiProducts),
            tapsiSuccess: $tapsiSuccess,
            emergencyActive: $emergencyActive,
            directPrices: $prices,
            tapsiSkipped: $tapsiSkipped,
        );
    }

    /**
     * Why nothing may be delivered to Tapsi right now, or null when we may send.
     */
    private function tapsiSkipReason(): ?string
    {
        if (! config('tapsi.enabled')) {
            return 'disabled';
        }

        if (! $this->tapsiShop->hasToken()) {
            return 'token_missing';
        }

        return null;
    }

    /**
     * Products whose Tapsi price or stock differs from the last delivered payload.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function changedSinceLastPush(array $candidates): array
    {
        $lastPushed = Cache::get(self::LAST_PUSHED_CACHE_KEY)['products'] ?? [];

        $changed = [];

        foreach ($candidates as $productId => $payload) {
            $previous = $lastPushed[$productId] ?? null;

            if ($previous === null
                || (int) $previous['price'] !== (int) $payload['price']
                || (int) $previous['stock'] !== (int) $payload['stock']) {
                $changed[] = $payload;
            }
        }

        return $changed;
    }

    /**
     * Remember the payload Tapsi now holds so the next run only sends deltas.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function rememberPushedPayload(array $candidates): void
    {
        $products = [];

        foreach ($candidates as $productId => $payload) {
            $products[$productId] = [
                'price' => (int) $payload['price'],
                'stock' => (int) $payload['stock'],
            ];
        }

        Cache::forever(self::LAST_PUSHED_CACHE_KEY, [
            'products' => $products,
            'pushed_at' => now()->toIso8601String(),
        ]);
    }

    private function result(
        string $status,
        int $updated = 0,
        int $tapsiSent = 0,
        ?bool $tapsiSuccess = null,
        bool $emergencyActive = false,
        array $directPrices = [],
        ?string $tapsiSkipped = null,
    ): array {
        return [
            'status' => $status,
            'updated' => $updated,
            'tapsi_sent' => $tapsiSent,
            'tapsi_success' => $tapsiSuccess,
            'tapsi_skipped' => $tapsiSkipped,
            'emergency_active' => $emergencyActive,
            'direct_prices' => $directPrices,
        ];
    }

    private function broadcastProducts(): void
    {
        $products = Product::query()
            ->with(['category:id,name,slug', 'brand:id,name,slug', 'images'])
            ->whereNotNull('tokeniko_sku')
            ->get()
            ->map(fn (Product $p) => $this->formatProduct($p))
            ->toArray();

        if ($products === []) {
            return;
        }

        try {
            $pending = broadcast(new ProductsUpdated($products));
            unset($pending);
        } catch (\Throwable $e) {
            Log::warning('[TokenikoDirectSync] Broadcast failed; sync continues.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatProduct(Product $p): array
    {
        $price = (int) $p->price;

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

    private function clearProductCache(): void
    {
        $store = Cache::getStore();

        if (! method_exists($store, 'getRedis')) {
            return;
        }

        $redis = $store->getRedis();
        $keys = $redis->keys('api:products:*');

        if ($keys) {
            $redis->del($keys);
        }
    }
}
