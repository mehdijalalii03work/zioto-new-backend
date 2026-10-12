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
        // Serial/manual inventory is the only source of truth now; stock is
        // never pulled from Hesabfa on any path (periodic, manual, webhook).
        // Price and invoice paths are independent and stay untouched.
        return ['success' => false, 'message' => 'دریافت موجودی از حسابفا متوقف شده است'];
    }

    public function updateStockByItemCode(string $itemCode, int $quantity): array
    {
        // Stock intake is stopped on every path; the quantity is acknowledged
        // but deliberately never written to any stock column.
        Log::info('Hesabfa webhook: stock intake is stopped, quantity ignored', [
            'item_code' => $itemCode,
            'quantity' => $quantity,
        ]);

        return ['message' => "Stock intake is stopped; ignored quantity for SKU: {$itemCode}"];
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
