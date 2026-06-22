<?php

namespace App\Filament\Resources\ShippingMethods\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ShippingMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اطلاعات روش ارسال')
                    ->icon('heroicon-o-truck')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('name')
                                    ->label('نام')
                                    ->required()
                                    ->maxLength(100),

                                TextInput::make('code')
                                    ->label('کد')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50),

                                Select::make('icon')
                                    ->label('آیکون')
                                    ->searchable()
                                    ->live()
                                    ->prefixIcon(fn ($state): ?Heroicon => filled($state) ? Heroicon::tryFrom($state) : null)
                                    ->options([
                                        'heroicon-o-truck' => 'تیپاکس',
                                        'heroicon-o-bicycle' => 'پیک شهری',
                                        'heroicon-o-building-storefront' => 'تحویل حضوری',
                                        'heroicon-o-envelope' => 'پست',
                                        'heroicon-o-rocket-launch' => 'سریع',
                                        'heroicon-o-cube' => 'بسته',
                                        'heroicon-o-globe-alt' => 'سراسری',
                                        'heroicon-o-map-pin' => 'مکان',
                                        'heroicon-o-hand' => 'حضوری',
                                        'heroicon-o-shopping-bag' => 'سبد خرید',
                                        'heroicon-o-arrow-path' => 'بازگشت',
                                        'heroicon-o-check-badge' => 'تضمین شده',
                                        'heroicon-o-shield-check' => 'بیمه',
                                        'heroicon-o-sparkles' => 'ویژه',
                                    ]),
                            ]),

                        Textarea::make('description')
                            ->label('توضیحات')
                            ->rows(3),

                        Grid::make(3)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('فعال')
                                    ->default(true),

                                Toggle::make('is_pickup')
                                    ->label('حضوری')
                                    ->default(false),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('تعرفه‌ها')
                    ->description('هزینه‌های ارسال بر اساس نوع تعرفه')
                    ->icon('heroicon-o-currency-dollar')
                    ->collapsible()
                    ->schema([
                        Repeater::make('rates')
                            ->label('تعرفه‌ها')
                            ->relationship()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('rate_type')
                                            ->label('نوع تعرفه')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->options([
                                                'flat' => 'ثابت',
                                                'province' => 'استانی',
                                                'city' => 'شهری',
                                                'weight' => 'وزنی',
                                                'cart_total' => 'مبلغ سبد',
                                            ]),

                                        TextInput::make('base_rate')
                                            ->label('هزینه پایه (ریال)')
                                            ->required()
                                            ->numeric()
                                            ->live(onBlur: true),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('per_kg_rate')
                                            ->label('هزینه اضافه هر کیلوگرم (ریال)')
                                            ->numeric()
                                            ->nullable(),

                                        TextInput::make('free_shipping_min')
                                            ->label('آستانه ارسال رایگان (ریال)')
                                            ->numeric()
                                            ->nullable(),

                                        TextInput::make('tax_rate')
                                            ->label('درصد مالیات')
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->placeholder('مثلاً ۹')
                                            ->nullable(),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        Select::make('province_id')
                                            ->label('استان')
                                            ->relationship('province', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),

                                        Select::make('city_id')
                                            ->label('شهر')
                                            ->relationship('city', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('min_weight')
                                            ->label('حداقل وزن (گرم)')
                                            ->numeric()
                                            ->nullable(),

                                        TextInput::make('max_weight')
                                            ->label('حداکثر وزن (گرم)')
                                            ->numeric()
                                            ->nullable(),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('min_cart_total')
                                            ->label('حداقل مبلغ سبد (ریال)')
                                            ->numeric()
                                            ->nullable(),

                                        TextInput::make('max_cart_total')
                                            ->label('حداکثر مبلغ سبد (ریال)')
                                            ->numeric()
                                            ->nullable(),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('estimated_days_min')
                                            ->label('حداقل روز تخمینی تحویل')
                                            ->numeric()
                                            ->nullable(),

                                        TextInput::make('estimated_days_max')
                                            ->label('حداکثر روز تخمینی تحویل')
                                            ->numeric()
                                            ->nullable(),
                                    ]),
                            ])
                            ->cloneable()
                            ->itemLabel(fn (array $state): string => [
                                'flat' => 'ثابت',
                                'province' => 'استانی',
                                'city' => 'شهری',
                                'weight' => 'وزنی',
                                'cart_total' => 'مبلغ سبد',
                            ][$state['rate_type'] ?? ''] ?? ''
                                .' — '
                                .number_format((int) ($state['base_rate'] ?? 0))
                                .' ریال')
                            ->addActionLabel('افزودن تعرفه')
                            ->defaultItems(0)
                            ->collapsible(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
