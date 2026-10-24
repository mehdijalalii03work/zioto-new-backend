<?php

namespace App\Filament\Resources\DiscountCodes\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

class DiscountCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اطلاعات کد تخفیف')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('code')
                            ->label('کد')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(64)
                            ->dehydrateStateUsing(fn ($state): string => mb_strtoupper(trim((string) $state))),
                        TextInput::make('discount_amount')
                            ->label('مبلغ تخفیف (تومان)')
                            ->required()
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->formatStateUsing(fn ($state): string => number_format((int) ($state / 10)))
                            ->dehydrateStateUsing(fn ($state): int => (int) preg_replace('/[^\d]/', '', (string) $state) * 10),
                        Select::make('platforms')
                            ->label('فروشگاه‌های مجاز')
                            ->multiple()
                            ->required()
                            ->options(['main' => 'فروشگاه اصلی', 'nopay' => 'نوپی'])
                            ->default(['main', 'nopay']),
                        Toggle::make('is_active')
                            ->label('فعال')
                            ->default(true),
                    ]),
                ])
                ->columnSpanFull(),
            Section::make('زمان و محدودیت مصرف')
                ->schema([
                    Grid::make(2)->schema([
                        DateTimePicker::make('starts_at')
                            ->label('شروع اعتبار')
                            ->seconds(false)
                            ->native(false)
                            ->jalali()
                            ->nullable(),
                        DateTimePicker::make('ends_at')
                            ->label('پایان اعتبار')
                            ->seconds(false)
                            ->native(false)
                            ->jalali()
                            ->after('starts_at')
                            ->nullable(),
                        TextInput::make('max_uses')
                            ->label('حداکثر تعداد استفاده')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->nullable()
                            ->helperText('خالی بماند: بدون محدودیت'),
                        TextInput::make('max_uses_per_customer')
                            ->label('حداکثر استفاده برای هر مشتری')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->nullable()
                            ->helperText('خالی بماند: بدون محدودیت'),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }
}
