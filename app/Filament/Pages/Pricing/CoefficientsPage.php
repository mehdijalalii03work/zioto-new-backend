<?php

namespace App\Filament\Pages\Pricing;

use App\Enums\Permission;
use App\Models\Setting;
use App\Services\Pricing\PricingSettings;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CoefficientsPage extends Page
{
    protected static ?string $slug = 'pricing/coefficients';

    protected static ?string $title = 'ضرایب';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'ضرایب';

    protected static string|\UnitEnum|null $navigationGroup = 'تابلو قیمت زیوتو';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.pricing.coefficients';

    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::PricingView->value) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'coef_gold750_sell_ratio' => PricingSettings::coefficient('coef_gold750_sell_ratio'),
            'coef_gold750_buy_ratio' => PricingSettings::coefficient('coef_gold750_buy_ratio'),
            'coef_gold999_sell_ratio' => PricingSettings::coefficient('coef_gold999_sell_ratio'),
            'coef_gold999_buy_ratio' => PricingSettings::coefficient('coef_gold999_buy_ratio'),
            'coef_gold999_to_gold995' => PricingSettings::coefficient('coef_gold999_to_gold995'),
            'coef_gold995_sell_ratio' => PricingSettings::coefficient('coef_gold995_sell_ratio'),
            'coef_gold995_buy_ratio' => PricingSettings::coefficient('coef_gold995_buy_ratio'),
            'coef_gold999_to_gold9999' => PricingSettings::coefficient('coef_gold999_to_gold9999'),
            'coef_gold9999_sell_ratio' => PricingSettings::coefficient('coef_gold9999_sell_ratio'),
            'coef_gold9999_buy_ratio' => PricingSettings::coefficient('coef_gold9999_buy_ratio'),
            'coef_silver9999_to_silver999' => PricingSettings::coefficient('coef_silver9999_to_silver999'),
            'coef_silver_buy_price' => PricingSettings::coefficient('coef_silver_buy_price'),
            'coef_buy_price' => PricingSettings::coefficient('coef_buy_price'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $ratio = fn (string $name, string $label, string $helper): TextInput => TextInput::make($name)
            ->label($label)
            ->helperText($helper)
            ->numeric()
            ->step(0.0001);

        return $schema
            ->components([
                Section::make('طلای ۷۵۰ (۱۸ عیار)')
                    ->description('فروش = Max API × ضریب فروش؛ خرید = Min API × ضریب خرید')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Grid::make(2)->schema([
                            $ratio('coef_gold750_sell_ratio', 'ضریب فروش (HighValueRatio)', 'ضریب اعمال شده روی Max API برای محاسبه قیمت فروش ۷۵۰'),
                            $ratio('coef_gold750_buy_ratio', 'ضریب خرید (LowValueRatio)', 'ضریب اعمال شده روی Min API برای محاسبه قیمت خرید ۷۵۰'),
                        ]),
                    ])->columnSpanFull(),

                Section::make('طلای ۹۹۹ (۲۴ عیار)')
                    ->description('فروش = Max API × ضریب فروش؛ خرید = Min API × ضریب خرید')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Grid::make(2)->schema([
                            $ratio('coef_gold999_sell_ratio', 'ضریب فروش (HighValueRatio)', 'ضریب اعمال شده روی Max API برای محاسبه قیمت فروش ۹۹۹'),
                            $ratio('coef_gold999_buy_ratio', 'ضریب خرید (LowValueRatio)', 'ضریب اعمال شده روی Min API برای محاسبه قیمت خرید ۹۹۹'),
                        ]),
                    ])->columnSpanFull(),

                Section::make('طلای ۹۹۵')
                    ->description('از طلای ۹۹۹ ساخته می‌شود: فروش × ضریب تبدیل × HighValueRatio')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('coef_gold999_to_gold995')->label('ضریب تبدیل ۹۹۹ ← ۹۹۵')->numeric(),
                            $ratio('coef_gold995_sell_ratio', 'ضریب فروش (HighValueRatio)', ''),
                            $ratio('coef_gold995_buy_ratio', 'ضریب خرید (LowValueRatio)', ''),
                        ]),
                    ])->columnSpanFull(),

                Section::make('طلای ۹۹۹.۹')
                    ->description('از طلای ۹۹۹ ساخته می‌شود: فروش × ضریب تبدیل × HighValueRatio')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('coef_gold999_to_gold9999')->label('ضریب تبدیل ۹۹۹ ← ۹۹۹.۹')->numeric(),
                            $ratio('coef_gold9999_sell_ratio', 'ضریب فروش (HighValueRatio)', ''),
                            $ratio('coef_gold9999_buy_ratio', 'ضریب خرید (LowValueRatio)', ''),
                        ]),
                    ])->columnSpanFull(),

                Section::make('نقره')
                    ->description('قیمت نقره ۹۹۹.۹ دستی وارد می‌شود؛ نقره ۹۹۹ از آن ساخته می‌شود')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('coef_silver9999_to_silver999')->label('ضریب تبدیل ۹۹۹.۹ ← ۹۹۹')->numeric(),
                            TextInput::make('coef_silver_buy_price')->label('ضریب خرید نقره نسبت به فروش')->numeric(),
                        ]),
                    ])->columnSpanFull(),

                Section::make('ضریب خرید طلا (حالت خاص)')
                    ->description('فقط وقتی استفاده می‌شود که دو منبع برابر باشند یا قیمت دستی برنده شود')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('coef_buy_price')->label('ضریب خرید نسبت به فروش (طلا)')->numeric(),
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

        $keys = [
            'coef_gold750_sell_ratio',
            'coef_gold750_buy_ratio',
            'coef_gold999_sell_ratio',
            'coef_gold999_buy_ratio',
            'coef_gold999_to_gold995',
            'coef_gold995_sell_ratio',
            'coef_gold995_buy_ratio',
            'coef_gold999_to_gold9999',
            'coef_gold9999_sell_ratio',
            'coef_gold9999_buy_ratio',
            'coef_silver9999_to_silver999',
            'coef_silver_buy_price',
            'coef_buy_price',
        ];

        foreach ($keys as $key) {
            Setting::updateOrCreate(['key' => "zioto_pricing_{$key}"], [
                'value' => (string) ($data[$key] ?? ''),
                'type' => 'number',
                'category' => 'pricing',
                'label' => $key,
            ]);
        }

        PricingSettings::invalidateBoardCache();

        Notification::make()
            ->title('ضرایب ذخیره شد')
            ->success()
            ->send();
    }
}
