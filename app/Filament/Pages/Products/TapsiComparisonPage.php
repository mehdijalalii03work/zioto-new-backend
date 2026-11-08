<?php

namespace App\Filament\Pages\Products;

use App\Enums\Permission;
use App\Services\Pricing\DynamicPriceService;
use App\Services\TapsiShopService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Product\Models\Product;

/**
 * Compare current product prices with the prices calculated for Tapsi Shop.
 */
class TapsiComparisonPage extends Page
{
    protected static ?string $slug = 'products/compare-tapsi';

    protected static ?string $title = 'مقایسه قیمت تپسی‌شاپ';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'مقایسه با تپسی‌شاپ';

    protected static string|\UnitEnum|null $navigationGroup = 'قیمت‌گذاری زیوتو';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.tapsi-comparison';

    /** @var list<array{id: int, name: string, productId: string, ourPrice: float|null, tapsiPrice: int|null, markupPercent: int|null, isBelowThreshold: bool|null, diffPct: float|null}> */
    public array $rows = [];

    public int $productCount = 0;

    public int $markupThreshold = 0;

    public int $markupBelowThreshold = 0;

    public int $markupAboveThreshold = 0;

    /** @var 'asc'|'desc'|null */
    public ?string $sortDirection = null;

    public string $priceSortDirection = 'asc';

    public string $sortColumn = 'price';

    public function mount(DynamicPriceService $dynamicPrice, TapsiShopService $tapsiShop): void
    {
        $this->loadComparison($dynamicPrice, $tapsiShop);
    }

    public function refresh(): void
    {
        if (! auth()->user()?->can(Permission::PricingEdit->value)) {
            Notification::make()->title('دسترسی غیرمجاز')->danger()->send();

            return;
        }

        $this->loadComparison(app(DynamicPriceService::class), app(TapsiShopService::class));

        Notification::make()
            ->title('بروزرسانی شد')
            ->body('قیمت محصولات و قیمت ارسالی به تپسی‌شاپ دوباره محاسبه شد.')
            ->success()
            ->send();
    }

    public function sortByDiff(): void
    {
        $this->sortColumn = 'diff';
        $this->sortDirection = $this->sortDirection === 'desc' ? 'asc' : 'desc';
        $direction = $this->sortDirection;

        usort($this->rows, function (array $a, array $b) use ($direction): int {
            $aNull = $a['diffPct'] === null;
            $bNull = $b['diffPct'] === null;

            if ($aNull || $bNull) {
                return $aNull === $bNull ? 0 : ($aNull ? 1 : -1);
            }

            $compared = abs($a['diffPct']) <=> abs($b['diffPct']);

            return $direction === 'desc' ? -$compared : $compared;
        });
    }

    public function sortByPrice(): void
    {
        $this->sortColumn = 'price';
        $this->priceSortDirection = $this->priceSortDirection === 'asc' ? 'desc' : 'asc';
        $direction = $this->priceSortDirection;

        usort($this->rows, function (array $a, array $b) use ($direction): int {
            $aNull = $a['ourPrice'] === null;
            $bNull = $b['ourPrice'] === null;

            if ($aNull || $bNull) {
                return $aNull === $bNull ? 0 : ($aNull ? 1 : -1);
            }

            $compared = $a['ourPrice'] <=> $b['ourPrice'];

            return $direction === 'desc' ? -$compared : $compared;
        });
    }

    private function loadComparison(DynamicPriceService $dynamicPrice, TapsiShopService $tapsiShop): void
    {
        $this->rows = [];
        $this->priceSortDirection = 'asc';
        $this->sortDirection = null;
        $this->sortColumn = 'price';
        $this->markupThreshold = (int) config('tapsi.markup_threshold', 50_000_000);
        $this->markupBelowThreshold = (int) config('tapsi.markup_below_threshold', 2);
        $this->markupAboveThreshold = (int) config('tapsi.markup_above_threshold', 1);

        $products = Product::query()
            ->whereNotNull('tapsi_product_id')
            ->where('tapsi_product_id', '!=', '')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            $ourPrice = $dynamicPrice->priceFor($product, roleSlug: 'basic', allowTokenikoPrice: false);

            if ($ourPrice === null && is_numeric($product->price)) {
                $ourPrice = (float) $product->price;
            }

            $tapsiPrice = $ourPrice === null ? null : $tapsiShop->calculateTapsiPrice($ourPrice);
            $markupPercent = $ourPrice === null ? null : $tapsiShop->markupPercentFor($ourPrice);
            $isBelowThreshold = $ourPrice === null ? null : $tapsiShop->isBelowMarkupThreshold($ourPrice);

            $this->rows[] = [
                'id' => $product->getKey(),
                'name' => $product->name,
                'productId' => (string) $product->tapsi_product_id,
                'ourPrice' => $ourPrice,
                'tapsiPrice' => $tapsiPrice,
                'markupPercent' => $markupPercent,
                'isBelowThreshold' => $isBelowThreshold,
                'diffPct' => self::diffPct($ourPrice, $tapsiPrice),
            ];
        }

        $this->productCount = count($this->rows);
        usort($this->rows, static fn (array $a, array $b): int => ($a['ourPrice'] ?? PHP_INT_MAX) <=> ($b['ourPrice'] ?? PHP_INT_MAX));
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::PricingView->value) ?? false;
    }

    private static function diffPct(?float $ourPrice, ?int $tapsiPrice): ?float
    {
        if ($ourPrice === null || $tapsiPrice === null || $ourPrice === 0) {
            return null;
        }

        return ($ourPrice - $tapsiPrice) / $ourPrice * 100;
    }
}
