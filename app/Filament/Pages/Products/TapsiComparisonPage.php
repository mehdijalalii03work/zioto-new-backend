<?php

namespace App\Filament\Pages\Products;

use App\Enums\Permission;
use App\Services\Pricing\DynamicPriceService;
use App\Services\TapsiShopService;
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

    /** @var list<array{id: int, name: string, productId: string, ourPrice: float|null, tapsiPrice: int|null, diffPct: float|null}> */
    public array $rows = [];

    public int $productCount = 0;

    public function mount(DynamicPriceService $dynamicPrice, TapsiShopService $tapsiShop): void
    {
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

            $this->rows[] = [
                'id' => $product->getKey(),
                'name' => $product->name,
                'productId' => (string) $product->tapsi_product_id,
                'ourPrice' => $ourPrice,
                'tapsiPrice' => $tapsiPrice,
                'diffPct' => self::diffPct($ourPrice, $tapsiPrice),
            ];
        }

        $this->productCount = count($this->rows);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::PricingView->value) ?? false;
    }

    private static function diffPct(?float $ourPrice, ?int $tapsiPrice): ?float
    {
        if ($ourPrice === null || $tapsiPrice === null || $tapsiPrice === 0) {
            return null;
        }

        return ($ourPrice - $tapsiPrice) / $tapsiPrice * 100;
    }
}
