<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyDiscountRequest;
use App\Models\User;
use App\Services\Pricing\DiscountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function __construct(
        private readonly DiscountService $discounts,
    ) {}

    public function apply(ApplyDiscountRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $code = $request->validated('code');

        $snapshot = $this->discounts->cartSnapshot($user->id);

        if ($snapshot['items'] === []) {
            return response()->json([
                'message' => 'سبد خرید شما خالی است',
                'error_code' => 'CART_EMPTY',
            ], 422);
        }

        $result = $this->discounts->validate($code, $user, $snapshot['total'], $snapshot['items']);

        if (! $result['valid']) {
            return response()->json([
                'message' => $result['message'],
                'error_code' => 'DISCOUNT_INVALID',
            ], 422);
        }

        $discount = $result['discount'];
        $amount = $this->discounts->calculate($discount, $snapshot['items'], $snapshot['total'], $user);
        $this->discounts->applyForUser($user->id, $discount, $amount);

        return response()->json([
            'message' => sprintf('کد تخفیف اعمال شد. مبلغ تخفیف: %s تومان', number_format($amount)),
            'data' => [
                'code' => $discount->code,
                'amount' => $amount,
                'type' => $discount->type,
                'cart_total' => (int) $snapshot['total'],
            ],
        ]);
    }

    public function remove(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->discounts->removeForUser($user->id);

        return response()->json([
            'message' => 'کد تخفیف حذف شد',
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $session = $this->discounts->activeDiscount($user->id);

        if (! $session) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => $session,
        ]);
    }
}
