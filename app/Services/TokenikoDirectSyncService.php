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
    public function __construct(
        private readonly TokenikoShopService $tokenikoShop,
        private readonly TapsiShopService $tapsiShop,
    ) {}

    public function sync(): array
    {
        $lock = Cache::lock('tokeniko:direct-sync', 300);

        if (! $lock->get()) {
            Log::warning('[TokenikoDirectSync] Skipped: another sync job is still active.');

            return $this->result(status: 'skipped');
        }

        try {
            return $this->run();
        } finally {
            $lock->release();
        }
    }

    private function run(): array
    {
        $prices = $this->tokenikoShop->fetchAndStore();

        if (empty($prices)) {
            Log::warning('[TokenikoDirectSync] No prices received from Tokeniko API.');

            return $this->result(status: 'failure');
        }

        $emergencyActive = Setting::getValue('tapsi_emergency_status', 'open') === 'closed';

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
            $priceChanged = $currentPrice !== $newPrice;

            if ($priceChanged) {
                $updates[$product->id] = ['price' => $newPrice];
            }

            if (! empty($product->tapsi_product_id) && ($priceChanged || $emergencyActive)) {
                $tapsiPrice = $this->tapsiShop->calculateTapsiPrice($newPrice);
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

        $tapsiSuccess = null;

        if (! empty($tapsiProducts) && config('tapsi.enabled')) {
            $tapsiSuccess = $this->tapsiShop->sendBatch($tapsiProducts);
        }

        $this->clearProductCache();
        $this->broadcastProducts();

        Log::info('[TokenikoDirectSync] Completed', [
            'products_updated' => count($updates),
            'tapsi_sent' => count($tapsiProducts),
            'tapsi_success' => $tapsiSuccess,
            'emergency_active' => $emergencyActive,
        ]);

        return $this->result(
            status: 'success',
            updated: count($updates),
            tapsiSent: count($tapsiProducts),
            tapsiSuccess: $tapsiSuccess,
            emergencyActive: $emergencyActive,
        );
    }

    private function result(
        string $status,
        int $updated = 0,
        int $tapsiSent = 0,
        ?bool $tapsiSuccess = null,
        bool $emergencyActive = false,
    ): array {
        return [
            'status' => $status,
            'updated' => $updated,
            'tapsi_sent' => $tapsiSent,
            'tapsi_success' => $tapsiSuccess,
            'emergency_active' => $emergencyActive,
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

        if (! empty($products)) {
            broadcast(new ProductsUpdated($products));
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
