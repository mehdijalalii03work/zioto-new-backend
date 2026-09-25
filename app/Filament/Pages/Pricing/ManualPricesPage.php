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

class ManualPricesPage extends Page
{
    protected static ?string $slug = 'pricing/manual-prices';

    protected static ?string $title = 'قیمت‌های دستی';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'قیمت‌های دستی';

    protected static string|\UnitEnum|null $navigationGroup = 'قیمت‌گذاری زیوتو';

    protected static ?int $navigationSort = 4;

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
            'manual_silver9999_sell' => PricingSettings::manualSilver9999Sell(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('قیمت‌های دستی')
                    ->description('در صورت وجود، بر قیمت API اولویت دارند')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('manual_gold750_sell')->label('قیمت دستی فروش طلای ۷۵۰')->numeric()->nullable(),
                            TextInput::make('manual_silver9999_sell')->label('قیمت دستی فروش نقره ۹۹۹.۹')->numeric()->nullable(),
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

        foreach (['manual_gold750_sell', 'manual_silver9999_sell'] as $key) {
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
