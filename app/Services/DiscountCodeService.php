<?php

namespace App\Services;

use App\Models\DiscountCode;
use App\Models\User;
use Illuminate\Support\Str;

class DiscountCodeService
{
    /**
     * @return array{code: ?DiscountCode, error: ?string}
     */
    public function validate(string $code, User $user, string $platform, bool $lock = false): array
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

        $usageQuery = $discountCode->usages();
        if ($discountCode->max_uses !== null && (clone $usageQuery)->count() >= $discountCode->max_uses) {
            return ['code' => null, 'error' => 'ظرفیت استفاده از این کد تخفیف تکمیل شده است'];
        }

        if ($discountCode->max_uses_per_customer !== null && (clone $usageQuery)->where('user_id', $user->id)->count() >= $discountCode->max_uses_per_customer) {
            return ['code' => null, 'error' => 'سقف استفاده شما از این کد تخفیف تکمیل شده است'];
        }

        return ['code' => $discountCode, 'error' => null];
    }
}
