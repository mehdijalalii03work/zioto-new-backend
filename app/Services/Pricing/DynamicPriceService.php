<?php

namespace App\Services\Pricing;

use App\Models\Setting;
use App\Services\PriceBoardService;
use App\Services\TokenikoShopService;
use Illuminate\Support\Facades\Cache;

/**
 * Computes per-user dynamic product prices using labor coefficients.
 *
 * Board and product prices are stored in Rial; admin calculations use Toman.
 * Reference rounding ceilings gold after tax and silver before tax.
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
        private readonly TokenikoShopService $tokenikoShop,
    ) {}

    /**
     * Calculate price for a product for the given user (or guest).
     * Returns null when dynamic pricing does not apply (use stored price).
     */
    public function priceFor(object $product, ?int $userId = null, ?string $timePeriod = null, ?string $roleSlug = null, bool $allowTokenikoPrice = true): ?float
    {
        $tokenikoPrice = $allowTokenikoPrice ? $this->tokenikoPriceFor($product) : null;
        if ($tokenikoPrice !== null) {
            return $tokenikoPrice;
        }

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

        $calculated = $this->calculatePrice($product, $weight, $boardPrice, $coefficient)['price'];

        return (float) ($calculated * self::RIAL_PER_TOMAN * $this->packSize($product));
    }

    /**
     * Base price for a product as the "basic" role default (used by sync).
     */
    public function basePriceFor(object $product): ?float
    {
        return $this->priceFor($product, userId: null, roleSlug: 'basic', allowTokenikoPrice: false);
    }

    public function tokenikoPriceFor(object $product): ?float
    {
        if (! PricingSettings::tokenikoProductPricesEnabled()) {
            return null;
        }

        $tokenikoSku = trim((string) ($product->tokeniko_sku ?? ''));
        $tokenikoPrice = $tokenikoSku !== '' ? $this->tokenikoShop->getPriceByName($tokenikoSku) : null;

        if ($tokenikoPrice === null || ! is_numeric($tokenikoPrice) || (float) $tokenikoPrice <= 0) {
            return null;
        }

        return (float) $tokenikoPrice * $this->packSize($product);
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

        $withTaxes = $this->calculatePrice($product, $weight, $basePrice, $coefficient);
        $packSize = $this->packSize($product);
        $calculated = $withTaxes['price'] * $packSize;

        $prices = $this->priceBoard->getBoardPrices();

        return [
            'metal_type' => $metalType,
            'weight' => $weight,
            'coefficient' => $coefficient,
            'time_period' => $period,
            'user_role' => $role,
            'base_price' => $basePrice,
            'calculated_price' => (float) $calculated,
            'taxes' => $this->scaleTaxes($withTaxes['taxes'], $packSize),
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

        $packSize = $this->packSize($product);
        $rawCost = $weight * $basePrice * $packSize;
        $matrix = [];

        foreach ($periods as $period) {
            $periodSlug = $period['slug'];
            $matrix[$periodSlug] = [];

            foreach ($roles as $role) {
                $roleSlug = $role['slug'];
                $coefficient = $this->labor->coefficientFor($product, $periodSlug, $roleSlug);
                $labor = $rawCost * $coefficient;

                $withTaxes = $this->calculatePrice($product, $weight, $basePrice, $coefficient);
                $finalPrice = $withTaxes['price'] * $packSize;

                $matrix[$periodSlug][$roleSlug] = [
                    'coefficient' => $coefficient,
                    'labor_cost' => round($labor),
                    'final_price' => $finalPrice,
                    'taxes' => $this->scaleTaxes($withTaxes['taxes'], $packSize),
                ];
            }
        }

        return $matrix;
    }

    private function packSize(object $product): int
    {
        return max(1, (int) ($product->pack_size ?? 1));
    }

    /** @param list<array{scope: string, rate: float, amount: float}> $taxes
     * @return list<array{scope: string, rate: float, amount: float}>
     */
    private function scaleTaxes(array $taxes, int $packSize): array
    {
        foreach ($taxes as &$tax) {
            $tax['amount'] *= $packSize;
        }
        unset($tax);

        return $taxes;
    }

    /**
     * All public price outputs share the selected rounding and tax order.
     *
     * @return array{price: float, taxes: list<array{scope: string, rate: float, amount: float}>}
     */
    private function calculatePrice(object $product, float $weight, float $boardPrice, float $coefficient): array
    {
        if (! PricingSettings::roundPrices() || PricingSettings::roundingMode() !== 'reference') {
            $labor = $weight * $boardPrice * $coefficient;
            $result = $this->priceWithTaxes($product, round($weight * $boardPrice * (1 + $coefficient)), $labor);
            $result['price'] = $this->applyStepRounding($result['price']);

            return $result;
        }

        $base = bcmul($this->decimalString($weight), $this->decimalString($boardPrice), 10);
        $labor = bcmul($base, $this->decimalString($coefficient), 10);
        $price = bcadd($base, $labor, 10);
        $isGold = $this->isGold($product);
        $taxes = [];

        if (! $isGold) {
            $price = $this->ceilToStep($price, PricingSettings::silverRoundTo());
        }

        $metalRate = (float) Setting::getValue($isGold ? 'tax_gold' : 'tax_silver', 0);
        if ($metalRate > 0) {
            $amount = bcdiv(bcmul($price, $this->decimalString($metalRate), 10), '100', 10);
            $taxes[] = ['scope' => 'total', 'rate' => $metalRate, 'amount' => (float) $amount];
            $price = bcadd($price, $amount, 10);
        }

        if ($isGold && bccomp($labor, '0', 10) > 0) {
            $laborRate = PricingSettings::taxGoldLabor();
            if ($laborRate > 0) {
                $amount = bcdiv(bcmul($labor, $this->decimalString($laborRate), 10), '100', 10);
                $taxes[] = ['scope' => 'labor', 'rate' => $laborRate, 'amount' => (float) $amount];
                $price = bcadd($price, $amount, 10);
            }
        }

        if ($isGold) {
            $price = $this->ceilToStep($price, PricingSettings::roundTo());
        }

        return ['price' => (float) $price, 'taxes' => $taxes];
    }

    /**
     * BCMath rejects scientific notation produced by small PHP floats.
     */
    private function decimalString(float $value): string
    {
        $decimal = (string) $value;

        return str_contains(strtolower($decimal), 'e') ? sprintf('%.10F', $value) : $decimal;
    }

    private function ceilToStep(string $price, int $step): string
    {
        $step = max(1, $step);
        $rounded = bcmul(bcdiv($price, (string) $step, 0), (string) $step, 10);

        return bccomp($rounded, $price, 10) < 0
            ? bcadd($rounded, (string) $step, 10)
            : $rounded;
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
