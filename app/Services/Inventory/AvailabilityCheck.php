<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockReservationStatus;
use App\Models\ProductSerial;
use App\Models\ProductStockReservation;
use App\Support\Platform;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;

/**
 * Availability check shared by order submit and payment init.
 *
 * currentRead is used only while the caller holds sorted product mutexes.
 * It reads current holding rows even inside an older surrounding transaction.
 *
 * Buckets per product (quantities aggregated across lines first):
 * - serial mode: free serials plus this order's own holdings.
 * - manual mode: capacity (stock minus active reservations) plus this
 *   order's own active reservations.
 *
 * No purchase path depends on the hesabfa switches or the legacy numeric
 * columns anymore; those columns are history only.
 *
 * Tapsi never consumes site stock, so tapsi lines are always valid here.
 *
 * @param  iterable<array{product: ?Product, product_id: ?int, quantity: int}>  $lines
 * @return array{valid: bool, message?: string, product?: string, items?: list<array{product_id: ?int, product_name: string, requested: int, available: int}>}
 */
final class AvailabilityCheck
{
    public static function check(iterable $lines, string $platform, ?Order $order = null, bool $currentRead = false): array
    {
        if ($platform === Platform::TAPSI) {
            return ['valid' => true];
        }

        $needs = [];

        foreach ($lines as $line) {
            $product = $line['product'] ?? null;
            $productId = $line['product_id'] ?? $product?->id;

            if ($productId === null) {
                continue;
            }

            $needs[$productId] ??= ['product' => $product, 'quantity' => 0];
            $needs[$productId]['quantity'] += (int) $line['quantity'];

            if ($needs[$productId]['product'] === null) {
                $needs[$productId]['product'] = $product;
            }
        }

        $insufficient = [];
        $items = [];

        foreach ($needs as $productId => $need) {
            $product = $need['product'] ?? Product::find($productId);

            if ($product === null) {
                continue;
            }

            $available = self::availableFor($product, $need['quantity'], $order, $currentRead);

            if ($need['quantity'] > $available) {
                $insufficient[] = "{$product->name}: موجودی {$available}، درخواست {$need['quantity']}";
                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'requested' => $need['quantity'],
                    'available' => $available,
                ];
            }
        }

        if ($insufficient !== []) {
            return [
                'valid' => false,
                'message' => 'موجودی برخی محصولات کافی نیست: '.implode(' | ', $insufficient),
                'product' => implode(', ', $insufficient),
                'items' => $items,
            ];
        }

        return ['valid' => true];
    }

    private static function availableFor(Product $product, int $needed, ?Order $order, bool $currentRead): int
    {
        $snapshot = $order?->items()->where('product_id', $product->id)->first();
        $mode = $snapshot?->inventory_mode ?? $product->inventory_mode ?? InventoryMode::Manual;

        if ($mode === InventoryMode::Serial) {
            return self::serialAvailable($product, $order, $currentRead, $needed);
        }

        return self::capacityAvailable($product, $order, $currentRead);
    }

    private static function serialAvailable(Product $product, ?Order $order, bool $currentRead, int $needed): int
    {
        $query = $product->serials()->where('status', SerialStatus::Available->value);
        $free = $currentRead ? $query->orderBy('id')->limit($needed)->lockForUpdate()->get(['id'])->count() : $query->count();

        return $free + self::ownSerialHoldings($product, $order);
    }

    private static function capacityAvailable(Product $product, ?Order $order, bool $currentRead): int
    {
        $query = $product->manualReservations()->where('status', StockReservationStatus::Reserved->value);
        $active = $currentRead ? $query->lockForUpdate()->get(['quantity'])->sum('quantity') : $query->sum('quantity');

        $own = 0;

        if ($order !== null) {
            $own = (int) ProductStockReservation::where('product_id', $product->id)
                ->where('status', StockReservationStatus::Reserved->value)
                ->whereHas('orderItem', fn ($query) => $query->where('order_id', $order->id))
                ->sum('quantity');
        }

        return max(0, (int) $product->stock_quantity - (int) $active + $own);
    }

    private static function ownSerialHoldings(Product $product, ?Order $order): int
    {
        if ($order === null) {
            return 0;
        }

        $itemIds = $order->items()->pluck('id')->all();

        if ($itemIds === []) {
            return 0;
        }

        return ProductSerial::where('product_id', $product->id)
            ->whereIn('current_order_item_id', $itemIds)
            ->whereIn('status', [SerialStatus::Reserved->value, SerialStatus::Allocated->value])
            ->count();
    }
}
