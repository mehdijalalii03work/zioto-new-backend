<?php

namespace App\Services\Pricing;

use Modules\Product\Models\Product;

/**
 * Sales availability restrictions (disabled products/categories + sales hours).
 *
 * Port of WordPress ZiotoPricing\Sales_Controller.
 */
class SalesRestrictionService
{
    /**
     * Whether the product can currently be purchased.
     */
    public function canPurchase(?Product $product): bool
    {
        if (! $product) {
            return false;
        }

        if ($this->isProductSalesDisabled($product->id)) {
            return false;
        }

        if ($this->isCategorySalesDisabled($product)) {
            return false;
        }

        if (! $this->isWithinSalesHours()) {
            return false;
        }

        return true;
    }

    /**
     * Machine-readable reason when sales are blocked.
     */
    public function blockedReason(?Product $product): ?string
    {
        if (! $product) {
            return 'invalid_product';
        }

        if ($this->isProductSalesDisabled($product->id)) {
            return 'product_disabled';
        }

        if ($this->isCategorySalesDisabled($product)) {
            return 'category_disabled';
        }

        if (! $this->isWithinSalesHours()) {
            return 'outside_hours';
        }

        return null;
    }

    public function humanMessage(?string $reason): string
    {
        return match ($reason) {
            'product_disabled' => 'فروش این محصول در حال حاضر غیرفعال است.',
            'category_disabled' => 'فروش محصولات این دسته‌بندی در حال حاضر غیرفعال است.',
            'outside_hours' => sprintf(
                'خرید محصولات فقط از ساعت %s تا %s امکان‌پذیر است.',
                PricingSettings::salesStartTime(),
                PricingSettings::salesEndTime(),
            ),
            'invalid_product' => 'محصول یافت نشد.',
            default => 'امکان خرید این محصول وجود ندارد.',
        };
    }

    public function isProductSalesDisabled(int $productId): bool
    {
        return in_array($productId, PricingSettings::disabledProducts(), true);
    }

    public function isCategorySalesDisabled(Product $product): bool
    {
        $disabledCategories = PricingSettings::disabledCategories();

        if ($disabledCategories === []) {
            return false;
        }

        $categoryId = $product->category_id ?? null;
        if ($categoryId === null) {
            return false;
        }

        return in_array((int) $categoryId, $disabledCategories, true);
    }

    public function isWithinSalesHours(): bool
    {
        if (! PricingSettings::enableSalesHours()) {
            return true;
        }

        $start = PricingSettings::salesStartTime();
        $end = PricingSettings::salesEndTime();
        $current = now()->format('H:i');

        $currentTime = strtotime($current);
        $startTime = strtotime($start);
        $endTime = strtotime($end);

        if ($endTime < $startTime) {
            return $currentTime >= $startTime || $currentTime < $endTime;
        }

        return $currentTime >= $startTime && $currentTime < $endTime;
    }
}
