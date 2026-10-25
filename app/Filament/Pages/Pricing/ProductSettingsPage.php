<?php

namespace App\Filament\Pages\Pricing;

use App\Enums\Permission;
use App\Models\Setting;
use App\Services\Pricing\PricingSettings;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class ProductSettingsPage extends Page
{
    protected static ?string $slug = 'pricing/product-settings';

    protected static ?string $title = 'تنظیمات فروش و قیمت گذاری';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'تنظیمات فروش و قیمت گذاری';

    protected static string|\UnitEnum|null $navigationGroup = 'قیمت‌گذاری زیوتو';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.pricing.product-settings';

    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::PricingView->value) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'sales_closed' => ! PricingSettings::salesEnabled(),
            'tokeniko_product_prices_enabled' => PricingSettings::tokenikoProductPricesEnabled(),
            'enable_dynamic_pricing' => PricingSettings::enableDynamicPricing(),
            'round_prices' => PricingSettings::roundPrices(),
            'round_to' => PricingSettings::roundTo(),
            'rounding_mode' => PricingSettings::roundingMode(),
            'silver_round_to' => PricingSettings::silverRoundTo(),
            'tax_gold_labor' => PricingSettings::taxGoldLabor(),
            'show_discount' => PricingSettings::showDiscountBadge(),
            'enable_sales_hours' => PricingSettings::enableSalesHours(),
            'sales_start_time' => PricingSettings::salesStartTime(),
            'sales_end_time' => PricingSettings::salesEndTime(),
            'weekly_holiday_days' => PricingSettings::weeklyHolidayDays(),
            'special_holiday_dates' => array_map(
                static fn (string $date): array => ['date' => $date],
                PricingSettings::specialHolidayDates(),
            ),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('وضعیت فروش')
                    ->icon('heroicon-o-shopping-bag')
                    ->schema([
                        Toggle::make('sales_closed')
                            ->label('توقف فروش محصولات')
                            ->inline(false)
                            ->helperText('با فعال‌کردن این گزینه، افزودن محصول به سبد و ثبت سفارش جدید برای همه محصولات متوقف می‌شود. سفارش‌های ثبت‌شده قبلی قابل پرداخت می‌مانند.'),
                    ])
                    ->columnSpanFull(),

                Section::make('منبع قیمت محصولات')
                    ->icon('heroicon-o-arrow-path')
                    ->schema([
                        Toggle::make('tokeniko_product_prices_enabled')
                            ->label('دریافت مستقیم قیمت محصولات از توکنیکو')
                            ->inline(false)
                            ->helperText('با فعال‌کردن این گزینه، قیمت فروش محصولات دارای SKU توکنیکو مستقیماً از توکنیکو دریافت می‌شود و محاسبه ضریب و اجرت برای آن محصولات اعمال نمی‌شود.'),
                    ])
                    ->columnSpanFull(),

                Section::make('قیمت‌گذاری محصول')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('enable_dynamic_pricing')->label('قیمت‌گذاری پویا')->inline(false),
                            Toggle::make('round_prices')->label('گردکردن قیمت محصول')->inline(false),
                            Select::make('rounding_mode')
                                ->label('روش گردکردن')
                                ->options([
                                    'nearest' => 'نزدیک‌ترین مضرب پس از مالیات',
                                    'reference' => 'رو به بالا؛ طلا پس از مالیات، نقره پیش از مالیات',
                                ])
                                ->required()
                                ->in(['nearest', 'reference'])
                                ->helperText('فقط هنگام فعال بودن گردکردن قیمت محصول اعمال می‌شود.'),
                            TextInput::make('round_to')
                                ->label('مضرب گردکردن نهایی (طلا در روش رو به بالا)')
                                ->integer()->required()->minValue(1)->suffix('تومان')->default(1000),
                            TextInput::make('silver_round_to')
                                ->label('مضرب نقره پیش از مالیات')
                                ->integer()->required()->minValue(1)->suffix('تومان')->default(10000)
                                ->helperText('فقط در روش رو به بالا؛ ابتدا فلز و اجرت گرد می‌شوند، سپس مالیات اعمال می‌شود.'),
                            TextInput::make('tax_gold_labor')
                                ->label('درصد مالیات اجرت طلا')
                                ->numeric()
                                ->suffix('٪')
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(100)
                                ->helperText('فقط روی اجرتِ محصولاتِ قیمت‌گذاری پویا (از تابلو) اعمال می‌شود.'),
                            Toggle::make('show_discount')->label('نمایش نشان تخفیف')->inline(false),
                        ]),
                    ])->columnSpanFull(),

                Section::make('ساعات فروش')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('enable_sales_hours')->label('فعال‌سازی محدودیت ساعات')->inline(false),
                            TextInput::make('sales_start_time')->label('شروع')->placeholder('09:00'),
                            TextInput::make('sales_end_time')->label('پایان')->placeholder('23:00'),
                        ]),
                    ])->columnSpanFull(),

                Section::make('تقویم روزهای کاری و تعطیل')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        CheckboxList::make('weekly_holiday_days')
                            ->label('روزهای تعطیل هفتگی')
                            ->options([
                                6 => 'شنبه',
                                0 => 'یکشنبه',
                                1 => 'دوشنبه',
                                2 => 'سه‌شنبه',
                                3 => 'چهارشنبه',
                                4 => 'پنجشنبه',
                                5 => 'جمعه',
                            ])
                            ->columns(4)
                            ->default(PricingSettings::DEFAULT_WEEKLY_HOLIDAY_DAYS)
                            ->helperText('روزهای انتخاب‌شده در تمام ساعات با ضریب بازه شبانه محاسبه می‌شوند؛ روزهای دیگر روز کاری هستند.'),
                        Repeater::make('special_holiday_dates')
                            ->label('تعطیلی‌های مناسبتی')
                            ->schema([
                                DatePicker::make('date')
                                    ->label('تاریخ')
                                    ->jalali()
                                    ->native(false)
                                    ->required()
                                    ->rules(['date']),
                            ])
                            ->columns(1)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->addActionLabel('افزودن تاریخ تعطیل')
                            ->helperText('این تاریخ‌ها فقط در همان سال تعطیل هستند و در تمام ساعات ضریب شبانه دارند.'),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        if (! auth()->user()?->can(Permission::PricingEdit->value)) {
            Notification::make()->title('دسترسی غیرمجاز')->danger()->send();

            return;
        }

        $data = $this->form->getState();

        $booleans = ['tokeniko_product_prices_enabled', 'enable_dynamic_pricing', 'round_prices', 'show_discount', 'enable_sales_hours'];

        $weeklyHolidayDays = collect($data['weekly_holiday_days'] ?? [])
            ->filter(static fn (mixed $day): bool => (is_int($day) && $day >= 0 && $day <= 6)
                || (is_string($day) && preg_match('/^[0-6]$/D', $day) === 1))
            ->map(static fn (mixed $day): int => (int) $day)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $specialHolidayDates = collect($data['special_holiday_dates'] ?? [])
            ->pluck('date')
            ->filter(static fn (mixed $date): bool => is_string($date) && trim($date) !== '')
            ->map(static fn (string $date): string => Carbon::parse($date, config('app.timezone'))->toDateString())
            ->unique()
            ->sort()
            ->values()
            ->all();

        Setting::updateOrCreate(['key' => 'zioto_pricing_weekly_holiday_days'], [
            'value' => json_encode($weeklyHolidayDays),
            'type' => 'json',
            'category' => 'pricing',
            'label' => 'روزهای تعطیل هفتگی',
        ]);

        Setting::updateOrCreate(['key' => 'zioto_pricing_special_holiday_dates'], [
            'value' => json_encode($specialHolidayDates),
            'type' => 'json',
            'category' => 'pricing',
            'label' => 'تعطیلی‌های مناسبتی',
        ]);

        Setting::updateOrCreate(['key' => 'zioto_pricing_sales_enabled'], [
            'value' => ($data['sales_closed'] ?? false) ? 'false' : 'true',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'توقف فروش محصولات',
        ]);

        foreach ($booleans as $key) {
            Setting::updateOrCreate(['key' => "zioto_pricing_{$key}"], [
                'value' => ($data[$key] ?? false) ? 'true' : 'false',
                'type' => 'boolean',
                'category' => 'pricing',
                'label' => $key,
            ]);
        }

        $strings = ['sales_start_time', 'sales_end_time', 'rounding_mode'];
        foreach ($strings as $key) {
            Setting::updateOrCreate(['key' => "zioto_pricing_{$key}"], [
                'value' => (string) ($data[$key] ?? ''),
                'type' => 'string',
                'category' => 'pricing',
                'label' => $key,
            ]);
        }

        $ints = ['round_to', 'silver_round_to'];
        foreach ($ints as $key) {
            Setting::updateOrCreate(['key' => "zioto_pricing_{$key}"], [
                'value' => (string) (int) ($data[$key] ?? 0),
                'type' => 'integer',
                'category' => 'pricing',
                'label' => $key,
            ]);
        }

        Setting::updateOrCreate(['key' => 'tax_gold_labor'], [
            'value' => (string) (float) ($data['tax_gold_labor'] ?? 0),
            'type' => 'number',
            'category' => 'tax',
            'label' => 'درصد مالیات اجرت طلا',
        ]);

        Notification::make()
            ->title('تنظیمات فروش و قیمت گذاری ذخیره شد')
            ->success()
            ->send();
    }
}
