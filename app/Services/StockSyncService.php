<?php

namespace App\Services;

use App\Models\HesabfaSyncLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;

class StockSyncService
{
    public function __construct(
        private HesabfaService $hesabfa,
    ) {}

    public function syncAllStock(): array
    {
        if (! $this->hesabfa->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات حسابفا یافت نشد'];
        }

        if (! config('hesabfa.sync_stock')) {
            return ['success' => false, 'message' => 'همگام‌سازی موجودی غیرفعال است'];
        }

        $warehouseCode = config('hesabfa.warehouse_code', '11');
        $quantities = $this->hesabfa->getAllItemQuantities($warehouseCode);

        if (empty($quantities)) {
            return ['success' => false, 'message' => 'موجودی از حسابفا دریافت نشد'];
        }

        $stockUpdates = $this->prepareStockUpdates($quantities);

        if ($stockUpdates->isEmpty()) {
            return ['success' => true, 'message' => 'هیچ محصولی برای بروزرسانی یافت نشد', 'updated' => 0, 'errors' => []];
        }

        return $this->batchUpdateStock($stockUpdates);
    }

    private function prepareStockUpdates(array $quantities): Collection
    {
        $excludedSkus = config('hesabfa.excluded_skus', []);

        return collect($quantities)
            ->filter(fn ($item) => ! empty($item['ProductCode']) || ! empty($item['Code']))
            ->map(fn ($item) => [
                'sku' => ! empty($item['ProductCode']) ? $item['ProductCode'] : $item['Code'],
                'quantity' => max(0, (int) ($item['Quantity'] ?? 0)),
            ])
            ->reject(fn ($item) => in_array($item['sku'], $excludedSkus))
            ->unique('sku');
    }

    private function batchUpdateStock(Collection $stockUpdates): array
    {
        $updatedAt = now();
        $errors = [];
        $updated = 0;
        $reservedEnabled = config('hesabfa.enable_reserved_stock', false);

        $stockUpdates->chunk(100)->each(function ($chunk) use ($updatedAt, &$errors, &$updated, $reservedEnabled) {
            $skus = $chunk->pluck('sku')->toArray();
            $quantityMap = $chunk->pluck('quantity', 'sku')->toArray();

            $products = Product::whereIn('sku', $skus)->get()->keyBy('sku');

            foreach ($products as $product) {
                $quantity = $quantityMap[$product->sku] ?? null;

                if ($quantity === null) {
                    continue;
                }

                if ($product->hesabfa_stock_locked || $product->hesabfa_exclude_from_sync) {
                    continue;
                }

                try {
                    $updateData = [
                        'hesabfa_physical_stock' => $quantity,
                        'hesabfa_stock_synced_at' => $updatedAt,
                    ];

                    if ($reservedEnabled) {
                        $reserved = (int) ($product->hesabfa_reserved_stock ?? 0);
                        $manualReserved = (int) ($product->hesabfa_manual_reserved ?? 0);
                        $updateData['stock_quantity'] = max(0, $quantity - $reserved - $manualReserved);
                    } else {
                        $updateData['stock_quantity'] = $quantity;
                    }

                    $product->update($updateData);
                    $updated++;
                } catch (\Exception $e) {
                    $errors[] = "SKU {$product->sku}: ".$e->getMessage();
                }
            }
        });

        if ($updated > 0) {
            HesabfaSyncLog::create([
                'sync_type' => 'stock_sync',
                'status' => 'success',
                'response_data' => ['updated_count' => $updated, 'errors' => $errors],
            ]);
        }

        return [
            'success' => true,
            'message' => "{$updated} محصول بروزرسانی شد",
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function updateStockByItemCode(string $itemCode, int $quantity): array
    {
        $product = Product::where('sku', $itemCode)->first();

        if (! $product) {
            Log::info('Hesabfa webhook: product not found for item code', ['item_code' => $itemCode]);

            return ['message' => "Product not found for SKU: {$itemCode}"];
        }

        $excludedSkus = config('hesabfa.excluded_skus', []);
        if (in_array($itemCode, $excludedSkus)) {
            return ['message' => "SKU {$itemCode} is excluded from sync"];
        }

        if ($product->hesabfa_stock_locked || $product->hesabfa_exclude_from_sync) {
            return ['message' => "SKU {$itemCode} stock is locked or excluded"];
        }

        $reservedEnabled = config('hesabfa.enable_reserved_stock', false);

        $updateData = [
            'hesabfa_physical_stock' => max(0, $quantity),
            'hesabfa_stock_synced_at' => now(),
        ];

        if ($reservedEnabled) {
            $reserved = (int) ($product->hesabfa_reserved_stock ?? 0);
            $manualReserved = (int) ($product->hesabfa_manual_reserved ?? 0);
            $updateData['stock_quantity'] = max(0, $quantity - $reserved - $manualReserved);
        } else {
            $updateData['stock_quantity'] = max(0, $quantity);
        }

        $product->update($updateData);

        Log::info('Hesabfa webhook: stock updated', [
            'item_code' => $itemCode,
            'quantity' => $quantity,
        ]);

        return ['message' => "Stock updated for {$itemCode}: {$quantity}"];
    }

    public function updatePriceByItemCode(string $itemCode, int $priceIncoming): array
    {
        $product = Product::where('sku', $itemCode)->first();

        if (! $product) {
            return ['message' => "Product not found for SKU: {$itemCode}"];
        }

        $priceUnit = config('hesabfa.price_unit', 'rial');
        $price = $priceUnit === 'rial' ? (int) round($priceIncoming / 10) : $priceIncoming;

        $product->update([
            'price' => $price,
        ]);

        Log::info('Hesabfa webhook: price updated', [
            'item_code' => $itemCode,
            'price_incoming' => $priceIncoming,
            'price_unit' => $priceUnit,
            'price_stored' => $price,
        ]);

        return ['message' => "Price updated for {$itemCode}: {$price}"];
    }
}
