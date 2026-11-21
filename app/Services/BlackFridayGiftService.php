<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Modules\Order\Models\Order;

class BlackFridayGiftService
{
    private const START = '2026-11-22 00:00:00';

    private const END = '2026-12-01 23:59:59.999999';

    private const TOMAN = 10;

    /** @return array{half_gram:int, one_gram:int} */
    public function quantitiesForOrder(Order $order): array
    {
        $empty = ['half_gram' => 0, 'one_gram' => 0];
        $createdAt = $order->created_at;

        if (! $createdAt || ! $createdAt->betweenIncluded(
            Carbon::parse(self::START, 'Asia/Tehran'),
            Carbon::parse(self::END, 'Asia/Tehran')
        )) {
            return $empty;
        }

        // Order item subtotals are in Rials; the cart campaign thresholds are in Tomans.
        $amountInRials = (int) $order->items->sum('subtotal');
        $amountInToman = intdiv($amountInRials, self::TOMAN);

        if ($amountInToman < 20_000_000) {
            return $amountInToman > 0 ? ['half_gram' => 1, 'one_gram' => 0] : $empty;
        }
        if ($amountInToman < 40_000_000) {
            return ['half_gram' => 0, 'one_gram' => 1];
        }
        if ($amountInToman < 100_000_000) {
            return ['half_gram' => 1, 'one_gram' => 1];
        }

        return [
            'half_gram' => 1,
            'one_gram' => intdiv($amountInToman, 100_000_000),
        ];
    }
}
