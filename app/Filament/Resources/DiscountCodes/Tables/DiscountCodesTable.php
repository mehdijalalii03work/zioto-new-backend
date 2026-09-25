<?php

namespace App\Filament\Resources\DiscountCodes\Tables;

use App\Models\DiscountCode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DiscountCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('کد')
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('type')
                    ->label('نوع')
                    ->formatStateUsing(fn ($state) => DiscountCode::TYPES[$state] ?? $state),

                TextColumn::make('value')
                    ->label('مقدار')
                    ->formatStateUsing(fn ($state, $record) => $record->type === 'fixed_amount'
                        ? number_format((float) $state)
                        : number_format((float) $state, 2).'%'),

                TextColumn::make('usage_count')
                    ->label('استفاده')
                    ->formatStateUsing(fn ($state, $record) => $record->usage_limit > 0
                        ? "{$state}/{$record->usage_limit}"
                        : (string) $state),

                TextColumn::make('expiry_date')
                    ->label('انقضا')
                    ->date('Y/m/d')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('is_active')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'فعال' : 'غیرفعال')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),

                TextColumn::make('created_at')
                    ->label('ایجاد')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('نوع')
                    ->options(DiscountCode::TYPES),

                SelectFilter::make('is_active')
                    ->label('وضعیت')
                    ->options([
                        1 => 'فعال',
                        0 => 'غیرفعال',
                    ]),
            ])
            ->defaultPaginationPageOption(25)
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
