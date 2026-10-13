<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\InventoryMode;
use App\Models\ProductInventoryAudit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Product\Models\Product;

final class ProductInventorySettingsService
{
    public const FIELDS = ['stock_quantity', 'inventory_mode', 'serial_prefix', 'track_serials', 'made_to_order', 'production_lead_time_text'];

    public function update(Product $product, array $data, User $actor, ?string $reason = null): Product
    {
        return $product->getConnection()->transaction(function () use ($product, $data, $actor, $reason): Product {
            $record = $product->exists ? $product->newQuery()->lockForUpdate()->findOrFail($product->id) : $product;
            Validator::make(array_intersect_key($data, array_flip(self::FIELDS)), [
                'stock_quantity' => ['sometimes', 'required', 'integer', 'min:0'],
                'inventory_mode' => ['sometimes', 'required', Rule::in(['manual', 'serial'])],
                'track_serials' => ['sometimes', 'boolean'],
                'made_to_order' => ['sometimes', 'boolean'],
            ])->validate();
            $before = $this->settings($record);
            $candidate = clone $record;
            $candidate->fill(array_intersect_key($data, array_flip(self::FIELDS)));
            $after = $this->settings($candidate);

            // Serial products sell from free serials only: stock_quantity is
            // history in that mode, and a stale value would silently go live
            // again on a future switch back to manual. Retire it on the spot,
            // whether this save switches the mode or only heals a stale value.
            if (($after['inventory_mode'] ?? null) === InventoryMode::Serial->value) {
                $after['stock_quantity'] = 0;
            }

            $changes = array_diff_assoc($after, $before);

            if ($changes !== []) {
                if (! $actor->checkPermissionTo($record->exists ? 'product.edit' : 'product.create') || ! $actor->checkPermissionTo('serial.manage')) {
                    throw new AuthorizationException('اجازه مدیریت موجودی ندارید.');
                }
                Validator::make($after + ['reason' => $reason], [
                    'stock_quantity' => ['required', 'integer', 'min:0'],
                    'inventory_mode' => ['required', Rule::in(['manual', 'serial'])],
                    'serial_prefix' => ['nullable', 'regex:/\A[a-z]{3}9?\z/', Rule::unique('products', 'serial_prefix')->ignore($record->id)],
                    'track_serials' => ['boolean'],
                    'made_to_order' => ['boolean'],
                    'production_lead_time_text' => ['nullable', 'string', 'max:255'],
                    'reason' => ['required', 'string', 'max:500', 'regex:/\S/'],
                ])->validate();
                if (($after['inventory_mode'] === 'serial' && ! $after['track_serials']) || (($after['inventory_mode'] === 'serial' || $after['track_serials']) && ! $after['serial_prefix'])) {
                    throw ValidationException::withMessages(['serial_prefix' => 'موجودی سریالی و رهگیری نیازمند پیشوند معتبر و فعال بودن رهگیری است.']);
                }
                if ($record->exists) {
                    $reserved = $record->manualReservations()->where('status', 'reserved')->lockForUpdate()->get();
                    if ($after['stock_quantity'] < $reserved->sum('quantity')) {
                        throw ValidationException::withMessages(['stock_quantity' => 'ظرفیت نمی‌تواند از تعداد رزروشده کمتر باشد.']);
                    }
                    $mappingChanged = array_intersect(array_keys($changes), ['inventory_mode', 'serial_prefix', 'track_serials']);
                    if ($mappingChanged) {
                        $activeSerial = $record->serials()->whereIn('status', ['reserved', 'allocated'])->lockForUpdate()->first(['id']);
                        if ($reserved->isNotEmpty() || $activeSerial || app(ProductInventoryObligations::class)->hasOpenOrders($record)) {
                            throw ValidationException::withMessages(['inventory_mode' => 'ابتدا تعهدهای باز محصول را تعیین تکلیف کنید.']);
                        }
                    }
                    if ($record->serials()->lockForUpdate()->first(['id']) !== null && (array_key_exists('serial_prefix', $changes) || (isset($changes['track_serials']) && ! $after['track_serials']))) {
                        throw ValidationException::withMessages(['serial_prefix' => 'پیشوند و رهگیری محصول دارای تاریخچه سریال قابل حذف یا انتقال نیست.']);
                    }
                }
            }

            $record->fill(array_diff_key($data, ['inventory_reason' => true]));
            $record->fill($after);
            $record->save();
            if ($changes !== []) {
                ProductInventoryAudit::on($record->getConnectionName())->create(['product_id' => $record->id, 'actor_id' => $actor->id, 'reason' => trim($reason), 'before' => $before, 'after' => $after]);
            }

            return $record;
        }, 3);
    }

    private function settings(Product $product): array
    {
        return [
            'stock_quantity' => $product->stock_quantity ?? 0,
            'inventory_mode' => $product->inventory_mode?->value ?? 'manual',
            'serial_prefix' => is_string($product->serial_prefix) ? (strtolower(trim($product->serial_prefix)) ?: null) : null,
            'track_serials' => (bool) $product->track_serials,
            'made_to_order' => (bool) $product->made_to_order,
            'production_lead_time_text' => $product->production_lead_time_text,
        ];
    }
}
