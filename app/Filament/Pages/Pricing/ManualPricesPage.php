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
use Filament\Support\RawJs;

class ManualPricesPage extends Page
{
    protected static ?string $slug = 'pricing/manual-prices';

    protected static ?string $title = 'قیمت‌های دستی';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'قیمت‌های دستی';

    protected static string|\UnitEnum|null $navigationGroup = 'تابلو قیمت زیوتو';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.pricing.manual-prices';

    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::PricingView->value) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'manual_gold750_sell' => PricingSettings::manualGold750Sell(),
            'manual_gold999_sell' => PricingSettings::manualGold999Sell(),
            'manual_silver9999_sell' => PricingSettings::manualSilver9999Sell(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('قیمت‌های دستی')
                    ->description('مقادیر به تومان به ازای هر گرم وارد شوند. قیمت دستی در صورت ورود، همیشه بر API اولویت دارد. قیمت دستی نقره ۹۹۹.۹ تنها منبع قیمت نقره است.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Grid::make(2)->schema([
                            $this->moneyInput('manual_gold750_sell', 'قیمت دستی فروش طلای ۷۵۰'),
                            $this->moneyInput('manual_gold999_sell', 'قیمت دستی فروش طلای ۹۹۹'),
                            $this->moneyInput('manual_silver9999_sell', 'قیمت دستی فروش نقره ۹۹۹.۹'),
                        ]),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /**
     * Money field with thousands separators while typing; the raw digits are
     * what gets validated, dehydrated and stored.
     */
    private function moneyInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->suffix('تومان')
            ->mask(RawJs::make('$money($input)'))
            ->stripCharacters(',')
            ->dehydrateStateUsing(fn (mixed $state): mixed => is_string($state) ? str_replace(',', '', $state) : $state)
            ->numeric()
            ->nullable();
    }

    public function save(): void
    {
        if (! auth()->user()?->can(Permission::PricingEdit->value)) {
            Notification::make()->title('دسترسی غیرمجاز')->danger()->send();

            return;
        }

        $data = $this->form->getState();

        foreach (['manual_gold750_sell', 'manual_gold999_sell', 'manual_silver9999_sell'] as $key) {
            Setting::updateOrCreate(['key' => "zioto_pricing_{$key}"], [
                'value' => (string) ($data[$key] ?? ''),
                'type' => 'number',
                'category' => 'pricing',
                'label' => $key,
            ]);
        }

        PricingSettings::invalidateBoardCache();

        Notification::make()
            ->title('قیمت‌های دستی ذخیره شد')
            ->success()
            ->send();
    }
}
