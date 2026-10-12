<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SerialEventType;
use App\Enums\Inventory\SerialFulfillmentStatus;
use App\Enums\Inventory\SerialStatus;
use App\Enums\Inventory\StockReservationStatus;
use App\Models\ProductSerial;
use App\Models\ProductStockReservation;
use App\Models\SerialEvent;
use App\Support\Platform;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;

/**
 * Sole owner of serial-warehouse mutations (plan §5.2).
 *
 * Lock order inside every transaction: order → its payments by id →
 * products by id → serials by id. Items and ownership are always re-read
 * after locking; idempotency is derived from real DB state, and each event
 * carries a fresh operation_key (retries never reuse a key).
 *
 * Tapsi orders never touch site serials. Orders created before the rollout
 * (serial_inventory_enabled=false) own no serial state, so release paths
 * skip them; checkout/payment init reject new purchases until rollout is ready.
 */
final class SerialInventoryService
{
    public function reserveForPayment(int $orderId, string $platform, ?int $paymentId = null): void
    {
        if ($platform === Platform::TAPSI) {
            return;
        }

        $this->transact(function () use ($orderId, $platform, $paymentId): void {
            $order = $this->lockedOrder($orderId, $platform);

            if (! $order->serial_inventory_enabled) {
                return;
            }

            $items = $this->lockedItems($order);
            $this->assertManualPayment($items, $paymentId);

            $plan = $this->planReservation($order, $items);

            $this->applySerialReservations($order, $plan);
            $this->applyManualReservations($order, $plan, $paymentId);

            $this->markItems($items, SerialFulfillmentStatus::Reserved);
            $order->update(['serial_fulfillment_status' => SerialFulfillmentStatus::Reserved->value]);
        });
    }

    public function finalizePaidOrder(int $orderId, string $platform): void
    {
        if ($platform === Platform::TAPSI) {
            return;
        }

        $this->transact(function () use ($orderId, $platform): void {
            $order = $this->lockedOrder($orderId, $platform);

            if (! $order->serial_inventory_enabled) {
                return;
            }

            if ($order->status === 'cancelled' || in_array($order->serial_fulfillment_status, [SerialFulfillmentStatus::Shipped, SerialFulfillmentStatus::Returned], true)) {
                return;
            }

            if ($order->payment_status !== 'paid') {
                throw new LogicException('نهایی‌سازی فقط برای سفارش پرداخت‌شده انجام می‌شود.');
            }

            if ($order->payments()->withoutTenantScope()->where('platform', $order->platform)->whereIn('status', ['initiating', 'processing'])->exists()) {
                $order->update(['serial_fulfillment_status' => SerialFulfillmentStatus::NeedsReview->value]);

                return;
            }

            $items = $this->lockedItems($order);
            $this->lockProducts($items);

            if ($items->isNotEmpty() && $items->every(fn (OrderItem $item): bool => count($this->historicalShippedUnits($item)) >= (int) $item->quantity)) {
                $hasReturn = SerialEvent::where('order_id', $order->id)->where('event_type', SerialEventType::Returned->value)->exists();
                $order->update(['serial_fulfillment_status' => $hasReturn ? SerialFulfillmentStatus::Returned->value : SerialFulfillmentStatus::Shipped->value]);

                return;
            }

            $shortage = $this->applySerialFinalization($order, $items);
            $manualShortage = $this->applyManualFinalization($order, $items);

            if ($shortage || $manualShortage) {
                $order->update(['serial_fulfillment_status' => SerialFulfillmentStatus::NeedsReview->value]);
                Log::warning('Serial finalize shortage, order kept paid for review.', ['order_id' => $order->id]);

                return;
            }

            if ($this->hasMadeToOrder($items)) {
                $order->update(['serial_fulfillment_status' => SerialFulfillmentStatus::AwaitingProduction->value]);

                return;
            }

            if ($this->hasSerialItems($items)) {
                $this->refreshFulfillmentStatus($order->fresh());

                return;
            }

            $order->update(['serial_fulfillment_status' => SerialFulfillmentStatus::Allocated->value]);
        });
    }

