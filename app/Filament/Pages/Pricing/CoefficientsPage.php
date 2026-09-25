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

    protected static string|\UnitEnum|null $navigationGroup = 'قیمت‌گذاری زیوتو';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.pricing.coefficients';

    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::PricingView->value) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'coef_gold750_to_gold995' => PricingSettings::coefficient('coef_gold750_to_gold995'),
            'coef_gold995_to_gold9999' => PricingSettings::coefficient('coef_gold995_to_gold9999'),
            'coef_buy_price' => PricingSettings::coefficient('coef_buy_price'),
            'coef_silver999_to_silver9999' => PricingSettings::coefficient('coef_silver999_to_silver9999'),
            'coef_silver9999_to_silver925_sell' => PricingSettings::coefficient('coef_silver9999_to_silver925_sell'),
            'coef_silver9999_to_silver925_buy' => PricingSettings::coefficient('coef_silver9999_to_silver925_buy'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ضرایب تابلو')
                    ->description('ضریب‌های تبدیل بین کلیدهای تابلو')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('coef_gold750_to_gold995')->label('۷۵۰ → ۹۹۵')->numeric(),
                            TextInput::make('coef_gold995_to_gold9999')->label('۹۹۵ → ۹۹۹.۹')->numeric(),
                            TextInput::make('coef_buy_price')->label('قیمت خرید')->numeric(),
                            TextInput::make('coef_silver999_to_silver9999')->label('نقره ۹۹۹ → ۹۹۹.۹')->numeric(),
                            TextInput::make('coef_silver9999_to_silver925_sell')->label('نقره فروش → ۹۲۵')->numeric(),
                            TextInput::make('coef_silver9999_to_silver925_buy')->label('نقره خرید → ۹۲۵')->numeric(),
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
            'coef_gold750_to_gold995',
            'coef_gold995_to_gold9999',
            'coef_buy_price',
            'coef_silver999_to_silver9999',
            'coef_silver9999_to_silver925_sell',
            'coef_silver9999_to_silver925_buy',
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
