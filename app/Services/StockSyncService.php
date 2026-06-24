<?php

namespace App\Services;

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

        $excludedSkus = config('hesabfa.excluded_skus', []);
        $updated = 0;
        $errors = [];

        foreach ($quantities as $item) {
            $itemCode = $item['ItemCode'] ?? $item['ProductCode'] ?? null;
            $quantity = $item['Quantity'] ?? $item['Physical'] ?? 0;

            if (! $itemCode) {
                continue;
            }

            $product = Product::where('sku', $itemCode)->first();

            if (! $product) {
                continue;
            }

            if (in_array($itemCode, $excludedSkus)) {
                continue;
            }

            try {
                $product->update([
                    'stock_quantity' => max(0, (int) $quantity),
                    'hesabfa_physical_stock' => max(0, (int) $quantity),
                    'hesabfa_stock_synced_at' => now(),
                ]);
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "SKU {$itemCode}: ".$e->getMessage();
            }
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

        $product->update([
            'stock_quantity' => max(0, $quantity),
            'hesabfa_physical_stock' => max(0, $quantity),
            'hesabfa_stock_synced_at' => now(),
        ]);

        Log::info('Hesabfa webhook: stock updated', [
            'item_code' => $itemCode,
            'quantity' => $quantity,
        ]);

        return ['message' => "Stock updated for {$itemCode}: {$quantity}"];
    }

    public function updatePriceByItemCode(string $itemCode, int $priceInRials): array
    {
        $product = Product::where('sku', $itemCode)->first();

        if (! $product) {
            return ['message' => "Product not found for SKU: {$itemCode}"];
        }

        $priceInToman = (int) round($priceInRials / 10);

        $product->update([
            'price' => $priceInToman,
        ]);

        Log::info('Hesabfa webhook: price updated', [
            'item_code' => $itemCode,
            'price_rials' => $priceInRials,
            'price_toman' => $priceInToman,
        ]);

        return ['message' => "Price updated for {$itemCode}: {$priceInToman} تومان"];
    }
}
