<?php

namespace App\Services;

use App\Exceptions\RahkaranSerialConflictException;
use App\Models\HesabfaSyncLog;
use App\Models\RahkaranSerial;
use App\Support\InventorySource;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use RuntimeException;
use Throwable;

class RahkaranInventoryService
{
    public function __construct(
        private RahkaranService $rahkaran,
        private RahkaranSettings $settings,
    ) {}

    public function syncAllSerials(): array
    {
        return Cache::lock(InventorySource::SYNC_LOCK, 3600)->get(fn (): array => $this->syncInventory())
            ?: ['success' => false, 'message' => 'همگام‌سازی موجودی راهکاران در حال اجراست.', 'updated' => 0];
    }

    private function syncInventory(): array
    {
        if (InventorySource::isOffline()) {
            return ['success' => false, 'message' => 'منبع موجودی آفلاین است.', 'updated' => 0];
        }

        $products = Product::query()->published()->whereNotNull('sku')->get();
        $quantitySource = setting('rahkaran_stock_quantity_source', 'stock_api');
        $useStoredSerials = $quantitySource === 'stored_serials';
        $requiresApi = ! $useStoredSerials || $products->contains(
            fn (Product $product): bool => ! $product->rahkaran_serial_tracked && (int) $product->rahkaran_part_ref > 0,
        );
        if ($requiresApi && ! $this->rahkaran->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات راهکاران کامل نیست', 'updated' => 0];
        }

        if ($requiresApi && (! is_numeric($this->settings->get('inventory_id')) || ! is_numeric($this->settings->get('plant_id')))) {
            return ['success' => false, 'message' => 'شناسه انبار و مرکز نگهداری راهکاران باید تنظیم شوند', 'updated' => 0];
        }

        $updated = 0;
        $updatedProducts = 0;
        $errors = [];
        $errorDetails = [];

        $serialProductIds = $useStoredSerials ? [] : $products
            ->filter(fn (Product $product): bool => $product->rahkaran_serial_tracked && $product->rahkaran_part_ref)
            ->map(fn (Product $product): int => (int) $product->rahkaran_part_ref)->values()->all();
        $serialsByProduct = [];
        $batchError = null;

        if ($serialProductIds !== []) {
            try {
                $serialsByProduct = $this->rahkaran->getSerialsForParts($serialProductIds);
            } catch (Throwable $exception) {
                $batchError = $exception->getMessage();
                Log::warning('Rahkaran bulk serial lookup failed', ['message' => $batchError]);
            }
        }

        foreach ($products as $product) {
            $partRef = (int) $product->rahkaran_part_ref;
            if ($partRef <= 0) {
                $errors[$product->sku] = 'شناسهٔ کالای راهکاران تنظیم نشده است';
                $errorDetails[$product->sku] = ['product_id' => $product->id];

                continue;
            }

            try {
                if ($useStoredSerials && $product->rahkaran_serial_tracked) {
                    $this->updateStockFromStoredSerials($product);
                    $updatedProducts++;

                    continue;
                }

                $records = $product->rahkaran_serial_tracked ? ($serialsByProduct[$partRef] ?? null) : null;
                $serials = $records === null ? [] : array_column($records, 'Serial');
                $physicalStock = $quantitySource === 'live_serials' && $serials !== []
                    ? count($serials)
                    : $this->rahkaran->getStock($partRef, (int) $this->settings->get('inventory_id'), (int) $this->settings->get('plant_id'));

                if ($product->rahkaran_serial_tracked && $physicalStock > 0 && $serials === []) {
                    throw new RuntimeException($batchError ?? 'موجودی مثبت است اما سریال معتبر کالا در پاسخ راهکاران پیدا نشد.');
                }

                $this->applyInventorySnapshot($product, max(0, (int) floor($physicalStock)), $serials);
                $updated += count($serials);
                $updatedProducts++;
            } catch (Throwable $exception) {
                $errors[$product->sku] = $exception->getMessage();
                $errorDetails[$product->sku] = ['product_id' => $product->id];
                if ($exception instanceof RahkaranSerialConflictException) {
                    $errorDetails[$product->sku] += [
                        'serial' => $exception->serial,
                        'conflicting_product_id' => $exception->conflictingProduct->id,
                    ];
                }
                Log::error('Rahkaran serial sync failed', ['sku' => $product->sku, 'message' => $exception->getMessage()]);
            }
        }

        HesabfaSyncLog::query()->create([
            'sync_type' => 'rahkaran_stock_sync',
            'status' => $errors === [] ? 'success' : 'failed',
            'request_data' => ['mapped_products' => Product::query()->whereNotNull('rahkaran_part_ref')->count(), 'quantity_source' => $quantitySource],
            'response_data' => ['synced_serial_count' => $updated, 'updated_product_count' => $updatedProducts, 'errors' => $errors, 'error_details' => $errorDetails],
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

    public function refreshOfflineSerialStock(Product $product): void
    {
        $serials = RahkaranSerial::query()->where('product_id', $product->id);
        $physical = (clone $serials)->whereIn('status', ['available', 'reserved'])
            ->where('missing_from_warehouse', false)->count();
        $available = $serials->where('status', 'available')->forCurrentInventory()->count();
        $product->forceFill([
            'rahkaran_physical_stock' => $physical,
            'stock_quantity' => max(0, $available - (int) $product->hesabfa_manual_reserved),
        ])->save();
    }

    private function updateStockFromStoredSerials(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $counts = RahkaranSerial::query()
                ->where('product_id', $product->id)
                ->where(function ($query): void {
                    $query->where('status', 'available')
                        ->orWhere(fn ($reserved) => $reserved->where('status', 'reserved')->where('missing_from_warehouse', false));
                })
                ->selectRaw('status, COUNT(*) AS total')
                ->groupBy('status')
                ->pluck('total', 'status');
            $available = (int) ($counts['available'] ?? 0);
            $product->rahkaran_physical_stock = $available + (int) ($counts['reserved'] ?? 0);
            $product->stock_quantity = max(0, $available - (int) $product->hesabfa_manual_reserved);
            $product->save();
        }, attempts: 3);
    }

    /**
     * @param  list<string>  $serials
     */
    private function applyInventorySnapshot(Product $product, int $physicalStock, array $serials): void
    {
        DB::transaction(function () use ($product, $physicalStock, $serials): void {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $product->rahkaran_physical_stock = $physicalStock;

            if ($product->rahkaran_serial_tracked) {
                $present = $physicalStock > 0 ? $serials : [];
                $productSerials = RahkaranSerial::query()->where('product_id', $product->id);
                $absent = (clone $productSerials)->whereNotIn('serial', $present);
                (clone $absent)->where('status', 'available')->update(['status' => 'unavailable']);
                $absent->where('status', 'reserved')->update(['missing_from_warehouse' => true]);

                foreach ($present as $number) {
                    $serial = RahkaranSerial::query()->firstOrCreate(
                        ['serial' => $number],
                        ['product_id' => $product->id, 'status' => 'available'],
                    );

                    if ((int) $serial->product_id !== $product->id) {
                        $conflictingProduct = Product::query()->find($serial->product_id);
                        if ($conflictingProduct) {
                            throw new RahkaranSerialConflictException($number, $conflictingProduct);
                        }

                        throw new RuntimeException('سریال راهکاران قبلاً برای کالای دیگری ثبت شده است.');
                    }

                    $serial->synced_at = now();
                    $serial->missing_from_warehouse = false;
                    if ($serial->status === 'unavailable') {
                        $serial->status = 'available';
                    }
                    $serial->save();
                }

                $product->stock_quantity = max(0, min(
                    $productSerials->where('status', 'available')->count(),
                    $physicalStock,
                ) - (int) $product->hesabfa_manual_reserved);
            } else {
                $product->stock_quantity = $product->sellableRahkaranStock($physicalStock);
            }

            $product->save();
        }, attempts: 3);
    }

    public function reserveForOrder(Order $order): bool
    {
        $order->loadMissing('items.product');

        return DB::transaction(function () use ($order): bool {
            foreach ($order->items->sortBy(fn ($item) => [$item->product?->base_product_id ?? $item->product_id, $item->id]) as $item) {
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
                    $existing = RahkaranSerial::query()->where('order_item_id', $item->id)->lockForUpdate()->get(['id'])->count();
                    $needed = max(0, $required - $existing);
                    if ($needed === 0) {
                        continue;
                    }

                    $availableSerialCount = RahkaranSerial::query()
                        ->where('product_id', $stockProduct->id)
                        ->where('status', 'available')
                        ->forCurrentInventory()
                        ->lockForUpdate()->get(['id'])->count();
                    $reservedSerialCount = RahkaranSerial::query()
                        ->where('product_id', $stockProduct->id)
                        ->where('status', 'reserved')
                        ->when(setting('rahkaran_stock_quantity_source', 'stock_api') === 'stored_serials', fn ($query) => $query->where('missing_from_warehouse', false))
                        ->lockForUpdate()->get(['id'])->count();
                    $remainingPhysicalStock = max(
                        0,
                        (int) ($stockProduct->rahkaran_physical_stock ?? 0)
                            - $reservedSerialCount
                            - (int) $stockProduct->hesabfa_manual_reserved,
                    );
                    $sellable = InventorySource::isOffline()
                        ? max(0, $availableSerialCount - (int) $stockProduct->hesabfa_manual_reserved)
                        : min($availableSerialCount, $remainingPhysicalStock);
                    if ($needed > $sellable) {
                        throw new DomainException("موجودی سریالی محصول {$item->product->sku} کافی نیست.");
                    }

                    $serials = RahkaranSerial::query()
                        ->where('product_id', $stockProduct->id)
                        ->where('status', 'available')
                        ->forCurrentInventory()
                        ->orderBy('id')
                        ->limit($needed)
                        ->lockForUpdate()
                        ->get();

                    if ($serials->count() < $needed) {
                        throw new DomainException("سریال کافی برای محصول {$item->product->sku} موجود نیست.");
                    }

                    foreach ($serials as $serial) {
                        $serial->update(['status' => 'reserved', 'order_item_id' => $item->id]);
                    }

                    $stockProduct->stock_quantity = max(0, min(
                        $availableSerialCount - $needed,
                        InventorySource::isOffline() ? $availableSerialCount : (int) ($stockProduct->rahkaran_physical_stock ?? 0),
                    ) - (int) $stockProduct->hesabfa_manual_reserved);
                    $stockProduct->save();

                    continue;
                }

                if (! InventorySource::usesRahkaranInventory()
                    || $orderItem->rahkaran_stock_consumed
                    || $orderItem->rahkaran_stock_reserved_quantity > 0) {
                    continue;
                }

                $required = (int) $item->quantity * $item->product->pack_size;
                if ($required > $stockProduct->sellableRahkaranStock()) {
                    throw new DomainException("موجودی کالای {$item->product->sku} کافی نیست.");
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
            $releasedSerials = RahkaranSerial::query()
                ->whereIn('order_item_id', $order->items->pluck('id'))
                ->where('status', 'reserved')
                ->update([
                    'status' => DB::raw("CASE WHEN missing_from_warehouse THEN 'unavailable' ELSE 'available' END"),
                    'order_item_id' => null,
                ]);

            if ($releasedSerials === 0 && ! $order->items->contains(fn ($item) => $item->rahkaran_stock_reserved_quantity > 0)) {
                return;
            }

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

            if ($product->rahkaran_serial_tracked && InventorySource::isOffline()) {
                $this->refreshOfflineSerialStock($product);

                continue;
            }

            if ($product->rahkaran_serial_tracked && setting('rahkaran_stock_quantity_source', 'stock_api') === 'stored_serials') {
                $this->updateStockFromStoredSerials($product);

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