    public function releaseExpiredReservation(int $orderId, string $platform): void
    {
        if ($platform === Platform::TAPSI) {
            return;
        }

        $probe = Order::withoutTenantScope()->where('id', $orderId)->where('platform', $platform)->first();

        if ($probe !== null && $probe->payment_status === 'paid') {
            $this->finalizePaidOrder($orderId, $platform);

            return;
        }

        $now = now();

        $this->transact(function () use ($orderId, $platform, $now): void {
            $order = $this->lockedOrder($orderId, $platform);

            if ($order->payment_status === 'paid') {
                $this->finalizePaidOrder($orderId, $platform);

                return;
            }

            if ($order->payments()->withoutTenantScope()->where('platform', $order->platform)->whereIn('status', ['paid', 'initiating', 'processing'])->exists()) {
                $order->update(['serial_fulfillment_status' => SerialFulfillmentStatus::NeedsReview->value]);

                return;
            }

            $items = $this->lockedItems($order);
            $this->lockProducts($items);
            $itemIds = $items->pluck('id')->all();

            $expired = ProductSerial::whereIn('current_order_item_id', $itemIds)
                ->where('status', SerialStatus::Reserved->value)
                ->where('reserved_until', '<', $now)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($expired as $serial) {
                $this->freeSerial($serial, SerialStatus::Available, SerialEventType::ReservationReleased, $order, null, 'انقضای رزرو');
            }

            ProductStockReservation::whereIn('order_item_id', $itemIds)
                ->where('status', StockReservationStatus::Reserved->value)
                ->where('reserved_until', '<', $now)
                ->update(['status' => StockReservationStatus::Released->value, 'updated_at' => $now]);

            if ($this->hasSerialItems($items)) {
                $this->refreshFulfillmentStatus($order->fresh());

                return;
            }

            $hasActive = ProductStockReservation::whereIn('order_item_id', $itemIds)
                ->whereIn('status', [StockReservationStatus::Reserved->value, StockReservationStatus::Consumed->value])
                ->exists();

            $order->update(['serial_fulfillment_status' => $hasActive
                ? SerialFulfillmentStatus::Reserved->value
                : SerialFulfillmentStatus::NotRequired->value]);
        });
    }

