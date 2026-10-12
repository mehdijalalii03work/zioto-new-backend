<?php

namespace App\Filament\Pages\Products;

use App\Enums\Permission;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;
use Throwable;

/**
 * Compare our product prices against Tokeniko's shop prices.
 *
 * Every product with a tokeniko_sku is a row. Tokeniko is fetched live on
 * every page open/refresh and never stored.
 */
class ProductComparisonPage extends Page
{
    protected static ?string $slug = 'products/compare-tokeniko';

    protected static ?string $title = 'مقایسه قیمت محصولات';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationLabel = 'مقایسه با توکنیکو';

    protected static string|\UnitEnum|null $navigationGroup = 'قیمت‌گذاری زیوتو';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.product-comparison';

    private const TOKENIKO_URL = 'https://apigateway.tokeniko.com/shop/api/Category/getPrices';

    /**
     * @var list<array{id: int, name: string, sku: string, ourPrice: float|null, tokPrice: float|null, diffPct: float|null}>
     */
    public array $rows = [];

    public ?string $tokenikoError = null;

    public int $productCount = 0;

    /**
     * Raw threshold input (allowed difference percent).
     */
    public ?string $allowedDiffInput = '0.5';

    /**
     * Applied threshold; rows with abs(diffPct) >= it are highlighted.
     */
    public ?float $diffThreshold = 0.5;

    /**
     * Sort direction for the diff column (by absolute value).
     *
     * @var 'asc'|'desc'
     */
    public string $sortDirection = 'desc';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::PricingView->value) ?? false;
    }

    public function mount(): void
    {
        $this->loadComparison();
    }

    /**
     * Re-read our prices and re-fetch Tokeniko. Tokeniko data is never stored.
     */
    public function refresh(): void
    {
        if (! auth()->user()?->can(Permission::PricingEdit->value)) {
            Notification::make()->title('دسترسی غیرمجاز')->danger()->send();

            return;
        }

        $this->loadComparison();

        Notification::make()
            ->title('بروزرسانی شد')
            ->body('قیمت محصولات و قیمت توکنیکو دوباره خوانده شد.')
            ->success()
            ->send();
    }

    /**
     * Apply the allowed-difference threshold; rows beyond it highlight yellow.
     * Empty input clears the highlight.
     */
    public function applyThreshold(): void
    {
        $input = trim((string) $this->allowedDiffInput);

        if ($input === '') {
            $this->diffThreshold = null;

            return;
        }

        if (! is_numeric($input) || (float) $input < 0 || (float) $input > 1000) {
            Notification::make()
                ->title('خطا')
                ->body('درصد مجاز معتبر وارد کنید (۰ تا ۱۰۰۰).')
                ->danger()
                ->send();

            return;
        }

        $this->diffThreshold = (float) $input;
    }

    private function loadComparison(): void
    {
        $products = Product::query()
            ->whereNotNull('tokeniko_sku')
            ->where('tokeniko_sku', '!=', '')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $tokeniko = $this->fetchTokeniko();
        $this->tokenikoError = $tokeniko['error'];

        $rows = [];

        foreach ($products as $product) {
            $ourPrice = is_numeric($product->price) ? (float) $product->price : null;
            $tokPrice = $tokeniko['prices'][mb_strtolower(trim((string) $product->tokeniko_sku))] ?? null;

            $rows[] = [
                'id' => $product->getKey(),
                'name' => $product->name,
                'sku' => (string) $product->tokeniko_sku,
                'ourPrice' => $ourPrice,
                'tokPrice' => $tokPrice,
                'diffPct' => self::diffPct($ourPrice, $tokPrice),
            ];
        }

        $this->rows = $rows;
        $this->productCount = count($rows);
        $this->sortRows();
    }

    /**
     * Toggle diff-column sorting (by absolute value, missing values last).
     */
    public function sortByDiff(): void
    {
        $this->sortDirection = $this->sortDirection === 'desc' ? 'asc' : 'desc';
        $this->sortRows();
    }

    private function sortRows(): void
    {
        $direction = $this->sortDirection;

        usort($this->rows, function (array $a, array $b) use ($direction): int {
            $aNull = $a['diffPct'] === null;
            $bNull = $b['diffPct'] === null;

            if ($aNull && $bNull) {
                return 0;
            }

            if ($aNull) {
                return 1;
            }

            if ($bNull) {
                return -1;
            }

            $compared = abs($a['diffPct']) <=> abs($b['diffPct']);

            return $direction === 'desc' ? -$compared : $compared;
        });
    }

    /**
     * @return array{prices: array<string, float>, error: string|null}
     */
    private function fetchTokeniko(): array
    {
        $result = ['prices' => [], 'error' => null];

        try {
            $response = Http::withoutVerifying()->timeout(15)->get(self::TOKENIKO_URL);
        } catch (Throwable $e) {
            Log::warning('[ProductComparison] Tokeniko request failed: '.$e->getMessage());
            $result['error'] = 'خطا در دریافت قیمت توکنیکو';

            return $result;
        }

        if ($response->failed()) {
            Log::warning('[ProductComparison] Tokeniko HTTP error', ['status' => $response->status()]);
            $result['error'] = 'خطا در دریافت قیمت توکنیکو (کد '.$response->status().')';

            return $result;
        }

        $data = $response->json();

        if (! is_array($data) || empty($data['Model']) || ! is_array($data['Model'])) {
            $result['error'] = 'پاسخ نامعتبر از توکنیکو';

            return $result;
        }

        foreach ($data['Model'] as $item) {
            if (empty($item['Name']) || ! isset($item['SellPrice']) || ! is_numeric($item['SellPrice'])) {
                continue;
            }

            $result['prices'][mb_strtolower(trim($item['Name']))] = (float) $item['SellPrice'];
        }

        return $result;
    }

    private static function diffPct(?float $ours, ?float $theirs): ?float
    {
        if ($ours === null || $theirs === null || $theirs == 0) {
            return null;
        }

        return ($ours - $theirs) / $theirs * 100;
    }
}
