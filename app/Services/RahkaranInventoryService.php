<?php

namespace App\Services;

use App\Models\HesabfaSyncLog;
use App\Models\RahkaranSerial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use RuntimeException;

class RahkaranInventoryService
{
    public function __construct(
        private RahkaranService $rahkaran,
        private RahkaranSettings $settings,
    ) {}

    public function syncAllSerials(): array
    {
        if (! $this->rahkaran->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات راهکاران کامل نیست', 'updated' => 0];
        }

        if (! is_numeric($this->settings->get('inventory_id')) || ! is_numeric($this->settings->get('plant_id'))) {
            return ['success' => false, 'message' => 'شناسه انبار و مرکز نگهداری راهکاران باید تنظیم شوند', 'updated' => 0];
        }
        $updated = 0;
        $updatedProducts = 0;
        $errors = [];

        foreach (Product::query()->published()->whereNotNull('sku')->get() as $product) {
            $partRef = $product->rahkaran_part_ref;
            if (! $partRef) {
                $errors[$product->sku] = 'نگاشت کالای راهکاران تنظیم نشده است';

                continue;
            }

            try {
                if ($product->rahkaran_serial_tracked) {
                    foreach ($this->rahkaran->getSerialsForPart((int) $partRef) as $serialRecord) {
                        $serial = (string) ($serialRecord['Serial'] ?? '');
                        if ($serial === '') {
                            continue;
                        }

                        RahkaranSerial::query()->firstOrCreate(
                            ['serial' => $serial],
                            ['product_id' => $product->id, 'status' => 'available', 'synced_at' => now()],
                        );
                        RahkaranSerial::query()->where('serial', $serial)->update(['synced_at' => now()]);
                        $updated++;
                    }
                }

                $physicalStock = $this->rahkaran->getStock(
                    (int) $partRef,
                    (int) $this->settings->get('inventory_id'),
                    (int) $this->settings->get('plant_id'),
                );
                $product->rahkaran_physical_stock = max(0, (int) floor($physicalStock));
                $product->stock_quantity = $product->rahkaran_serial_tracked
                    ? max(0, min(
                        RahkaranSerial::query()->where('product_id', $product->id)->where('status', 'available')->count(),
                        $product->rahkaran_physical_stock,
                    ) - (int) $product->hesabfa_manual_reserved)
                    : $product->sellableRahkaranStock($product->rahkaran_physical_stock);
                $product->save();
                $updatedProducts++;
            } catch (\Throwable $exception) {
                $errors[$product->sku] = $exception->getMessage();
                Log::error('Rahkaran serial sync failed', ['sku' => $product->sku, 'message' => $exception->getMessage()]);
            }
        }

        HesabfaSyncLog::query()->create([
            'sync_type' => 'rahkaran_stock_sync',
            'status' => $errors === [] ? 'success' : 'failed',
            'request_data' => ['mapped_products' => Product::query()->whereNotNull('rahkaran_part_ref')->count()],
            'response_data' => ['synced_serial_count' => $updated, 'updated_product_count' => $updatedProducts, 'errors' => $errors],
            'error_message' => $errors === [] ? null : implode('; ', array_map(static fn ($sku, $error) => "{$sku}: {$error}", array_keys($errors), $errors)),
        ]);

        return [
            'success' => $errors === [],
            'message' => "موجودی {$updatedProducts} کالا همگام شد؛ {$updated} سریال خوانده شد",
            'updated' => $updated,
            'updated_products' => $updatedProducts,
            'errors' => $errors,
        ];
    }

    public function reserveForOrder(Order $order): bool
    {
        $order->loadMissing('items.product');

        return DB::transaction(function () use ($order): bool {
            foreach ($order->items as $item) {
                if (! $item->product) {
                    continue;
                }

                $orderItem = OrderItem::query()->lockForUpdate()->find($item->id);
                $stockProduct = Product::query()
                    ->lockForUpdate()
                    ->find($item->product->base_product_id ?? $item->product_id);
                if (! $orderItem || ! $stockProduct) {
                    continue;
                }

                if ($stockProduct->rahkaran_serial_tracked) {
                    $required = (int) $item->quantity * $item->product->pack_size;
                    $existing = RahkaranSerial::query()->where('order_item_id', $item->id)->count();
                    $needed = max(0, $required - $existing);
                    if ($needed === 0) {
                        continue;
                    }

                    $availableSerialCount = RahkaranSerial::query()
                        ->where('product_id', $stockProduct->id)
                        ->where('status', 'available')
                        ->count();
                    $reservedSerialCount = RahkaranSerial::query()
                        ->where('product_id', $stockProduct->id)
                        ->where('status', 'reserved')
                        ->count();
                    $remainingPhysicalStock = max(
                        0,
                        (int) ($stockProduct->rahkaran_physical_stock ?? 0)
                            - $reservedSerialCount
                            - (int) $stockProduct->hesabfa_manual_reserved,
                    );
                    if ($needed > min($availableSerialCount, $remainingPhysicalStock)) {
                        throw new RuntimeException("موجودی سریالی محصول {$item->product->sku} کافی نیست.");
                    }

                    $serials = RahkaranSerial::query()
                        ->where('product_id', $stockProduct->id)
                        ->where('status', 'available')
                        ->orderBy('id')
                        ->limit($needed)
                        ->lockForUpdate()
                        ->get();

                    if ($serials->count() < $needed) {
                        throw new RuntimeException("سریال کافی برای محصول {$item->product->sku} موجود نیست.");
                    }

                    foreach ($serials as $serial) {
                        $serial->update(['status' => 'reserved', 'order_item_id' => $item->id]);
                    }

                    $stockProduct->stock_quantity = min(
                        RahkaranSerial::query()->where('product_id', $stockProduct->id)->where('status', 'available')->count(),
                        (int) ($stockProduct->rahkaran_physical_stock ?? 0),
                    );
                    $stockProduct->save();

                    continue;
                }

                if (setting('inventory_source', 'hesabfa') !== 'rahkaran'
                    || $orderItem->rahkaran_stock_consumed
                    || $orderItem->rahkaran_stock_reserved_quantity > 0) {
                    continue;
                }

                $required = (int) $item->quantity * $item->product->pack_size;
                if ($required > $stockProduct->sellableRahkaranStock()) {
                    throw new RuntimeException("موجودی کالای {$item->product->sku} کافی نیست.");
                }

                $stockProduct->increment('rahkaran_reserved_stock', $required);
                $orderItem->update(['rahkaran_stock_reserved_quantity' => $required]);
                $stockProduct->refresh();
                $stockProduct->update(['stock_quantity' => $stockProduct->sellableRahkaranStock()]);
            }

            return true;
        });
    }

