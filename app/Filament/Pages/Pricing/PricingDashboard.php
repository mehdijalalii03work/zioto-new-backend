<?php

namespace App\Filament\Pages\Pricing;

use App\Enums\Permission;
use App\Services\PriceBoardService;
use App\Services\Pricing\DynamicPriceService;
use App\Services\Pricing\PricingSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Morilog\Jalali\Jalalian;

class PricingDashboard extends Page
{
    /**
     * Raw base prices whose last change time is surfaced on the dashboard.
     *
     * @var list<string>
     */
    public const PRICE_CHANGE_TRACKED = [
        'persian.Gold750',
        'tala.Gold750',
        'persian.Silver999',
    ];

    protected static ?string $slug = 'pricing/dashboard';

    protected static ?string $title = 'داشبورد قیمت‌گذاری';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'داشبورد';

    protected static string|\UnitEnum|null $navigationGroup = 'تابلو قیمت زیوتو';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.pricing.pricing-dashboard';

    public array $board = [];

    public array $sourceStatus = [];

    public array $basePrices = ['persian' => [], 'tala' => []];

    public array $formula = [];

    public ?string $updatedAt = null;

    public ?string $lastSuccessAt = null;

    /**
     * Persistent per-source timestamps of the last API response that carried data.
     * Read from settings, so it survives cache expiry and never depends on a price change.
     *
     * @var array<string, int>
     */
    public array $lastRequestTimes = [];

    public ?string $lastRequestAt = null;

    /**
     * Persistent per-source timestamps of the last time a tracked raw base price
     * actually changed value. Keyed by "{source}.{metal}".
     *
     * @var array<string, int>
     */
    public array $lastPriceChangeTimes = [];

    public ?string $lastPriceChangeAt = null;

    public int $cacheDuration = 0;

