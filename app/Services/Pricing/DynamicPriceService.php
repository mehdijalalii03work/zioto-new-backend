<?php

namespace App\Services\Pricing;

use App\Models\Setting;
use App\Services\PriceBoardService;
use Illuminate\Support\Facades\Cache;

/**
 * Computes per-user dynamic product prices using labor coefficients.
 *
 * board is stored in Toman, products.price is stored in Rial:
 *
 * price(Rial) = weight × board(metal, Toman) × (1 + coefficient)  → round → tax → labor tax (gold) → step rounding → ×10
 */
class DynamicPriceService
{
    /**
     * products.price is Rial while the price board (and the admin preview) is Toman.
     */
    public const RIAL_PER_TOMAN = 10;

    public const METAL_OPTIONS = [
        'Gold750_Sell' => 'قیمت فروش طلای ۷۵۰ (۱۸ عیار)',
        'Gold750_Buy' => 'قیمت خرید طلای ۷۵۰ (۱۸ عیار)',
        'Gold995_Sell' => 'قیمت فروش طلای ۹۹۵ (۲۴ عیار)',
        'Gold995_Buy' => 'قیمت خرید طلای ۹۹۵ (۲۴ عیار)',
        'Gold9999_Sell' => 'قیمت فروش طلای ۹۹۹.۹ (۲۴ عیار)',
        'Gold9999_Buy' => 'قیمت خرید طلای ۹۹۹.۹ (۲۴ عیار)',
        'Gold999_Sell' => 'قیمت فروش طلای ۹۹۹ (۲۴ عیار)',
        'Gold999_Buy' => 'قیمت خرید طلای ۹۹۹ (۲۴ عیار)',
        'Silver9999_Sell' => 'قیمت فروش نقره ۹۹۹.۹',
        'Silver9999_Buy' => 'قیمت خرید نقره ۹۹۹.۹',
        'Silver999_Sell' => 'قیمت فروش نقره ۹۹۹',
        'Silver999_Buy' => 'قیمت خرید نقره ۹۹۹',
    ];

    private ?array $boardMemo = null;

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

        $labor = $weight * $boardPrice * $coefficient;
        $withTaxes = $this->priceWithTaxes($product, round($weight * $boardPrice * (1 + $coefficient)), $labor);
        $calculated = $this->applyStepRounding($withTaxes['price']);

        return (float) ($calculated * self::RIAL_PER_TOMAN);
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

    /**
     * Full board map, memoized for the lifetime of the instance so pricing many
     * products (or a preview matrix) only reads the cache once.
     *
     * @return array<string, array>
     */
    public function boardPrices(): array
    {
        return $this->boardMemo ??= $this->priceBoard->getBoardPrices();
    }

    public function boardValue(string $metalKey): ?float
    {
        $prices = $this->boardPrices();

        if (! isset($prices[$metalKey]['value'])) {
            return null;
        }

        $value = (float) $prices[$metalKey]['value'];

        // Price board now returns Rials; convert to Tomans for calculations
        return $value > 0 ? $value / self::RIAL_PER_TOMAN : null;
    }

