<?php

namespace App\Filament\Pages\Pricing;

use App\Enums\Permission;
use App\Services\PriceBoardService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\Jalalian;
use Throwable;

/**
 * Compare our price board against Tokeniko's public board.
 *
 * Tokeniko is fetched live on every page open/refresh and never stored;
 * only our own board refresh touches the cache.
 */
class BoardComparisonPage extends Page
{
    protected static ?string $slug = 'pricing/compare-tokeniko';

    protected static ?string $title = 'مقایسه با توکنیکو';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'مقایسه با توکنیکو';

    protected static string|\UnitEnum|null $navigationGroup = 'تابلو قیمت زیوتو';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.pricing.board-comparison';

    private const TOKENIKO_URL = 'https://tokeniko.com/api/prices-with-change';

    /**
     * Board items compared, in display order.
     *
     * @var list<string>
     */
    private const ITEMS = ['Gold750', 'Gold995', 'Gold9999', 'Silver9999'];

    private const LABELS = [
        'Gold750' => 'طلای ۷۵۰',
        'Gold995' => 'طلای ۹۹۵',
        'Gold9999' => 'طلای ۹۹۹.۹',
        'Silver9999' => 'نقره ۹۹۹.۹',
    ];

    /**
     * @var list<array{name: string, label: string, ourSell: float|null, tokSell: float|null, sellDiffPct: float|null, ourBuy: float|null, tokBuy: float|null, buyDiffPct: float|null}>
     */
    public array $rows = [];

    public ?string $tokenikoUpdatedAt = null;

    public ?string $boardUpdatedAt = null;

    public ?string $tokenikoError = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::PricingView->value) ?? false;
    }

    public function mount(): void
    {
        $this->loadComparison();
    }

    /**
     * Refresh our board and re-fetch Tokeniko. Tokeniko data is never stored.
     */
    public function refresh(): void
    {
        if (! auth()->user()?->can(Permission::PricingEdit->value)) {
            Notification::make()->title('دسترسی غیرمجاز')->danger()->send();

            return;
        }

        app(PriceBoardService::class)->refresh();
        $this->loadComparison();

        Notification::make()
            ->title('بروزرسانی شد')
            ->body('تابلوی زیوتو و توکنیکو دوباره خوانده شد.')
            ->success()
            ->send();
    }

    private function loadComparison(): void
    {
        $priceBoard = app(PriceBoardService::class);
        $board = $priceBoard->getBoardPrices();
        $this->boardUpdatedAt = self::toJalali($priceBoard->getLastSyncAt()?->toDateTimeString());

        $tokeniko = $this->fetchTokeniko();
        $this->tokenikoUpdatedAt = self::toJalali($tokeniko['updatedAt']);
        $this->tokenikoError = $tokeniko['error'];

        $rows = [];

        foreach (self::ITEMS as $name) {
            $ourSell = isset($board[$name.'_Sell']['value']) ? (float) $board[$name.'_Sell']['value'] : null;
            $ourBuy = isset($board[$name.'_Buy']['value']) ? (float) $board[$name.'_Buy']['value'] : null;
            $tokSell = $tokeniko['products'][$name]['sell'] ?? null;
            $tokBuy = $tokeniko['products'][$name]['buy'] ?? null;

            $rows[] = [
                'name' => $name,
                'label' => self::LABELS[$name],
                'ourSell' => $ourSell,
                'tokSell' => $tokSell,
                'sellDiffPct' => self::diffPct($ourSell, $tokSell),
                'ourBuy' => $ourBuy,
                'tokBuy' => $tokBuy,
                'buyDiffPct' => self::diffPct($ourBuy, $tokBuy),
            ];
        }

        $this->rows = $rows;
    }

    /**
     * @return array{products: array<string, array{sell: float|null, buy: float|null}>, updatedAt: string|null, error: string|null}
     */
    private function fetchTokeniko(): array
    {
        $result = ['products' => [], 'updatedAt' => null, 'error' => null];

        try {
            $response = Http::timeout(15)->get(self::TOKENIKO_URL);
        } catch (Throwable $e) {
            Log::warning('[BoardComparison] Tokeniko request failed: '.$e->getMessage());
            $result['error'] = 'خطا در دریافت قیمت توکنیکو';

            return $result;
        }

        if ($response->failed()) {
            Log::warning('[BoardComparison] Tokeniko HTTP error', ['status' => $response->status()]);
            $result['error'] = 'خطا در دریافت قیمت توکنیکو (کد '.$response->status().')';

            return $result;
        }

        $data = $response->json();

        if (! is_array($data)) {
            $result['error'] = 'پاسخ نامعتبر از توکنیکو';

            return $result;
        }

        $result['updatedAt'] = $data['lastUpdate'] ?? null;

        foreach ($data['products'] ?? $data['data']['products'] ?? [] as $item) {
            if (! isset($item['name']) || ! in_array($item['name'], self::ITEMS, true)) {
                continue;
            }

            $result['products'][$item['name']] = [
                'sell' => isset($item['sellPrice']) && is_numeric($item['sellPrice']) ? (float) $item['sellPrice'] : null,
                'buy' => isset($item['buyPrice']) && is_numeric($item['buyPrice']) ? (float) $item['buyPrice'] : null,
            ];
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

    private static function toJalali(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Jalalian::forge($value)->format('Y-m-d H:i');
        } catch (Throwable $e) {
            Log::warning('[BoardComparison] Cannot parse date: '.$value);

            return $value;
        }
    }
}
