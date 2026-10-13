<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockReservationStatus;
use App\Models\ProductSerial;
use App\Models\ProductStockReservation;
use Modules\Product\Models\Product;

/**
 * Fresh, grouped stock overlay for cached listings.
 *
 * Catalog data may be cached for a minute, but stock never is: callers
 * replace the cached stock flags with these live numbers at response time.
 * Validation paths must never use cached or replica reads — they go through
 * AvailabilityCheck instead.
 *
 * @return array<int, int> product id => sellable units (unknown ids => 0)
 */
final class SerialAvailabilityService
{
    /** @param  list<int>  $productIds */
    public function countsForProducts(array $productIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));

        $result = array_fill_keys($ids, 0);

        if ($ids === []) {
            return $result;
        }

        $modes = Product::query()->useWritePdo()->whereIn('id', $ids)->pluck('inventory_mode', 'id');

        $serialIds = [];
        $manualIds = [];

        foreach ($ids as $id) {
            $mode = $modes->get($id);

            if ($mode instanceof InventoryMode ? $mode === InventoryMode::Serial : $mode === InventoryMode::Serial->value) {
                $serialIds[] = $id;
            } else {
                $manualIds[] = $id;
            }
        }

        if ($serialIds !== []) {
            $free = ProductSerial::query()->useWritePdo()->whereIn('product_id', $serialIds)
                ->where('status', SerialStatus::Available->value)
                ->selectRaw('product_id, COUNT(*) as aggregate')
                ->groupBy('product_id')
                ->pluck('aggregate', 'product_id');

            foreach ($serialIds as $id) {
                $result[$id] = (int) ($free->get($id) ?? 0);
            }
        }

        if ($manualIds !== []) {
            $stocks = Product::query()->useWritePdo()->whereIn('id', $manualIds)->pluck('stock_quantity', 'id');

            $held = ProductStockReservation::query()->useWritePdo()->whereIn('product_id', $manualIds)
                ->where('status', StockReservationStatus::Reserved->value)
                ->selectRaw('product_id, COALESCE(SUM(quantity), 0) as aggregate')
                ->groupBy('product_id')
                ->pluck('aggregate', 'product_id');

            foreach ($manualIds as $id) {
                $result[$id] = max(0, (int) ($stocks->get($id) ?? 0) - (int) ($held->get($id) ?? 0));
            }
        }

        return $result;
    }
}
