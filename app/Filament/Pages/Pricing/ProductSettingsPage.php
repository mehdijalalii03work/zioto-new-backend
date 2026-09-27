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

class ProductSettingsPage extends Page
{
    protected static ?string $slug = 'pricing/product-settings';

    protected static ?string $title = 'تنظیمات محصول';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'تنظیمات محصول';

    protected static string|\UnitEnum|null $navigationGroup = 'قیمت‌گذاری زیوتو';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.pricing.product-settings';

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
            'show_discount' => PricingSettings::showDiscountBadge(),
            'enable_sales_hours' => PricingSettings::enableSalesHours(),
            'sales_start_time' => PricingSettings::salesStartTime(),
            'sales_end_time' => PricingSettings::salesEndTime(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('قیمت‌گذاری محصول')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('enable_dynamic_pricing')->label('قیمت‌گذاری پویا')->inline(false),
                            Toggle::make('round_prices')->label('گردکردن قیمت محصول')->inline(false),
                            TextInput::make('round_to')->label('مضرب گردکردن')->numeric()->default(1000),
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

        $booleans = ['enable_dynamic_pricing', 'round_prices', 'show_discount', 'enable_sales_hours'];

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

        $ints = ['round_to'];
        foreach ($ints as $key) {
            Setting::updateOrCreate(['key' => "zioto_pricing_{$key}"], [
                'value' => (string) (int) ($data[$key] ?? 0),
                'type' => 'integer',
                'category' => 'pricing',
                'label' => $key,
            ]);
        }

        Notification::make()
            ->title('تنظیمات محصول ذخیره شد')
            ->success()
            ->send();
    }
}
