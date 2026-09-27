<?php

namespace App\Filament\Pages\Pricing;

use App\Enums\Permission;
use App\Models\Setting;
use App\Services\Pricing\LaborCalculator;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LaborSettingsPage extends Page
{
    protected static ?string $slug = 'pricing/labor';

    protected static ?string $title = 'نقش‌ها و بازه‌های زمانی';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'تنظیمات اجرت';

    protected static string|\UnitEnum|null $navigationGroup = 'قیمت‌گذاری زیوتو';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.pricing.labor-settings';

    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::PricingView->value) ?? false;
    }

    public function mount(): void
    {
        $labor = app(LaborCalculator::class);

        $this->form->fill([
            'labor_roles' => $labor->getRoles(),
            'time_periods' => $labor->getTimePeriods(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('نقش‌های اجرت مشتریان')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Repeater::make('labor_roles')
                            ->schema([
                                TextInput::make('slug')
                                    ->label('شناسه')
                                    ->required()
                                    ->maxLength(50),
                                TextInput::make('name')
                                    ->label('نام')
                                    ->required()
                                    ->maxLength(100),
                                Toggle::make('is_default')
                                    ->label('پیش‌فرض')
                                    ->inline(false),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->collapsible(),
                    ])->columnSpanFull(),

                Section::make('بازه‌های زمانی')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Repeater::make('time_periods')
                            ->schema([
                                TextInput::make('slug')
                                    ->label('شناسه')
                                    ->required()
                                    ->maxLength(50),
                                TextInput::make('name')
                                    ->label('نام')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('start')
                                    ->label('شروع')
                                    ->placeholder('11:00')
                                    ->required(),
                                TextInput::make('end')
                                    ->label('پایان')
                                    ->placeholder('18:00')
                                    ->required(),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->collapsible(),
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

        $roles = array_values(array_map(static fn (array $role): array => [
            'slug' => (string) $role['slug'],
            'name' => (string) $role['name'],
            'is_default' => (bool) ($role['is_default'] ?? false),
        ], $data['labor_roles'] ?? []));

        $periods = array_values(array_map(static fn (array $period): array => [
            'slug' => (string) $period['slug'],
            'name' => (string) $period['name'],
            'start' => (string) $period['start'],
            'end' => (string) $period['end'],
        ], $data['time_periods'] ?? []));

        Setting::updateOrCreate(['key' => 'zioto_pricing_labor_roles'], [
            'value' => json_encode($roles),
            'type' => 'json',
            'category' => 'pricing',
            'label' => 'نقش‌های اجرت',
        ]);

        Setting::updateOrCreate(['key' => 'zioto_pricing_time_periods'], [
            'value' => json_encode($periods),
            'type' => 'json',
            'category' => 'pricing',
            'label' => 'بازه‌های زمانی',
        ]);

        Notification::make()
            ->title('نقش‌ها و بازه‌ها ذخیره شد')
            ->success()
            ->send();
    }
}