    public bool $usedFallback = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::PricingView->value) ?? false;
    }

    public function mount(): void
    {
        $this->refreshBoard();
    }

    public function refreshBoard(): void
    {
        $this->applyPayload(app(PriceBoardService::class)->getPrices());
    }

    public function forceRefresh(): void
    {
        if (! auth()->user()?->can(Permission::PricingEdit->value)) {
            Notification::make()->title('دسترسی غیرمجاز')->danger()->send();

            return;
        }

        $this->applyPayload(app(PriceBoardService::class)->refresh());

        Notification::make()->title('تابلو بروزرسانی شد')->success()->send();
    }

    public function clearCache(): void
    {
        if (! auth()->user()?->can(Permission::PricingEdit->value)) {
            Notification::make()->title('دسترسی غیرمجاز')->danger()->send();

            return;
        }

        app(PriceBoardService::class)->clearCache();
        $this->refreshBoard();

        Notification::make()->title('کش پاک شد')->success()->send();
    }

    public function getMetalOptionsProperty(): array
    {
        return DynamicPriceService::METAL_OPTIONS;
    }

    /**
     * @param  array{prices?: array, source_status?: array, raw?: array, used_fallback?: bool}  $payload
     */
    private function applyPayload(array $payload): void
    {
        $this->board = $payload['prices'] ?? [];
        $this->sourceStatus = $payload['source_status'] ?? [];
        $this->basePrices = $payload['raw'] ?? ['persian' => [], 'tala' => []];
        $this->usedFallback = (bool) ($payload['used_fallback'] ?? false);

        $this->updatedAt = app(PriceBoardService::class)->getLastSyncAt()?->toDateTimeString();
        $this->lastSuccessAt = $this->resolveLastSuccess();
        $this->lastRequestTimes = $this->resolveLastRequestTimes();
        $this->lastRequestAt = $this->resolveLastRequestAt();
        $this->lastPriceChangeTimes = $this->resolveLastPriceChangeTimes();
        $this->lastPriceChangeAt = $this->resolveLastPriceChangeAt();
        $this->cacheDuration = PricingSettings::cacheDurationSeconds();
        $this->formula = $this->computeFormula($this->basePrices);
    }

    /**
     * @return array<string, int>
     */
    private function resolveLastRequestTimes(): array
    {
        $times = [];

        foreach (PricingSettings::lastSuccessfulRawTimes() as $source => $time) {
            $time = (int) $time;

            if ($time > 0) {
                $times[$source] = $time;
            }
        }

        return $times;
    }

    private function resolveLastRequestAt(): ?string
    {
        if ($this->lastRequestTimes === []) {
            return null;
        }

        return $this->jalaliFormat(max($this->lastRequestTimes));
    }

    /**
     * @return array<string, int>
     */
    private function resolveLastPriceChangeTimes(): array
    {
        $all = PricingSettings::lastRawPriceChanges();
        $times = [];

        foreach (self::PRICE_CHANGE_TRACKED as $path) {
            $times[$path] = max(0, (int) ($all[$path] ?? 0));
        }

        return $times;
    }

    private function resolveLastPriceChangeAt(): ?string
    {
        $known = array_filter($this->lastPriceChangeTimes);

        if ($known === []) {
            return null;
        }

        return $this->jalaliFormat(max($known));
    }

    private function resolveLastSuccess(): ?string
    {
        $latest = null;

        foreach ($this->sourceStatus as $status) {
            $time = isset($status['last_time']) ? (int) $status['last_time'] : 0;

            if ($time > 0 && ($latest === null || $time > $latest)) {
                $latest = $time;
            }
        }

        if ($latest === null) {
            return null;
        }

        return $this->jalaliFormat($latest);
    }

    private function jalaliFormat(int $timestamp): string
    {
        return Jalalian::forge($timestamp, new \DateTimeZone((string) config('app.timezone')))->format('Y-m-d H:i');
    }

    /**
     * Step-by-step walkthrough of the board formulas with live numbers
     * (port of the WordPress dashboard's "نحوه محاسبه قیمت‌ها" section).
     *
     * @return array<string, array<string, mixed>>
     */
    private function computeFormula(array $raw): array
    {
        $persian = $raw['persian'] ?? [];
        $tala = $raw['tala'] ?? [];

        $coef = fn (string $key): string => PricingSettings::coefficient($key);
        $num = fn (mixed $value): ?float => ($value !== null && $value !== '' && is_numeric($value)) ? (float) $value : null;

        $coefGold999To995 = $coef('coef_gold999_to_gold995');
        $coefGold999To9999 = $coef('coef_gold999_to_gold9999');
        $coefGold995SellRatio = $coef('coef_gold995_sell_ratio');
        $coefGold995BuyRatio = $coef('coef_gold995_buy_ratio');
        $coefGold9999SellRatio = $coef('coef_gold9999_sell_ratio');
        $coefGold9999BuyRatio = $coef('coef_gold9999_buy_ratio');
        $coefBuy = $coef('coef_buy_price');
        $coefSilver9999To999 = $coef('coef_silver9999_to_silver999');
        $coefSilverBuy = $coef('coef_silver_buy_price');
        $coefGold750SellRatio = $coef('coef_gold750_sell_ratio');
        $coefGold750BuyRatio = $coef('coef_gold750_buy_ratio');

        $persianGold750 = $num($persian['Gold750'] ?? null);
        $talaGold750Effective = $num($tala['Gold750'] ?? null);

        $hasPersian = $persianGold750 !== null && $persianGold750 > 0;
        $hasTala = $talaGold750Effective !== null && $talaGold750Effective > 0;

        $maxApi = null;
        $maxSource = '—';
        if ($hasPersian && $hasTala) {
            if ($persianGold750 > $talaGold750Effective) {
                $maxApi = $persianGold750;
                $maxSource = 'PersianAPI';
            } else {
                $maxApi = $talaGold750Effective;
                $maxSource = 'Tala.ir';
            }
        } elseif ($hasPersian) {
            $maxApi = $persianGold750;
            $maxSource = 'PersianAPI';
        } elseif ($hasTala) {
            $maxApi = $talaGold750Effective;
            $maxSource = 'Tala.ir';
        }

        $minApi = null;
        $minSource = '—';
        $apisEqual = false;
        if ($hasPersian && $hasTala) {
            if ($persianGold750 == $talaGold750Effective) {
                $minApi = $persianGold750;
                $minSource = 'Equal';
                $apisEqual = true;
            } elseif ($persianGold750 < $talaGold750Effective) {
                $minApi = $persianGold750;
                $minSource = 'PersianAPI';
            } else {
                $minApi = $talaGold750Effective;
                $minSource = 'Tala.ir';
            }
        } elseif ($hasPersian) {
            $minApi = $persianGold750;
            $minSource = 'PersianAPI';
        } elseif ($hasTala) {
            $minApi = $talaGold750Effective;
            $minSource = 'Tala.ir';
        }

        $manualGold750 = $num(PricingSettings::manualGold750Sell());
        $manualGold750Rial = ($manualGold750 !== null && $manualGold750 > 0) ? $manualGold750 * 10 : null;

        $gold750Sell = $maxApi !== null ? $maxApi * (float) $coefGold750SellRatio : null;
        $sellLogic = 'Max API × HighValueRatio ('.number_format((float) $coefGold750SellRatio, 4).')';
        if ($manualGold750Rial !== null) {
            $gold750Sell = $manualGold750Rial * (float) $coefGold750SellRatio;
            $sellLogic = 'قیمت دستی × HighValueRatio (دستی همیشه اولویت دارد)';
        }

        $gold750Buy = $minApi !== null ? $minApi * (float) $coefGold750BuyRatio : null;
        $buyLogic = 'Min API × LowValueRatio ('.number_format((float) $coefGold750BuyRatio, 4).')';
        if ($manualGold750Rial !== null && $gold750Sell !== null) {
            $gold750Buy = $gold750Sell * (float) $coefBuy;
            $buyLogic = 'قیمت فروش × '.$coefBuy.' (قیمت دستی اعمال شد)';
        } elseif ($apisEqual && $gold750Sell !== null) {
            $gold750Buy = $gold750Sell * (float) $coefBuy;
            $buyLogic = 'Persian و Tala برابر → خرید = فروش × ضریب';
        }

        $persianGold999 = $num($persian['Gold999'] ?? null);
        $talaGold999 = $num($tala['Gold999'] ?? null);

        $coefGold999SellRatio = (float) $coef('coef_gold999_sell_ratio');
        $coefGold999BuyRatio = (float) $coef('coef_gold999_buy_ratio');
        $manualGold999 = $num(PricingSettings::manualGold999Sell());
        $manualGold999Rial = ($manualGold999 !== null && $manualGold999 > 0) ? $manualGold999 * 10 : null;

        $hasPersian999 = $persianGold999 !== null && $persianGold999 > 0;
        $hasTala999 = $talaGold999 !== null && $talaGold999 > 0;

        $maxApi999 = null;
        $maxSource999 = '—';
        if ($hasPersian999 && $hasTala999) {
            $maxApi999 = max($persianGold999, $talaGold999);
            $maxSource999 = $persianGold999 >= $talaGold999 ? 'PersianAPI' : 'Tala.ir';
        } elseif ($hasPersian999) {
            $maxApi999 = $persianGold999;
            $maxSource999 = 'PersianAPI';
        } elseif ($hasTala999) {
            $maxApi999 = $talaGold999;
            $maxSource999 = 'Tala.ir';
        }

        $gold999Sell = $maxApi999 !== null ? $maxApi999 * $coefGold999SellRatio : null;
        $sellLogic999 = $maxApi999 !== null
            ? 'Max API ('.$maxSource999.') × HighValueRatio ('.number_format($coefGold999SellRatio, 4).')'
            : 'منبعی در دسترس نیست';
        if ($manualGold999Rial !== null) {
            $gold999Sell = $manualGold999Rial * $coefGold999SellRatio;
            $sellLogic999 = 'قیمت دستی × HighValueRatio (دستی همیشه اولویت دارد)';
        }

        $minApi999 = null;
        $minSource999 = '—';
        $apisEqual999 = false;
        if ($hasPersian999 && $hasTala999) {
            if ($persianGold999 == $talaGold999) {
                $minApi999 = $persianGold999;
                $minSource999 = 'Equal';
                $apisEqual999 = true;
            } else {
                $minApi999 = min($persianGold999, $talaGold999);
                $minSource999 = $persianGold999 < $talaGold999 ? 'PersianAPI' : 'Tala.ir';
            }
        } elseif ($hasPersian999) {
            $minApi999 = $persianGold999;
            $minSource999 = 'PersianAPI';
        } elseif ($hasTala999) {
            $minApi999 = $talaGold999;
            $minSource999 = 'Tala.ir';
        }

        $gold999Buy = $minApi999 !== null ? $minApi999 * $coefGold999BuyRatio : null;
        $buyLogic999 = $minApi999 !== null
            ? 'Min API ('.$minSource999.') × LowValueRatio ('.number_format($coefGold999BuyRatio, 4).')'
            : '—';
        if ($manualGold999Rial !== null && $gold999Sell !== null) {
            $gold999Buy = $gold999Sell * (float) $coefBuy;
            $buyLogic999 = 'قیمت فروش × '.$coefBuy.' (قیمت دستی اعمال شد)';
        } elseif ($apisEqual999 && $gold999Sell !== null) {
            $gold999Buy = $gold999Sell * (float) $coefBuy;
            $buyLogic999 = 'Persian و Tala برابر → خرید = فروش × ضریب';
        } elseif ($minApi999 === null && $gold999Sell !== null) {
            $gold999Buy = $gold999Sell * (float) $coefBuy;
            $buyLogic999 = 'قیمت فروش × '.$coefBuy;
        }

        $gold995Sell = $gold999Sell !== null ? $gold999Sell * (float) $coefGold999To995 * (float) $coefGold995SellRatio : null;
        $gold995Buy = $gold999Buy !== null ? $gold999Buy * (float) $coefGold999To995 * (float) $coefGold995BuyRatio : null;

        $gold9999Sell = $gold999Sell !== null ? $gold999Sell * (float) $coefGold999To9999 * (float) $coefGold9999SellRatio : null;
        $gold9999Buy = $gold999Buy !== null ? $gold999Buy * (float) $coefGold999To9999 * (float) $coefGold9999BuyRatio : null;

        $persianSilver999 = $num($persian['Silver999'] ?? null);
        $manualSilver9999 = $num(PricingSettings::manualSilver9999Sell());
        $manualSilver9999Rial = ($manualSilver9999 !== null && $manualSilver9999 > 0) ? $manualSilver9999 * 10 : null;

        $silver9999Sell = $manualSilver9999Rial;
        $silverSellLogic = $silver9999Sell !== null ? 'قیمت دستی (تنها منبع نقره)' : 'قیمت دستی وارد نشده';

        $silver999Sell = $silver9999Sell !== null ? $silver9999Sell * (float) $coefSilver9999To999 : null;
        $silver9999Buy = $silver9999Sell !== null ? $silver9999Sell * (float) $coefSilverBuy : null;
        $silver999Buy = $silver999Sell !== null ? $silver999Sell * (float) $coefSilverBuy : null;

        return [
            'coefs' => [
                'gold999_to_gold995' => $coefGold999To995,
                'gold999_to_gold9999' => $coefGold999To9999,
                'gold995_sell_ratio' => $coefGold995SellRatio,
                'gold995_buy_ratio' => $coefGold995BuyRatio,
                'gold9999_sell_ratio' => $coefGold9999SellRatio,
                'gold9999_buy_ratio' => $coefGold9999BuyRatio,
                'buy' => $coefBuy,
                'silver9999_to_silver999' => $coefSilver9999To999,
                'silver_buy' => $coefSilverBuy,
                'gold750_sell_ratio' => $coefGold750SellRatio,
                'gold750_buy_ratio' => $coefGold750BuyRatio,
                'gold999_sell_ratio' => (string) $coefGold999SellRatio,
                'gold999_buy_ratio' => (string) $coefGold999BuyRatio,
            ],
            'gold750' => [
                'persian' => $persianGold750,
                'talaDirect' => $talaGold750Effective,
                'talaEffective' => $talaGold750Effective,
                'maxApi' => $maxApi,
                'maxSource' => $maxSource,
                'minApi' => $minApi,
                'minSource' => $minSource,
                'apisEqual' => $apisEqual,
                'manual' => $manualGold750Rial,
                'sell' => $gold750Sell,
                'sellLogic' => $sellLogic,
                'buy' => $gold750Buy,
                'buyLogic' => $buyLogic,
            ],
            'gold995' => [
                'sell' => $gold995Sell,
                'buy' => $gold995Buy,
            ],
            'gold9999' => [
                'sell' => $gold9999Sell,
                'buy' => $gold9999Buy,
            ],
            'gold999' => [
                'persian' => $persianGold999,
                'tala' => $talaGold999,
                'manual' => $manualGold999Rial,
                'maxApi' => $maxApi999,
                'maxSource' => $maxSource999,
                'minApi' => $minApi999,
                'minSource' => $minSource999,
                'sell' => $gold999Sell,
                'sellLogic' => $sellLogic999,
                'buy' => $gold999Buy,
                'buyLogic' => $buyLogic999,
            ],
            'silver9999' => [
                'persian999' => $persianSilver999,
                'manual' => $manualSilver9999Rial,
                'sell' => $silver9999Sell,
                'sellLogic' => $silverSellLogic,
                'buy' => $silver9999Buy,
                'silver999_sell' => $silver999Sell,
                'silver999_buy' => $silver999Buy,
            ],
        ];
    }
}
