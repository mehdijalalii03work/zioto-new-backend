<?php

namespace App\Services\Pricing;

use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Discount code validation, calculation, and cart integration.
 *
 * Applied discounts are cached per user (API is token-auth / stateless).
 * Port of WordPress ZiotoPricing\Discount_Code (business logic only).
 */
class DiscountService
{
    public function __construct(
        private readonly LaborCalculator $labor,
        private readonly DynamicPriceService $dynamicPrice,
    ) {}

    private function cacheKey(int $userId): string
    {
        return "zioto_discount:user:{$userId}";
    }

    /**
     * Validate a discount code for the current cart/user.
     *
     * @return array{valid: bool, message: string, discount?: DiscountCode}
     */
    public function validate(string $code, ?User $user, float $cartTotal, array $cartItems): array
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return ['valid' => false, 'message' => 'کد تخفیف را وارد کنید.'];
        }

        $discount = DiscountCode::where('code', $code)->first();

        if (! $discount) {
            return ['valid' => false, 'message' => 'کد تخفیف نامعتبر است.'];
        }

        if (! $discount->is_active) {
            return ['valid' => false, 'message' => 'این کد تخفیف غیرفعال است.'];
        }

        if ($discount->usage_limit > 0 && $discount->usage_count >= $discount->usage_limit) {
            return ['valid' => false, 'message' => 'تعداد استفاده از این کد تخفیف به پایان رسیده است.'];
        }

        if ($discount->isExpired()) {
            return ['valid' => false, 'message' => 'این کد تخفیف منقضی شده است.'];
        }

        if ($discount->min_order_amount > 0 && $cartTotal < $discount->min_order_amount) {
            return [
                'valid' => false,
                'message' => sprintf('حداقل مبلغ سفارش برای این کد تخفیف %s تومان است.', number_format($discount->min_order_amount)),
            ];
        }

        if ($discount->is_user_specific) {
            if (! $user) {
                return ['valid' => false, 'message' => 'برای استفاده از این کد تخفیف باید وارد حساب کاربری شوید.'];
            }

            $allowed = array_map('intval', (array) $discount->allowed_users);
            if (! in_array((int) $user->id, $allowed, true)) {
                return ['valid' => false, 'message' => 'این کد تخفیف برای حساب کاربری شما قابل استفاده نیست.'];
            }
        }

        $allowedRoles = (array) $discount->allowed_labor_roles;
        if ($allowedRoles !== []) {
            $userRole = $this->labor->getUserLaborRole($user?->id);
            if (! in_array($userRole, $allowedRoles, true)) {
                return ['valid' => false, 'message' => 'این کد تخفیف برای سطح کاربری شما قابل استفاده نیست.'];
            }
        }

        $allowedMetals = (array) $discount->allowed_metal_types;
        if ($allowedMetals !== [] && $cartItems !== []) {
            $hasValidItem = false;

            foreach ($cartItems as $item) {
                $metalType = $item['metal_type'] ?? null;
                if (is_string($metalType) && $metalType !== '' && in_array($metalType, $allowedMetals, true)) {
                    $hasValidItem = true;
                    break;
                }
            }

            if (! $hasValidItem) {
                return ['valid' => false, 'message' => 'این کد تخفیف فقط برای برخی از انواع فلزات قابل استفاده است.'];
            }
        }

        return [
            'valid' => true,
            'message' => 'کد تخفیف اعمال شد.',
            'discount' => $discount,
        ];
    }

    /**
     * Calculate discount amount for a discount + cart.
     *
     * @param  array<int, array{product_id:int, quantity:int, metal_type?:string, product?:object}>  $cartItems
     */
    public function calculate(DiscountCode $discount, array $cartItems, float $cartTotal, ?User $user = null): float
    {
        $amount = 0.0;

        switch ($discount->type) {
            case 'labor_percent':
                $amount = $this->calculateLaborDiscount($discount, $cartItems, $user);
                break;

            case 'total_percent':
                $amount = $cartTotal * ($discount->value / 100);
                break;

            case 'fixed_amount':
                $amount = min($discount->value, $cartTotal);
                break;
        }

        return (float) round(max(0, $amount));
    }

    private function calculateLaborDiscount(DiscountCode $discount, array $cartItems, ?User $user): float
    {
        $amount = 0.0;
        $period = $this->labor->getCurrentTimePeriod();
        $role = $this->labor->getUserLaborRole($user?->id);
        $allowedMetals = (array) $discount->allowed_metal_types;

        foreach ($cartItems as $item) {
            $product = $item['product'] ?? null;
            if (! $product) {
                continue;
            }

            $metalType = $product->price_board_item ?? null;
            $weight = (float) ($product->weight ?? 0);

            if (! $metalType || $weight <= 0) {
                continue;
            }

            if ($allowedMetals !== [] && ! in_array($metalType, $allowedMetals, true)) {
                continue;
            }

            $basePrice = $this->dynamicPrice->boardValue($metalType);
            if ($basePrice === null) {
                continue;
            }

            $rawCost = $weight * $basePrice;
            $coefficient = $this->labor->coefficientFor($product, $period, $role);
            $laborCost = $rawCost * $coefficient;
            $laborDiscount = $laborCost * ($discount->value / 100);
            $quantity = (int) ($item['quantity'] ?? 1);

            $amount += $laborDiscount * $quantity;
        }

        return $amount;
    }

    /**
     * Load cart items shaped for discount validation/calculation.
     *
     * @return array{items: array, total: float}
     */
    public function cartSnapshot(int $userId): array
    {
        $cartItems = Cart::where('user_id', $userId)
            ->with('product:id,name,price,weight,price_board_item,price_type,labor_coefficients,dynamic_pricing_enabled,category_id,metal_type')
            ->get();

        $items = [];
        $total = 0.0;

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->product;
            if (! $product) {
                continue;
            }

            $price = $this->dynamicPrice->priceFor($product, $userId)
                ?? (float) $product->price;

            $subtotal = $price * $cartItem->quantity;
            $total += $subtotal;

            $items[] = [
                'product_id' => $product->id,
                'quantity' => $cartItem->quantity,
                'metal_type' => $product->price_board_item,
                'product' => $product,
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Apply discount for the given user.
     */
    public function applyForUser(int $userId, DiscountCode $discount, float $amount): void
    {
        Cache::put($this->cacheKey($userId), [
            'id' => $discount->id,
            'code' => $discount->code,
            'amount' => $amount,
        ], now()->addDay());
    }

    public function removeForUser(int $userId): void
    {
        Cache::forget($this->cacheKey($userId));
    }

    /**
     * @return array{id:int, code:string, amount:float}|null
     */
    public function activeDiscount(int $userId): ?array
    {
        $data = Cache::get($this->cacheKey($userId));

        if (! is_array($data) || ! isset($data['id'], $data['code'])) {
            return null;
        }

        return [
            'id' => (int) $data['id'],
            'code' => (string) $data['code'],
            'amount' => (float) ($data['amount'] ?? 0),
        ];
    }

    /**
     * Recompute amount for order placement from a stored per-user discount.
     *
     * @return array{discount: ?DiscountCode, amount: float, code: ?string}
     */
    public function resolveForOrder(int $userId): array
    {
        $stored = $this->activeDiscount($userId);

        if (! $stored) {
            return ['discount' => null, 'amount' => 0.0, 'code' => null];
        }

        $discount = DiscountCode::find($stored['id']);

        if (! $discount || ! $discount->isUsable()) {
            $this->removeForUser($userId);

            return ['discount' => null, 'amount' => 0.0, 'code' => null];
        }

        $snapshot = $this->cartSnapshot($userId);
        $user = User::find($userId);
        $amount = $this->calculate($discount, $snapshot['items'], $snapshot['total'], $user);

        if ($amount <= 0) {
            return ['discount' => $discount, 'amount' => 0.0, 'code' => $discount->code];
        }

        return ['discount' => $discount, 'amount' => $amount, 'code' => $discount->code];
    }

    public function incrementUsage(DiscountCode $discount): void
    {
        $discount->increment('usage_count');
    }
}