    /**
     * Price breakdown for the admin preview page.
     * Returns Toman (the unit shown on the board), not Rial like priceFor().
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

        $labor = $weight * $basePrice * $coefficient;
        $withTaxes = $this->priceWithTaxes($product, round($weight * $basePrice * (1 + $coefficient)), $labor);
        $calculated = $this->applyStepRounding($withTaxes['price']);

        $prices = $this->priceBoard->getBoardPrices();

        return [
            'metal_type' => $metalType,
            'weight' => $weight,
            'coefficient' => $coefficient,
            'time_period' => $period,
            'user_role' => $role,
            'base_price' => $basePrice,
            'calculated_price' => (float) $calculated,
            'taxes' => $withTaxes['taxes'],
            'trend' => $prices[$metalType]['trend'] ?? null,
            'updated_at' => $prices[$metalType]['updated_at'] ?? null,
        ];
    }

    /**
     * Coefficient / labor cost / final price for every time period × role.
     *
     * Same math as priceFor() but returned in Toman (the unit shown on the
     * preview page) and computed with a single board lookup per product.
     *
     * @param  list<array{slug: string}>  $periods
     * @param  list<array{slug: string}>  $roles
     * @return array<string, array<string, array{coefficient: float, labor_cost: float, final_price: float, taxes: list<array{scope: string, rate: float, amount: float>}>}>
     */
    public function previewMatrix(object $product, array $periods, array $roles): array
    {
        $metalType = $product->price_board_item ?? null;
        $weight = (float) ($product->weight ?? 0);

        if ($metalType === null || $metalType === '' || $weight <= 0) {
            return [];
        }

        $basePrice = $this->boardValue($metalType);
        if ($basePrice === null) {
            return [];
        }

        $rawCost = $weight * $basePrice;
        $matrix = [];

        foreach ($periods as $period) {
            $periodSlug = $period['slug'];
            $matrix[$periodSlug] = [];

            foreach ($roles as $role) {
                $roleSlug = $role['slug'];
                $coefficient = $this->labor->coefficientFor($product, $periodSlug, $roleSlug);
                $labor = $rawCost * $coefficient;

                $withTaxes = $this->priceWithTaxes($product, round($rawCost * (1 + $coefficient)), $labor);
                $finalPrice = $this->applyStepRounding($withTaxes['price']);

                $matrix[$periodSlug][$roleSlug] = [
                    'coefficient' => $coefficient,
                    'labor_cost' => round($labor),
                    'final_price' => $finalPrice,
                    'taxes' => $withTaxes['taxes'],
                ];
            }
        }

        return $matrix;
    }

    /**
     * Applies every tax that belongs to the product and reports what it added.
     *
     * Gold: `tax_gold` on the whole price (usually 0) plus `tax_gold_labor` on
     * the labor only. Silver: `tax_silver` on the whole price, labor included.
     *
     * @return array{price: float, taxes: list<array{scope: string, rate: float, amount: float}>}
     */
    private function priceWithTaxes(object $product, float $price, float $labor): array
    {
        $isGold = $this->isGold($product);
        $taxes = [];

        $metalRate = (float) Setting::getValue($isGold ? 'tax_gold' : 'tax_silver', 0);

        if ($metalRate > 0) {
            $taxed = $this->applyTax($product, $price);
            $taxes[] = ['scope' => 'total', 'rate' => $metalRate, 'amount' => $taxed - $price];
            $price = $taxed;
        }

        if ($isGold && $labor > 0) {
            $laborRate = PricingSettings::taxGoldLabor();

            if ($laborRate > 0) {
                $taxed = $this->applyLaborTax($product, $price, $labor);
                $taxes[] = ['scope' => 'labor', 'rate' => $laborRate, 'amount' => $taxed - $price];
                $price = $taxed;
            }
        }

        return ['price' => $price, 'taxes' => $taxes];
    }

    private function applyTax(object $product, float $price): float
    {
        $taxKey = $this->isGold($product) ? 'tax_gold' : 'tax_silver';

        $taxPercentage = (float) Setting::getValue($taxKey, 0);

        if ($taxPercentage <= 0) {
            return $price;
        }

        return round($price * (1 + $taxPercentage / 100));
    }

    /**
     * VAT charged on the labor (making) fee only, added on top of the price
     * built from the board. Used for gold while `tax_gold` is zero, so the
     * metal itself stays untaxed.
     */
    private function applyLaborTax(object $product, float $price, float $labor): float
    {
        if ($labor <= 0 || ! $this->isGold($product)) {
            return $price;
        }

        $taxPercentage = PricingSettings::taxGoldLabor();

        if ($taxPercentage <= 0) {
            return $price;
        }

        return round($price + $labor * $taxPercentage / 100);
    }

    private function isGold(object $product): bool
    {
        return str_starts_with((string) ($product->price_board_item ?? ''), 'Gold')
            || ($product->metal_type?->value ?? null) === 'gold';
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
        $this->boardMemo = null;

        Cache::forget('priceboard:prices');
    }
}