    public function releaseOrder(Order $order): void
    {
        $order->loadMissing('items.product');

        DB::transaction(function () use ($order): void {
            RahkaranSerial::query()
                ->whereIn('order_item_id', $order->items->pluck('id'))
                ->where('status', 'reserved')
                ->update(['status' => 'available', 'order_item_id' => null]);

            foreach ($order->items as $item) {
                $this->releaseUnconsumedStockReservation($item->id, $item->product?->base_product_id ?? $item->product_id);
            }

            $this->refreshProductQuantities($order);
        });
    }

    public function finalizeOrder(Order $order): void
    {
        $order->loadMissing('items.product');

        DB::transaction(function () use ($order): void {
            RahkaranSerial::query()
                ->whereIn('order_item_id', $order->items->pluck('id'))
                ->where('status', 'reserved')
                ->update(['status' => 'sold']);

            foreach ($order->items as $item) {
                $this->consumeStockReservation($item->id, $item->product?->base_product_id ?? $item->product_id);
            }

            $this->refreshProductQuantities($order);
        });
    }

    private function refreshProductQuantities(Order $order): void
    {
        $order->loadMissing('items.product');
        $productIds = $order->items
            ->map(fn ($item) => $item->product?->base_product_id ?? $item->product_id)
            ->filter()
            ->unique();

        foreach ($productIds as $productId) {
            $product = Product::query()->find($productId);
            if (! $product) {
                continue;
            }

            $product->stock_quantity = $product->rahkaran_serial_tracked
                ? min(
                    RahkaranSerial::query()->where('product_id', $productId)->where('status', 'available')->count(),
                    (int) ($product->rahkaran_physical_stock ?? 0),
                )
                : $product->sellableRahkaranStock();
            $product->save();
        }
    }

    private function releaseUnconsumedStockReservation(int $orderItemId, ?int $productId): void
    {
        $orderItem = OrderItem::query()->lockForUpdate()->find($orderItemId);
        $quantity = (int) ($orderItem?->rahkaran_stock_reserved_quantity ?? 0);
        if (! $orderItem || $quantity === 0 || $orderItem->rahkaran_stock_consumed || ! $productId) {
            return;
        }

        $product = Product::query()->lockForUpdate()->find($productId);
        if ($product) {
            $product->decrement('rahkaran_reserved_stock', min($quantity, (int) $product->rahkaran_reserved_stock));
            $product->refresh();
            $product->update(['stock_quantity' => $product->sellableRahkaranStock()]);
        }

        $orderItem->update(['rahkaran_stock_reserved_quantity' => 0]);
    }

    private function consumeStockReservation(int $orderItemId, ?int $productId): void
    {
        $orderItem = OrderItem::query()->lockForUpdate()->find($orderItemId);
        $quantity = (int) ($orderItem?->rahkaran_stock_reserved_quantity ?? 0);
        if (! $orderItem || $orderItem->rahkaran_stock_consumed || ! $productId || $quantity === 0) {
            return;
        }

        $product = Product::query()->lockForUpdate()->find($productId);
        if ($product) {
            $physicalStock = max(0, (int) $product->rahkaran_physical_stock - $quantity);
            $product->forceFill([
                'rahkaran_physical_stock' => $physicalStock,
                'rahkaran_reserved_stock' => max(0, (int) $product->rahkaran_reserved_stock - $quantity),
            ])->save();
            $product->refresh();
            $product->update(['stock_quantity' => $product->sellableRahkaranStock()]);
        }

        $orderItem->update([
            'rahkaran_stock_reserved_quantity' => 0,
            'rahkaran_stock_consumed' => true,
        ]);
    }
}
