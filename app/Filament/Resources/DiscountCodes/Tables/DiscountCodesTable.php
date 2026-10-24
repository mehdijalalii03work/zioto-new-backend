<?php

namespace App\Filament\Resources\DiscountCodes\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
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
                    ->copyable()
                    ->copyMessage('کد تخفیف کپی شد')
                    ->copyMessageDuration(1500),
                TextColumn::make('discount_amount')->label('تخفیف (تومان)')->formatStateUsing(fn ($state): string => number_format((int) $state / 10)),
                TextColumn::make('usages_count')->label('مصرف‌شده')->counts('usages'),
                TextColumn::make('max_uses')->label('سقف مصرف')->placeholder('بدون محدودیت'),
                TextColumn::make('max_uses_per_customer')->label('سقف هر مشتری')->placeholder('بدون محدودیت'),
                TextColumn::make('starts_at')->label('شروع')->jalaliDateTime('Y/m/d H:i')->placeholder('همیشه'),
                TextColumn::make('ends_at')->label('پایان')->jalaliDateTime('Y/m/d H:i')->placeholder('همیشه'),
                ToggleColumn::make('is_active')->label('فعال'),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->recordActions([EditAction::make()->label('ویرایش')]);
    }
}
