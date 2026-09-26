<?php

namespace App\Filament\Pages\Pricing;

use App\Enums\Permission;
use App\Models\Setting;
use App\Services\Pricing\PricingSettings;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PricingSettingsPage extends Page
{
    protected static ?string $slug = 'pricing/settings';

    protected static ?string $title = 'تنظیمات';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'تنظیمات';

    protected static string|\UnitEnum|null $navigationGroup = 'تابلو قیمت زیوتو';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.pricing.pricing-settings';

    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::PricingView->value) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'enable_dynamic_pricing' => PricingSettings::enableDynamicPricing(),
            'round_prices' => PricingSettings::roundPrices(),
            'round_to' => PricingSettings::roundTo(),
            'trend_threshold' => PricingSettings::trendThreshold(),
            'cache_duration_seconds' => PricingSettings::cacheDurationSeconds(),
            'stale_max_age_seconds' => PricingSettings::staleMaxAgeSeconds(),
            'stale_block' => PricingSettings::staleBlock(),
            'show_discount' => PricingSettings::showDiscountBadge(),
            'enable_sales_hours' => PricingSettings::enableSalesHours(),
            'sales_start_time' => PricingSettings::salesStartTime(),
            'sales_end_time' => PricingSettings::salesEndTime(),
            'persian_api_enabled' => (bool) PricingSettings::get('persian_api_enabled', true),
            'tala_api_enabled' => (bool) PricingSettings::get('tala_api_enabled', true),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ویژگی‌ها')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('enable_dynamic_pricing')->label('قیمت‌گذاری پویا')->inline(false),
                            Toggle::make('round_prices')->label('گردکردن قیمت')->inline(false),
                            TextInput::make('round_to')->label('مضرب گردکردن')->numeric()->default(1000),
                            TextInput::make('trend_threshold')->label('آستانه روند (تومان)')->numeric(),
                            TextInput::make('cache_duration_seconds')->label('مدت کش (ثانیه)')->numeric(),
                            TextInput::make('stale_max_age_seconds')->label('حداکثر سن داده (ثانیه)')->numeric(),
                            Toggle::make('stale_block')->label('مسدودسازی داده کهنه')->inline(false),
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

                Section::make('منابع قیمت')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('persian_api_enabled')->label('PersianAPI')->inline(false),
                            Toggle::make('tala_api_enabled')->label('Tala.ir')->inline(false),
                        ]),
                    ])->columnSpanFull(),
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

        $booleans = [
            'enable_dynamic_pricing',
            'round_prices',
            'stale_block',
            'show_discount',
            'enable_sales_hours',
            'persian_api_enabled',
            'tala_api_enabled',
        ];

        foreach ($booleans as $key) {
            Setting::updateOrCreate(['key' => "zioto_pricing_{$key}"], [
                'value' => ($data[$key] ?? false) ? 'true' : 'false',
                'type' => 'boolean',
                'category' => 'pricing',
                'label' => $key,
            ]);
        }

        $strings = ['sales_start_time', 'sales_end_time'];
        foreach ($strings as $key) {
            Setting::updateOrCreate(['key' => "zioto_pricing_{$key}"], [
                'value' => (string) ($data[$key] ?? ''),
                'type' => 'string',
                'category' => 'pricing',
                'label' => $key,
            ]);
        }

        $ints = ['round_to', 'trend_threshold', 'cache_duration_seconds', 'stale_max_age_seconds'];
        foreach ($ints as $key) {
            Setting::updateOrCreate(['key' => "zioto_pricing_{$key}"], [
                'value' => (string) (int) ($data[$key] ?? 0),
                'type' => 'integer',
                'category' => 'pricing',
                'label' => $key,
            ]);
        }

        PricingSettings::invalidateBoardCache();

        Notification::make()
            ->title('تنظیمات قیمت‌گذاری ذخیره شد')
            ->success()
            ->send();
    }
}
