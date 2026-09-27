<?php

namespace App\Filament\Pages\Pricing;

use App\Enums\Permission;
use App\Filament\Resources\Products\ProductResource;
use App\Services\Pricing\DynamicPriceService;
use App\Services\Pricing\LaborCalculator;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;
use Throwable;

/**
 * WordPress-style price preview: the live board strip on top, then every
 * dynamically priced product against each time period × labor role.
 */
class PricePreviewPage extends Page
{
    protected static ?string $slug = 'pricing/preview';

    protected static ?string $title = 'پیش‌نمایش قیمت';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'پیش‌نمایش قیمت';

    protected static string|\UnitEnum|null $navigationGroup = 'قیمت‌گذاری زیوتو';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.pricing.price-preview';

    /**
     * Short board labels, shared by the live price strip and the «نوع» column.
     */
    private const BOARD_LABELS = [
        'Gold750_Sell' => 'طلای ۷۵۰',
        // 'Gold750_Buy' => 'طلای ۷۵۰ (خرید)',
        'Gold995_Sell' => 'طلای ۹۹۵',
        // 'Gold995_Buy' => 'طلای ۹۹۵ (خرید)',
        'Gold9999_Sell' => 'طلای ۹۹۹.۹',
        // 'Gold9999_Buy' => 'طلای ۹۹۹.۹ (خرید)',
        'Silver9999_Sell' => 'نقره ۹۹۹.۹',
        // 'Silver9999_Buy' => 'نقره ۹۹۹.۹ (خرید)',
    ];

    /**
     * @var list<array{label: string, kind: string, value: float|null}>
     */
    public array $livePrices = [];

    /**
     * @var list<array{slug: string, name: string, start: string, end: string}>
     */
    public array $periods = [];

    /**
     * @var list<array{slug: string, name: string}>
     */
    public array $roles = [];

    /**
     * Slug of the time period currently shown as the active tab.
     */
    public string $activePeriod = '';

    /**
     * One row per product, cells keyed by period slug then role order to match the header.
     *
     * @var list<array{id: int, name: string, edit_url: string, metal_label: string, weight: float, cells: array<string, list<array{coefficient: float, labor_cost: float, final_price: float}|null>>}>
     */
    public array $rows = [];

    public int $productCount = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::PricingView->value) ?? false;
    }

    public function mount(): void
    {
        $labor = app(LaborCalculator::class);

        $this->periods = array_values($labor->getTimePeriods());
        $this->roles = array_values($labor->getRoles());
        $this->activePeriod = $this->resolveDefaultPeriod($labor);

        $service = app(DynamicPriceService::class);

        try {
            $prices = $service->boardPrices();
        } catch (Throwable $exception) {
            Log::warning('[PricePreview] Unable to load the price board: '.$exception->getMessage());

            $prices = [];
        }

        $this->livePrices = $this->resolveLivePrices($prices);
        $this->rows = $this->buildRows($service);
        $this->productCount = count($this->rows);
    }

    /**
     * Switch the visible time period tab.
     */
    public function setPeriod(string $periodSlug): void
    {
        if (! in_array($periodSlug, array_column($this->periods, 'slug'), true)) {
            return;
        }

        $this->activePeriod = $periodSlug;
    }

    private function resolveDefaultPeriod(LaborCalculator $labor): string
    {
        $slugs = array_column($this->periods, 'slug');
        $current = $labor->getCurrentTimePeriod();

        return in_array($current, $slugs, true) ? $current : (string) ($slugs[0] ?? '');
    }

    /**
     * @param  array<string, array>  $prices
     * @return list<array{label: string, kind: string, value: float|null}>
     */
    private function resolveLivePrices(array $prices): array
    {
        $items = [];

        foreach (self::BOARD_LABELS as $key => $label) {
            $value = isset($prices[$key]['value']) ? (float) $prices[$key]['value'] : null;

            $items[] = [
                'label' => $label,
                'kind' => str_starts_with($key, 'Gold') ? 'gold' : 'silver',
                'value' => ($value !== null && $value > 0) ? $value : null,
            ];
        }

        return $items;
    }

    /**
     * @return list<array{id: int, name: string, edit_url: string, metal_label: string, weight: float, cells: array<string, list<array{coefficient: float, labor_cost: float, final_price: float}|null>>}>
     */
    private function buildRows(DynamicPriceService $service): array
    {
        $products = Product::query()
            ->whereNotNull('price_board_item')
            ->where('weight', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Product $product): bool => $service->isEligibleProduct($product));

        return $products->map(function (Product $product) use ($service): array {
            $matrix = $service->previewMatrix($product, $this->periods, $this->roles);
            $cells = [];

            foreach ($this->periods as $period) {
                $cells[$period['slug']] = array_values($matrix[$period['slug']] ?? []);
            }

            return [
                'id' => $product->getKey(),
                'name' => $product->name,
                'edit_url' => ProductResource::getUrl('edit', ['record' => $product]),
                'metal_label' => self::BOARD_LABELS[$product->price_board_item] ?? $product->price_board_item,
                'weight' => (float) $product->weight,
                'cells' => $cells,
            ];
        })->values()->all();
    }
}