    /**
     * Free everything an unpaid order still holds: all owned reserved
     * serials plus (optionally one cycle's) manual reservations.
     *
     * Paid and financially ambiguous attempts keep their holdings until
     * the gateway outcome is known.
     */
    public function releaseUnpaidHoldings(int $orderId, string $platform, ?int $paymentId = null, ?int $actorId = null, ?string $reason = null): void
    {
        if ($platform === Platform::TAPSI) {
            return;
        }

        $this->transact(function () use ($orderId, $platform, $paymentId, $actorId, $reason): void {
            $order = $this->lockedOrder($orderId, $platform);
            if ($order->payment_status === 'paid' || $order->payments()->withoutTenantScope()->where('platform', $order->platform)->whereIn('status', ['paid', 'processing', 'initiating'])->exists()) {
                return;
            }

            $items = $this->lockedItems($order);
            $this->lockProducts($items);

            $owned = ProductSerial::whereIn('current_order_item_id', $items->pluck('id')->all())
                ->where('status', SerialStatus::Reserved->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($owned as $serial) {
                $this->freeSerial($serial, SerialStatus::Available, SerialEventType::ReservationReleased, $order, $actorId, $reason ?? 'آزادسازی holdings سفارش پرداخت‌نشده');
            }

            $manualQuery = ProductStockReservation::whereIn('order_item_id', $items->pluck('id')->all())
                ->where('status', StockReservationStatus::Reserved->value);

            if ($paymentId !== null) {
                $manualQuery->where('payment_id', $paymentId);
            }

            $manualQuery->update(['status' => StockReservationStatus::Released->value, 'updated_at' => now()]);

            if ($this->hasSerialItems($items)) {
                $this->refreshFulfillmentStatus($order->fresh());
            }
        });
    }

    /**
     * Manual capacity must be consumed even without serial tracking.
     * Terminal shipment/return history and paid production waits are complete
     * for recovery purposes.
     */
    public function isSeriallyComplete(Order $order): bool
    {
        if (in_array($order->serial_fulfillment_status, [SerialFulfillmentStatus::Shipped, SerialFulfillmentStatus::Returned], true)) {
            return true;
        }

        $items = $order->items()->with('product')->get();

        foreach ($items as $item) {
            if (count($this->historicalShippedUnits($item)) >= (int) $item->quantity) {
                continue;
            }

            if ($this->itemMode($item) === InventoryMode::Manual && ! ProductStockReservation::where('order_item_id', $item->id)->where('status', StockReservationStatus::Consumed->value)->exists()) {
                return false;
            }

            if ($this->isAwaitingProduction($item)) {
                continue;
            }

            if ($this->requiresSerial($item) && count($this->fulfilledUnits($item)) !== (int) $item->quantity) {
                return false;
            }
        }

        return true;
    }

    public function completeProduction(int $orderId, string $platform, int $actorId, string $reason): void
    {
        $this->transact(function () use ($orderId, $platform, $actorId, $reason): void {
            $order = $this->lockedOrder($orderId, $platform);

            if (in_array($order->serial_fulfillment_status, [SerialFulfillmentStatus::Shipped, SerialFulfillmentStatus::Returned], true)) {
                return;
            }

            if (! $order->serial_inventory_enabled || $order->payment_status !== 'paid' || in_array($order->status, ['cancelled', 'expired'], true)) {
                throw new LogicException('تولید فقط برای سفارش فعال پرداخت‌شده تکمیل می‌شود.');
            }

            $items = $this->lockedItems($order);
            $this->lockProducts($items);

            if (! $this->hasMadeToOrder($items)) {
                return;
            }

            if ($order->payments()->withoutTenantScope()->where('platform', $order->platform)->whereIn('status', ['initiating', 'processing'])->exists()) {
                throw new LogicException('نتیجه پرداخت هنوز مشخص نیست.');
            }

            foreach ($items as $item) {
                if ($this->itemMode($item) === InventoryMode::Manual && ! ProductStockReservation::where('order_item_id', $item->id)->where('status', StockReservationStatus::Consumed->value)->exists()) {
                    throw new LogicException('ظرفیت سفارش هنوز نهایی نشده است.');
                }
            }

            foreach ($items as $item) {
                if ($this->isAwaitingProduction($item)) {
                    if (! ProductStockReservation::where('order_item_id', $item->id)->where('status', StockReservationStatus::Consumed->value)->exists()) {
                        throw new LogicException('ظرفیت سفارش هنوز نهایی نشده است.');
                    }

                    $item->update(['production_completed_at' => now()]);
                }
            }

            if ($this->applySerialFinalization($order, $items)) {
                throw new LogicException('سریال تولیدشده برای تکمیل همه واحدها کافی نیست.');
            }

            $this->markItems($items, SerialFulfillmentStatus::Allocated);
            $order->update(['serial_fulfillment_status' => SerialFulfillmentStatus::Allocated->value]);
            $order->addNote($reason.' | actor_id: '.$actorId, 'inventory');
        });
    }

    public function cancelUnpaidOrder(int $orderId, string $platform, string $reason, bool $expiredOnly = false): bool
    {
        $cancelled = false;

        $this->transact(function () use ($orderId, $platform, $reason, $expiredOnly, &$cancelled): void {
            $order = $this->lockedOrder($orderId, $platform);

            if (! in_array($order->payment_status, ['pending', 'failed'], true) || $order->status !== 'pending' || $order->payments()->withoutTenantScope()->where('platform', $order->platform)->whereIn('status', ['paid', 'processing', 'initiating'])->exists()) {
                return;
            }

            if ($expiredOnly) {
                $itemIds = $order->items()->pluck('id');
                $freshSerial = ProductSerial::whereIn('current_order_item_id', $itemIds)->where('status', 'reserved')->where('reserved_until', '>=', now())->exists();
                $freshManual = ProductStockReservation::whereIn('order_item_id', $itemIds)->where('status', 'reserved')->where('reserved_until', '>=', now())->exists();

                if ($order->created_at->gte(now()->subMinutes(20)) || $freshSerial || $freshManual) {
                    return;
                }
            }

            $order->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
            $this->releaseUnpaidHoldings($orderId, $platform, null, null, $reason);
            $cancelled = true;
        });

        return $cancelled;
    }

    public function releaseBeforeShipment(int $orderId, string $platform, int $actorId, string $reason): void
    {
        if ($platform === Platform::TAPSI) {
            return;
        }

        $this->transact(function () use ($orderId, $platform, $actorId, $reason): void {
            $order = $this->lockedOrder($orderId, $platform);
            $items = $this->lockedItems($order);
            $this->lockProducts($items);

            $owned = ProductSerial::whereIn('current_order_item_id', $items->pluck('id')->all())
                ->whereIn('status', [SerialStatus::Reserved->value, SerialStatus::Allocated->value])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($owned as $serial) {
                $event = $serial->status === SerialStatus::Allocated
                    ? SerialEventType::AllocationReleased
                    : SerialEventType::ReservationReleased;

                $this->freeSerial($serial, SerialStatus::Available, $event, $order, $actorId, $reason);
            }

            // Manual capacity is intentionally NOT reopened here; reopening
            // capacity is a separate audited manual action.
            if ($this->hasSerialItems($items)) {
                $this->refreshFulfillmentStatus($order->fresh());
            }
        });
    }

    public function markShipped(int $orderId, string $platform, int $actorId): void
    {
        if ($platform === Platform::TAPSI) {
            return;
        }

        $this->transact(function () use ($orderId, $platform, $actorId): void {
            $order = $this->lockedOrder($orderId, $platform);

            if ($order->serial_fulfillment_status === SerialFulfillmentStatus::Shipped) {
                return;
            }

            if (! $order->serial_inventory_enabled || $order->payment_status !== 'paid' || in_array($order->status, ['cancelled', 'expired'], true) || in_array($order->serial_fulfillment_status, [SerialFulfillmentStatus::NeedsReview, SerialFulfillmentStatus::AwaitingProduction], true) || ! $this->isSeriallyComplete($order)) {
                throw new LogicException('سفارش پرداخت‌شده و تکمیل‌شده برای ارسال لازم است.');
            }

            $items = $this->lockedItems($order);
            $this->lockProducts($items);

            if ($this->hasMadeToOrder($items)) {
                throw new LogicException('تکمیل تولید برای ارسال لازم است.');
            }

            foreach ($items as $item) {
                if (! $this->requiresSerial($item)) {
                    continue;
                }

                $allocated = ProductSerial::where('current_order_item_id', $item->id)
                    ->where('status', SerialStatus::Allocated->value)
                    ->count();

                if ($allocated !== (int) $item->quantity) {
                    throw new LogicException('ارسال ناقص ممنوع است؛ همه واحدها باید سریال تخصیص‌یافته داشته باشند.');
                }
            }

            $shippable = ProductSerial::whereIn('current_order_item_id', $items->pluck('id')->all())
                ->where('status', SerialStatus::Allocated->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($shippable as $serial) {
                $item = $items->firstWhere('id', $serial->current_order_item_id);

                $serial->update(['status' => SerialStatus::Shipped->value, 'shipped_at' => now()]);

                $this->recordEvent($serial, SerialEventType::Shipped, SerialStatus::Allocated, SerialStatus::Shipped, $order, $item, $serial->unit_number, $actorId, 'خروج فیزیکی');
            }

            $this->markItems($items, SerialFulfillmentStatus::Shipped);
            $order->update(['serial_fulfillment_status' => SerialFulfillmentStatus::Shipped->value]);
        });
    }

    public function receiveReturn(int $serialId, int $actorId, string $reason): void
    {
        $this->transact(function () use ($serialId, $actorId, $reason): void {
            $serial = $this->lockedLifecycleSerial($serialId);

            if ($serial->status !== SerialStatus::Shipped) {
                throw new LogicException('فقط سریال خارج‌شده قابل دریافت مرجوعی است.');
            }

            $serial->update(['status' => SerialStatus::Returned->value]);

            $item = $serial->current_order_item_id !== null ? OrderItem::find($serial->current_order_item_id) : null;
            $order = $item !== null ? Order::withoutTenantScope()->find($item->order_id) : null;

            $this->recordEvent($serial, SerialEventType::Returned, SerialStatus::Shipped, SerialStatus::Returned, $order, $item, $serial->unit_number, $actorId, $reason);

            if ($order !== null) {
                $order->update(['serial_fulfillment_status' => SerialFulfillmentStatus::Returned->value]);
            }
        });
    }

    public function restockReturnedSerial(int $serialId, int $actorId, string $reason): void
    {
        $this->transact(function () use ($serialId, $actorId, $reason): void {
            $serial = $this->lockedLifecycleSerial($serialId);

            if ($serial->status !== SerialStatus::Returned) {
                throw new LogicException('فقط سریال مرجوعیِ تأییدشده (QC) به موجودی برمی‌گردد.');
            }

            $item = $serial->current_order_item_id !== null ? OrderItem::find($serial->current_order_item_id) : null;
            $order = $item !== null ? Order::withoutTenantScope()->find($item->order_id) : null;

            $serial->update([
                'status' => SerialStatus::Available->value,
                'current_order_item_id' => null,
                'unit_number' => null,
                'reserved_until' => null,
            ]);

            $this->recordEvent($serial, SerialEventType::Restocked, SerialStatus::Returned, SerialStatus::Available, $order, $item, null, $actorId, $reason);

            if ($order !== null) {
                $this->refreshFulfillmentStatus($order);
            }
        });
    }

    // ------------------------------------------------------------------
    // Internals.
    // ------------------------------------------------------------------

    private function lockedLifecycleSerial(int $serialId): ProductSerial
    {
        $probe = ProductSerial::findOrFail($serialId);
        $item = $probe->current_order_item_id !== null ? OrderItem::findOrFail($probe->current_order_item_id) : null;

        if ($item !== null) {
            $order = Order::withoutTenantScope()->findOrFail($item->order_id);
            $this->lockedOrder($order->id, $order->platform);
        }

        Product::whereKey($probe->product_id)->lockForUpdate()->firstOrFail();
        $serial = ProductSerial::lockForUpdate()->findOrFail($serialId);

        if ($serial->current_order_item_id !== $probe->current_order_item_id || $serial->product_id !== $probe->product_id) {
            throw new LogicException('مالک سریال تغییر کرده است؛ عملیات را دوباره انجام دهید.');
        }

        return $serial;
    }

    private function lockedOrder(int $orderId, string $platform): Order
    {
        $order = Order::withoutTenantScope()
            ->where('id', $orderId)
            ->where('platform', $platform)
            ->lockForUpdate()
            ->firstOrFail();

        // Payments of the same order, locked in stable id order.
        $order->payments()->withoutTenantScope()->where('platform', $order->platform)->orderBy('id')->lockForUpdate()->get();

        return $order;
    }

    /** @return EloquentCollection<int, OrderItem> */
    private function lockedItems(Order $order): EloquentCollection
    {
        // Fresh read after the order lock; never trust a stale relation.
        return $order->items()->with('product')->orderBy('id')->lockForUpdate()->get();
    }

    /** @param  EloquentCollection<int, OrderItem>  $items */
    private function lockProducts(EloquentCollection $items): void
    {
        $productIds = $items->map(fn (OrderItem $item): ?int => $item->product_id)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($productIds !== []) {
            $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($items as $item) {
                $item->setRelation('product', $products->get($item->product_id));
            }
        }
    }

    /** @param  EloquentCollection<int, OrderItem>  $items */
    private function assertManualPayment(EloquentCollection $items, ?int $paymentId): void
    {
        $needsPayment = $items->contains(fn (OrderItem $item): bool => $this->itemMode($item) === InventoryMode::Manual);

        if ($needsPayment && $paymentId === null) {
            throw new InvalidArgumentException('رزرو ظرفیت دستی نیازمند شناسه پرداخت است.');
        }
    }

    private function itemMode(OrderItem $item): InventoryMode
    {
        return $item->inventory_mode ?? $item->product?->inventory_mode ?? InventoryMode::Manual;
    }

    private function requiresSerial(OrderItem $item): bool
    {
        return $this->itemMode($item) === InventoryMode::Serial
            || ($item->serial_tracking_required ?? $item->product?->track_serials ?? false);
    }

    /** @param  EloquentCollection<int, OrderItem>  $items */
    private function hasSerialItems(EloquentCollection $items): bool
    {
        return $items->contains(fn (OrderItem $item): bool => $this->requiresSerial($item));
    }

    /**
     * Validate the whole order and pre-lock every free serial before writing
     * anything (all-or-nothing, race-safe).
     *
     * @param  EloquentCollection<int, OrderItem>  $items
     * @return array{serial: array<int, array{item: OrderItem, need: int}>, manual: array<int, OrderItem>, locked: array<int, EloquentCollection<int, ProductSerial>>}
     */
    private function planReservation(Order $order, EloquentCollection $items): array
    {
        $serialPlan = [];
        $manualPlan = [];

        foreach ($items as $item) {
            if ($this->itemMode($item) === InventoryMode::Serial) {
                if ($item->product === null || $item->product_id === null) {
                    throw new LogicException('محصول آیتم سفارش یافت نشد.');
                }

                $owned = ProductSerial::where('current_order_item_id', $item->id)
                    ->whereIn('status', [SerialStatus::Reserved->value, SerialStatus::Allocated->value])
                    ->count();

                $need = (int) $item->quantity - $owned;

                if ($need > 0) {
                    $serialPlan[] = ['item' => $item, 'need' => $need];
                }

                continue;
            }

            if ($item->product === null || $item->product_id === null) {
                throw new LogicException('محصول آیتم سفارش یافت نشد.');
            }

            $manualPlan[] = $item;
        }

        $this->lockProducts($items);

        // Pre-lock free serials per product and verify counts while holding
        // the locks; a shortfall throws before a single row is written.
        $needsByProduct = [];

        foreach ($serialPlan as $entry) {
            $productId = $entry['item']->product_id;
            $needsByProduct[$productId] = ($needsByProduct[$productId] ?? 0) + $entry['need'];
        }

        $candidates = $this->lockSerialCandidates($needsByProduct);
        $locked = $candidates['free'];

        foreach ($needsByProduct as $productId => $totalNeed) {
            $available = ($locked[$productId] ?? new EloquentCollection)->count();

            if ($available < $totalNeed) {
                throw new SerialStockShortageException($productId, $totalNeed, $available);
            }
        }

        return ['serial' => $serialPlan, 'manual' => $manualPlan, 'locked' => $locked];
    }

    /**
     * Current locking reads avoid stale repeatable-read candidates. Keyset
     * batches keep lock acquisition ascending across products; satisfied
     * products leave the available branch before the next batch.
     *
     * @param  array<int, int>  $needsByProduct
     * @param  list<int>  $ownedIds
     * @return array{free: array<int, EloquentCollection<int, ProductSerial>>, owned: EloquentCollection<int, ProductSerial>}
     */
    private function lockSerialCandidates(array $needsByProduct, array $ownedIds = []): array
    {
        $remaining = array_filter($needsByProduct, fn (int $need): bool => $need > 0);
        $remainingOwned = array_fill_keys($ownedIds, true);
        $free = [];
        $owned = new EloquentCollection;
        $lastId = 0;

        while ($remaining !== [] || $remainingOwned !== []) {
            $rows = ProductSerial::where('id', '>', $lastId)
                ->where(function (Builder $query) use ($remaining, $remainingOwned): void {
                    if ($remaining !== []) {
                        $query->where(function (Builder $query) use ($remaining): void {
                            $query->where('status', SerialStatus::Available->value)->whereIn('product_id', array_keys($remaining));
                        });
                    }

                    if ($remainingOwned !== []) {
                        $query->orWhereIn('id', array_keys($remainingOwned));
                    }
                })
                ->orderBy('id')
                ->limit(min(100, array_sum($remaining) + count($remainingOwned)))
                ->lockForUpdate()
                ->get(['id', 'product_id', 'status', 'current_order_item_id', 'unit_number']);

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $serial) {
                $lastId = $serial->id;

                if (isset($remainingOwned[$serial->id])) {
                    $owned->push($serial);
                    unset($remainingOwned[$serial->id]);

                    continue;
                }

                if ($serial->status === SerialStatus::Available && isset($remaining[$serial->product_id])) {
                    $free[$serial->product_id] ??= new EloquentCollection;
                    $free[$serial->product_id]->push($serial);
                    $remaining[$serial->product_id]--;

                    if ($remaining[$serial->product_id] === 0) {
                        unset($remaining[$serial->product_id]);
                    }
                }
            }
        }

        return ['free' => $free, 'owned' => $owned];
    }

    /** @param  array{serial: array<int, array{item: OrderItem, need: int}>, manual: array<int, OrderItem>, locked: array<int, EloquentCollection<int, ProductSerial>>}  $plan */
    private function applySerialReservations(Order $order, array $plan): void
    {
        $reservedUntil = now()->addMinutes((int) config('serial_inventory.reserve_minutes', 20));

        foreach ($plan['serial'] as $entry) {
            /** @var OrderItem $item */
            $item = $entry['item'];

            $usedUnits = ProductSerial::where('current_order_item_id', $item->id)
                ->whereIn('status', [SerialStatus::Reserved->value, SerialStatus::Allocated->value])
                ->pluck('unit_number')
                ->all();

            for ($n = 0; $n < $entry['need']; $n++) {
                $serial = $plan['locked'][$item->product_id]->shift();
                $unit = $this->nextFreeUnit($usedUnits, (int) $item->quantity);
                $usedUnits[] = $unit;

                $serial->update([
                    'status' => SerialStatus::Reserved->value,
                    'current_order_item_id' => $item->id,
                    'unit_number' => $unit,
                    'reserved_until' => $reservedUntil,
                    'allocated_at' => null,
                ]);

                $this->recordEvent($serial, SerialEventType::Reserved, null, SerialStatus::Reserved, $order, $item, $unit, null, null);
            }
        }
    }

    /**
     * @param  array{serial: array<int, array{item: OrderItem, need: int}>, manual: array<int, OrderItem>, locked: array<int, EloquentCollection<int, ProductSerial>>}  $plan
     */
    private function applyManualReservations(Order $order, array $plan, ?int $paymentId): void
    {
        if ($plan['manual'] === []) {
            return;
        }

        $reservedUntil = now()->addMinutes((int) config('serial_inventory.reserve_minutes', 20));

        foreach ($plan['manual'] as $item) {
            $exists = ProductStockReservation::where('payment_id', $paymentId)
                ->where('order_item_id', $item->id)
                ->whereIn('status', [StockReservationStatus::Reserved->value, StockReservationStatus::Consumed->value])
                ->exists();

            if ($exists) {
                continue;
            }

            $activeOthers = ProductStockReservation::where('product_id', $item->product_id)
                ->where('status', StockReservationStatus::Reserved->value)
                ->orderBy('id')->lockForUpdate()->get()->sum('quantity');

            $acceptable = max(0, (int) $item->product->stock_quantity - (int) $activeOthers);

            if ((int) $item->quantity > $acceptable) {
                throw new SerialStockShortageException($item->product_id, (int) $item->quantity, $acceptable);
            }

            ProductStockReservation::updateOrCreate([
                'order_item_id' => $item->id,
                'payment_id' => $paymentId,
            ], [
                'product_id' => $item->product_id,
                'quantity' => (int) $item->quantity,
                'status' => StockReservationStatus::Reserved->value,
                'reserved_until' => $reservedUntil,
            ]);
        }
    }

    /**
     * Convert owned reservations to allocations and fill released gaps from
     * free stock. Returns true when the serial group cannot be completed
     * (nothing is converted in that case).
     *
     * @param  EloquentCollection<int, OrderItem>  $items
     */
    private function applySerialFinalization(Order $order, EloquentCollection $items): bool
    {
        $serialItems = $items->filter(fn (OrderItem $item): bool => $this->requiresSerial($item) && ! $this->isAwaitingProduction($item) && count($this->historicalShippedUnits($item)) < (int) $item->quantity);
        $ownedByItem = [];
        $fillByItem = [];
        $deficits = [];
        $needsByProduct = [];

        $ownedIdsByItem = [];

        foreach ($serialItems as $item) {
            $allocated = count($this->fulfilledUnits($item));
            $ownedIdsByItem[$item->id] = ProductSerial::where('current_order_item_id', $item->id)->where('status', SerialStatus::Reserved->value)->toBase()->pluck('id')->all();
            $deficit = max(0, (int) $item->quantity - $allocated - count($ownedIdsByItem[$item->id]));
            $deficits[$item->id] = $deficit;
            $needsByProduct[$item->product_id] = ($needsByProduct[$item->product_id] ?? 0) + $deficit;
        }

        $ownedIds = array_merge([], ...array_values($ownedIdsByItem));
        $candidates = $this->lockSerialCandidates($needsByProduct, $ownedIds);
        $pools = $candidates['free'];

        if ($candidates['owned']->count() !== count($ownedIds)) {
            return true;
        }

        foreach ($serialItems as $item) {
            $ownedByItem[$item->id] = new EloquentCollection($candidates['owned']->where('current_order_item_id', $item->id)->all());
        }

        foreach ($needsByProduct as $productId => $need) {
            if (($pools[$productId] ?? new EloquentCollection)->count() < $need) {
                return true;
            }
        }

        foreach ($serialItems as $item) {
            if ($deficits[$item->id] > 0) {
                $fillByItem[$item->id] = $pools[$item->product_id]->splice(0, $deficits[$item->id]);
            }
        }

        // Phase 2: everything validated — write.
        foreach ($serialItems as $item) {
            foreach ($ownedByItem[$item->id] as $serial) {
                $serial->update([
                    'status' => SerialStatus::Allocated->value,
                    'reserved_until' => null,
                    'allocated_at' => now(),
                ]);

                $this->recordEvent($serial, SerialEventType::Allocated, SerialStatus::Reserved, SerialStatus::Allocated, $order, $item, $serial->unit_number, null, null);
            }

            if (! isset($fillByItem[$item->id])) {
                $item->update(['serial_fulfillment_status' => SerialFulfillmentStatus::Allocated->value]);

                continue;
            }

            $usedUnits = $this->fulfilledUnits($item);

            foreach ($fillByItem[$item->id] as $serial) {
                $unit = $this->nextFreeUnit($usedUnits, (int) $item->quantity);
                $usedUnits[] = $unit;

                $serial->update([
                    'status' => SerialStatus::Allocated->value,
                    'current_order_item_id' => $item->id,
                    'unit_number' => $unit,
                    'reserved_until' => null,
                    'allocated_at' => now(),
                ]);

                $this->recordEvent($serial, SerialEventType::Allocated, null, SerialStatus::Allocated, $order, $item, $unit, null, 'جایگزینی از موجودی آزاد');
            }

            $item->update(['serial_fulfillment_status' => SerialFulfillmentStatus::Allocated->value]);
        }

        return false;
    }

    /**
     * Consume manual capacity exactly once, including released late attempts.
     * Insufficient remaining capacity preserves paid truth and flags review.
     *
     * @param  EloquentCollection<int, OrderItem>  $items
     */
    private function applyManualFinalization(Order $order, EloquentCollection $items): bool
    {
        $shortage = false;

        foreach ($items as $item) {
            if ($this->itemMode($item) !== InventoryMode::Manual || count($this->historicalShippedUnits($item)) >= (int) $item->quantity) {
                continue;
            }

            $consumed = ProductStockReservation::where('order_item_id', $item->id)->where('status', StockReservationStatus::Consumed->value)->exists();

            if (! $consumed) {
                $reservations = ProductStockReservation::where('order_item_id', $item->id)->orderBy('id')->lockForUpdate()->get();
                $own = $reservations->where('status', StockReservationStatus::Reserved)->sum('quantity');
                $active = ProductStockReservation::where('product_id', $item->product_id)->where('status', StockReservationStatus::Reserved->value)->orderBy('id')->lockForUpdate()->get()->sum('quantity');
                $available = max(0, (int) $item->product?->stock_quantity - (int) $active + (int) $own);

                if ($item->product === null || $available < (int) $item->quantity) {
                    $item->update(['serial_fulfillment_status' => SerialFulfillmentStatus::NeedsReview->value]);
                    $shortage = true;

                    continue;
                }

                $payment = $order->payments()->withoutTenantScope()->where('platform', $order->platform)->where('status', 'paid')->latest('id')->first() ?? $order->payments()->withoutTenantScope()->where('platform', $order->platform)->latest('id')->first();

                if ($payment === null) {
                    $shortage = true;

                    continue;
                }

                $reservation = $reservations->last();

                if ($reservation === null) {
                    $reservation = ProductStockReservation::create(['order_item_id' => $item->id, 'product_id' => $item->product_id, 'payment_id' => $payment->id, 'quantity' => (int) $item->quantity, 'status' => StockReservationStatus::Consumed->value, 'reserved_until' => now()]);
                } else {
                    $reservation->update(['status' => StockReservationStatus::Consumed->value]);
                }

                ProductStockReservation::where('order_item_id', $item->id)->where('id', '!=', $reservation->id)->where('status', StockReservationStatus::Reserved->value)->update(['status' => StockReservationStatus::Released->value]);
                $item->product->decrement('stock_quantity', (int) $item->quantity);
            }

            $item->update(['serial_fulfillment_status' => $this->isAwaitingProduction($item) ? SerialFulfillmentStatus::AwaitingProduction->value : SerialFulfillmentStatus::Allocated->value]);
        }

        return $shortage;
    }

    /** @param  list<int|null>  $usedUnits */
    /** @return list<int> */
    private function historicalShippedUnits(OrderItem $item): array
    {
        return SerialEvent::where('order_item_id', $item->id)->where('event_type', SerialEventType::Shipped->value)->whereBetween('unit_number', [1, (int) $item->quantity])->distinct()->pluck('unit_number')->map(fn ($unit): int => (int) $unit)->all();
    }

    /** @return list<int> */
    private function fulfilledUnits(OrderItem $item): array
    {
        return ProductSerial::where('current_order_item_id', $item->id)->whereIn('status', [SerialStatus::Allocated->value, SerialStatus::Shipped->value, SerialStatus::Returned->value])->toBase()->pluck('unit_number')->merge($this->historicalShippedUnits($item))->map(fn ($unit): int => (int) $unit)->unique()->values()->all();
    }

    private function nextFreeUnit(array $usedUnits, int $quantity): int
    {
        for ($unit = 1; $unit <= $quantity; $unit++) {
            if (! in_array($unit, $usedUnits, true)) {
                return $unit;
            }
        }

        throw new LogicException('واحد آزاد برای تخصیص سریال یافت نشد.');
    }

    private function isAwaitingProduction(OrderItem $item): bool
    {
        return $this->itemMode($item) === InventoryMode::Manual && ($item->made_to_order ?? $item->product?->made_to_order ?? false) && $item->production_completed_at === null;
    }

    /** @param  EloquentCollection<int, OrderItem>  $items */
    private function hasMadeToOrder(EloquentCollection $items): bool
    {
        return $items->contains(fn (OrderItem $item): bool => $this->itemMode($item) === InventoryMode::Manual
            && $this->isAwaitingProduction($item));
    }

    /** @param  EloquentCollection<int, OrderItem>  $items */
    private function markItems(EloquentCollection $items, SerialFulfillmentStatus $status): void
    {
        foreach ($items as $item) {
            $item->update(['serial_fulfillment_status' => $status->value]);
        }
    }

    private function refreshFulfillmentStatus(Order $order): void
    {
        $current = $order->serial_fulfillment_status;

        if (in_array($current, [SerialFulfillmentStatus::Shipped, SerialFulfillmentStatus::Returned], true)) {
            return;
        }

        $items = $order->items()->get();

        if (! $this->hasSerialItems($items)) {
            return;
        }

        $states = ProductSerial::whereIn('current_order_item_id', $items->pluck('id')->all())
            ->toBase()->pluck('status')
            ->map(fn ($status): string => $status instanceof SerialStatus ? $status->value : (string) $status)
            ->unique()
            ->all();

        $derived = match (true) {
            in_array(SerialStatus::Returned->value, $states, true) => SerialFulfillmentStatus::Returned,
            in_array(SerialStatus::Shipped->value, $states, true) => SerialFulfillmentStatus::Shipped,
            in_array(SerialStatus::Allocated->value, $states, true) => SerialFulfillmentStatus::Allocated,
            in_array(SerialStatus::Reserved->value, $states, true) => SerialFulfillmentStatus::Reserved,
            default => SerialFulfillmentStatus::NotRequired,
        };

        $order->update(['serial_fulfillment_status' => $derived->value]);
    }

    private function freeSerial(
        ProductSerial $serial,
        SerialStatus $to,
        SerialEventType $event,
        ?Order $order,
        ?int $actorId,
        ?string $reason,
    ): void {
        $from = $serial->status instanceof SerialStatus ? $serial->status : SerialStatus::from((string) $serial->status);
        $item = $serial->current_order_item_id !== null ? OrderItem::find($serial->current_order_item_id) : null;

        $serial->update([
            'status' => $to->value,
            'current_order_item_id' => null,
            'unit_number' => null,
            'reserved_until' => null,
        ]);

        $this->recordEvent($serial, $event, $from, $to, $order, $item, null, $actorId, $reason);
    }

    private function recordEvent(
        ProductSerial $serial,
        SerialEventType $type,
        ?SerialStatus $from,
        SerialStatus $to,
        ?Order $order,
        ?OrderItem $item,
        ?int $unit,
        ?int $actorId,
        ?string $reason,
    ): void {
        SerialEvent::create([
            'product_serial_id' => $serial->id,
            'order_id' => $order?->id,
            'order_item_id' => $item?->id,
            'unit_number' => $unit,
            'event_type' => $type->value,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_id' => $actorId,
            'reason' => $reason,
            'operation_key' => strtolower($type->value).':'.($order?->id ?? 0).':'.($item?->id ?? 0).':'.($unit ?? 0).':'.Str::ulid(),
            'order_number_snapshot' => $order?->order_number,
            'platform_snapshot' => $order?->platform,
        ]);
    }

    private function transact(Closure $callback): void
    {
        $attempts = 0;

        while (true) {
            try {
                DB::transaction($callback);

                return;
            } catch (QueryException $e) {
                $attempts++;

                // Deadlock or lock-timeout only; domain errors propagate at once.
                if (($e->errorInfo[0] ?? null) !== '40001' && ! in_array((int) ($e->errorInfo[1] ?? 0), [1213, 1205], true) || $attempts >= 3) {
                    throw $e;
                }
            }
        }
    }
}
