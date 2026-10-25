<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidateDiscountCodeRequest;
use App\Models\Cart;
use App\Services\DiscountCodeService;
use App\Support\Platform;
use Illuminate\Http\JsonResponse;

class DiscountCodeController extends Controller
{
    public function validate(ValidateDiscountCodeRequest $request, DiscountCodeService $discountCodeService): JsonResponse
    {
        $user = $request->user();
        $result = $discountCodeService->validate(
            $request->validated('code'),
            $user,
            Platform::fromRequest($request),
        );

        if ($result['error']) {
            return response()->json([
                'message' => $result['error'],
                'error_code' => 'DISCOUNT_CODE_INVALID',
            ], 422);
        }

        $discountCode = $result['code'];
        $cartTotal = Cart::where('user_id', $user->id)
            ->with('product:id,price')
            ->get()
            ->sum(fn (Cart $item): int => (int) $item->product->price * (int) $item->quantity);

        if ($cartTotal <= 0) {
            return response()->json(['message' => 'سبد خرید شما خالی است', 'error_code' => 'CART_EMPTY'], 422);
        }

        return response()->json([
            'eligible' => true,
            'code' => $discountCode->code,
            'discount_amount' => min((int) $discountCode->discount_amount, $cartTotal),
            'cart_total' => $cartTotal,
        ]);
    }
}
