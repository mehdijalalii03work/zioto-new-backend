<?php

namespace App\Filament\Resources\ShippingMethods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ShippingMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('code')
                    ->label('کد')
                    ->searchable()
                    ->copyable(),

                ToggleColumn::make('is_active')
                    ->label('فعال'),

                TextColumn::make('rates_count')
                    ->label('تعداد تعرفه')
                    ->counts('rates'),
            ])
            ->filters([])
            ->defaultPaginationPageOption(25)
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make()
                    ->label('ویرایش'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('حذف انتخاب شده‌ها'),
                ]),
            ]);
    }
}
