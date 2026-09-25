<?php

namespace App\Filament\Resources\DiscountCodes\Schemas;

use App\Models\DiscountCode;
use App\Models\User;
use App\Services\Pricing\DynamicPriceService;
use App\Services\Pricing\LaborCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DiscountCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        $labor = app(LaborCalculator::class);
        $roleOptions = collect($labor->getRoles())->mapWithKeys(fn (array $r) => [$r['slug'] => $r['name']]);

        return $schema
            ->components([
                Section::make('اطلاعات کد تخفیف')
                    ->icon('heroicon-o-ticket')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('code')
                                ->label('کد')
                                ->required()
                                ->maxLength(100)
                                ->unique(ignoreRecord: true)
                                ->toUpperCase(),

                            Select::make('type')
                                ->label('نوع تخفیف')
                                ->options(DiscountCode::TYPES)
                                ->required()
                                ->default('total_percent'),

                            TextInput::make('value')
                                ->label('مقدار')
                                ->required()
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01),

                            Toggle::make('is_active')
                                ->label('فعال')
                                ->default(true)
                                ->inline(false),

                            TextInput::make('min_order_amount')
                                ->label('حداقل مبلغ سفارش')
                                ->numeric()
                                ->minValue(0)
                                ->nullable(),

                            TextInput::make('usage_limit')
                                ->label('سقف استفاده (۰ = نامحدود)')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),

                            DatePicker::make('expiry_date')
                                ->label('تاریخ انقضا')
                                ->native(false)
                                ->nullable(),
                        ]),
                    ])->columnSpanFull(),

                Section::make('محدودیت‌ها')
                    ->icon('heroicon-o-lock-closed')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('is_user_specific')
                                ->label('مخصوص کاربران خاص')
                                ->inline(false)
                                ->live(),

                            Select::make('allowed_users')
                                ->label('کاربران مجاز')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->options(fn () => User::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->visible(fn ($get): bool => (bool) $get('is_user_specific')),

                            Select::make('allowed_labor_roles')
                                ->label('نقش‌های لابور مجاز')
                                ->multiple()
                                ->options($roleOptions->toArray()),

                            Select::make('allowed_metal_types')
                                ->label('انواع فلز مجاز')
                                ->multiple()
                                ->options(DynamicPriceService::METAL_OPTIONS),
                        ]),
                    ])->columnSpanFull(),

                Section::make('وضعیت')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('usage_count')
                                ->label('تعداد استفاده')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(),
                        ]),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }
}
