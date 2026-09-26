<?php

namespace App\Services\Pricing;

use App\Models\Setting;
use App\Services\PriceBoardService;
use Illuminate\Support\Facades\Cache;

/**
 * Computes per-user dynamic product prices using labor coefficients.
 *
 * price = weight × board(metal) × (1 + coefficient)  → round → tax → optional step rounding
 */
class DynamicPriceService
{
    public const METAL_OPTIONS = [
        'Gold750_Sell' => 'قیمت فروش طلای ۷۵۰ (۱۸ عیار)',
        'Gold750_Buy' => 'قیمت خرید طلای ۷۵۰ (۱۸ عیار)',
        'Gold995_Sell' => 'قیمت فروش طلای ۹۹۵ (۲۴ عیار)',
        'Gold995_Buy' => 'قیمت خرید طلای ۹۹۵ (۲۴ عیار)',
        'Gold9999_Sell' => 'قیمت فروش طلای ۹۹۹.۹ (۲۴ عیار)',
        'Gold9999_Buy' => 'قیمت خرید طلای ۹۹۹.۹ (۲۴ عیار)',
        'Silver9999_Sell' => 'قیمت فروش نقره ۹۹۹.۹',
        'Silver9999_Buy' => 'قیمت خرید نقره ۹۹۹.۹',
    ];

    public function __construct(
        private readonly LaborCalculator $labor,
        private readonly PriceBoardService $priceBoard,
    ) {}

    /**
     * Calculate price for a product for the given user (or guest).
     * Returns null when dynamic pricing does not apply (use stored price).
     */
    public function priceFor(object $product, ?int $userId = null, ?string $timePeriod = null, ?string $roleSlug = null): ?float
    {
        if (! PricingSettings::enableDynamicPricing()) {
            return null;
        }

        if (! $this->isEligibleProduct($product)) {
            return null;
        }

        $metalType = $product->price_board_item ?? null;
        $weight = (float) ($product->weight ?? 0);

        if ($metalType === null || $metalType === '' || $weight <= 0) {
            return null;
        }

        $boardPrice = $this->boardValue($metalType);
        if ($boardPrice === null) {
            return null;
        }

        $period = $timePeriod ?? $this->labor->getCurrentTimePeriod();
        $role = $roleSlug ?? $this->labor->getUserLaborRole($userId);
        $coefficient = $this->labor->coefficientFor($product, $period, $role);

        $calculated = $weight * $boardPrice * (1 + $coefficient);
        $calculated = round($calculated);

        $calculated = $this->applyTax($product, $calculated);
        $calculated = $this->applyStepRounding($calculated);

        return (float) $calculated;
    }

    /**
     * Base price for a product as the "basic" role default (used by sync).
     */
    public function basePriceFor(object $product): ?float
    {
        return $this->priceFor($product, userId: null, roleSlug: 'basic');
    }

    public function isEligibleProduct(object $product): bool
    {
        if (isset($product->dynamic_pricing_enabled) && $product->dynamic_pricing_enabled) {
            return true;
        }

        if (($product->price_type ?? null) === 'dynamic') {
            return true;
        }

        $metalCategories = PricingSettings::metalCategories();

        if ($metalCategories === []) {
            return false;
        }

        $productCategoryId = $product->category_id ?? null;
        if ($productCategoryId === null) {
            return false;
        }

        if (! in_array((int) $productCategoryId, $metalCategories, true)) {
            return false;
        }

        return ! empty($product->price_board_item) && ! empty($product->weight);
    }

    public function boardValue(string $metalKey): ?float
    {
        $prices = $this->priceBoard->getBoardPrices();

        if (! isset($prices[$metalKey]['value'])) {
            return null;
        }

        $value = (float) $prices[$metalKey]['value'];

        return $value > 0 ? $value : null;
    }

    /**
     * Pricing details for admin preview.
     *
     * @return array<string, mixed>|null
     */
    public function detailsFor(object $product, ?string $roleSlug = null, ?string $timePeriod = null): ?array
    {
        $metalType = $product->price_board_item ?? null;
        $weight = (float) ($product->weight ?? 0);

        if ($metalType === null || $metalType === '' || $weight <= 0) {
            return null;
        }

        $basePrice = $this->boardValue($metalType);
        if ($basePrice === null) {
            return null;
        }

        $period = $timePeriod ?? $this->labor->getCurrentTimePeriod();
        $role = $roleSlug ?? $this->labor->getUserLaborRole();
        $coefficient = $this->labor->coefficientFor($product, $period, $role);

        $calculated = round($weight * $basePrice * (1 + $coefficient));
        $calculated = $this->applyTax($product, $calculated);
        $calculated = $this->applyStepRounding($calculated);

        $prices = $this->priceBoard->getBoardPrices();

        return [
            'metal_type' => $metalType,
            'weight' => $weight,
            'coefficient' => $coefficient,
            'time_period' => $period,
            'user_role' => $role,
            'base_price' => $basePrice,
            'calculated_price' => (float) $calculated,
            'trend' => $prices[$metalType]['trend'] ?? null,
            'updated_at' => $prices[$metalType]['updated_at'] ?? null,
        ];
    }

    private function applyTax(object $product, float $price): float
    {
        $boardItem = $product->price_board_item ?? '';
        $taxKey = str_starts_with((string) $boardItem, 'Gold') || ($product->metal_type?->value ?? null) === 'gold'
            ? 'tax_gold'
            : 'tax_silver';

        $taxPercentage = (float) Setting::getValue($taxKey, 0);

        if ($taxPercentage <= 0) {
            return $price;
        }

        return round($price * (1 + $taxPercentage / 100));
    }

    private function applyStepRounding(float $price): float
    {
        if (! PricingSettings::roundPrices()) {
            return $price;
        }

        $step = PricingSettings::roundTo();
        if ($step <= 1) {
            return $price;
        }

        return round($price / $step) * $step;
    }

    public function clearBoardMemo(): void
    {
        Cache::forget('priceboard:prices');
    }
}
