<?php

namespace App\Services;

use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use App\Models\User;
use Illuminate\Support\Str;
use Modules\Order\Models\Order;

class DiscountCodeService
{
    /**
     * @return array{code: ?DiscountCode, error: ?string}
     */
    public function validate(string $code, User $user, string $platform, bool $lock = false, ?int $excludeOrderId = null): array
    {
        $query = DiscountCode::query()->where('code', Str::upper(trim($code)));

        if ($lock) {
            $query->lockForUpdate();
        }

        $discountCode = $query->first();

        if (! $discountCode || ! $discountCode->is_active) {
            return ['code' => null, 'error' => 'کد تخفیف نامعتبر یا غیرفعال است'];
        }

        if (! in_array($platform, $discountCode->platforms ?? [], true)) {
            return ['code' => null, 'error' => 'این کد تخفیف برای این فروشگاه قابل استفاده نیست'];
        }

        if (($discountCode->starts_at && now()->lt($discountCode->starts_at)) || ($discountCode->ends_at && now()->gt($discountCode->ends_at))) {
            return ['code' => null, 'error' => 'مهلت استفاده از این کد تخفیف معتبر نیست'];
        }

        $usageQuery = $discountCode->usages()
            ->whereIn('status', ['reserved', 'redeemed'])
            ->when($excludeOrderId !== null, fn ($query) => $query->where(function ($usageQuery) use ($excludeOrderId) {
                $usageQuery->whereNull('order_id')->orWhere('order_id', '!=', $excludeOrderId);
            }))
            ->where(function ($query) {
                $query->where('status', 'redeemed')
                    ->orWhereNull('order_id')
                    ->orWhereHas('order', fn ($orderQuery) => $orderQuery->whereNotIn('status', ['cancelled', 'expired']));
            });
        $userUsageQuery = (clone $usageQuery)->where('user_id', $user->id);

        if ($discountCode->max_uses !== null && (clone $usageQuery)->count() >= $discountCode->max_uses) {
            return ['code' => null, 'error' => 'ظرفیت استفاده از این کد تخفیف تکمیل شده است'];
        }

        if ($discountCode->max_uses_per_customer !== null && $userUsageQuery->count() >= $discountCode->max_uses_per_customer) {
            return ['code' => null, 'error' => 'سقف استفاده شما از این کد تخفیف تکمیل شده است'];
        }

        return ['code' => $discountCode, 'error' => null];
    }

    public function reserveUsage(Order $order): void
    {
        if (! $order->discount_code_id || (int) $order->discount_amount <= 0) {
            return;
        }

        DiscountCodeUsage::query()->firstOrCreate(
            [
                'discount_code_id' => $order->discount_code_id,
                'order_id' => $order->id,
            ],
            ['user_id' => $order->user_id, 'status' => 'reserved'],
        );
    }

    public function redeemUsage(int $orderId): void
    {
        DiscountCodeUsage::query()
            ->where('order_id', $orderId)
            ->whereIn('status', ['reserved', 'released'])
            ->update(['status' => 'redeemed']);
    }

    public function releaseUsage(int $orderId): void
    {
        DiscountCodeUsage::query()
            ->where('order_id', $orderId)
            ->where('status', 'reserved')
            ->update(['status' => 'released']);
    }
}
