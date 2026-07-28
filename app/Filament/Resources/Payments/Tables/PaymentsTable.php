<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('شناسه')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('کاربر')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn ($record): string => $record->user?->name ?? $record->order?->user?->name ?? '—'),

                TextColumn::make('order.order_number')
                    ->label('شماره سفارش')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ?: '—'),

                TextColumn::make('amount')
                    ->label('مبلغ (تومان)')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => number_format($state / 10)),

                TextColumn::make('payment_method')
                    ->label('روش پرداخت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'online' => 'info',
                        'card_to_card' => 'warning',
                        'installment' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'online' => 'آنلاین',
                        'card_to_card' => 'کارت به کارت',
                        'installment' => 'اقساطی',
                        default => $state,
                    }),

                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'successful' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'در انتظار',
                        'successful' => 'موفق',
                        'paid' => 'پرداخت شده',
                        'failed' => 'ناموفق',
                        'refunded' => 'مسترد شده',
                        default => $state,
                    }),

                TextColumn::make('gateway')
                    ->label('درگاه')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('paid_at')
                    ->label('تاریخ پرداخت')
                    ->dateTime()
                    ->jalaliDateTime('H:i Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime()
                    ->jalaliDateTime('H:i Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'pending' => 'در انتظار',
                        'successful' => 'موفق',
                        'failed' => 'ناموفق',
                        'refunded' => 'مسترد شده',
                    ]),

                SelectFilter::make('payment_method')
                    ->label('روش پرداخت')
                    ->options([
                        'online' => 'آنلاین',
                        'card_to_card' => 'کارت به کارت',
                        'installment' => 'اقساطی',
                    ]),

                TrashedFilter::make(),
            ])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->label('ویرایش'),

                RestoreAction::make()
                    ->label('بازیابی'),

                ForceDeleteAction::make()
                    ->label('حذف دائمی'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('حذف انتخاب شده‌ها'),

                    RestoreBulkAction::make()
                        ->label('بازیابی انتخاب شده‌ها'),

                    ForceDeleteBulkAction::make()
                        ->label('حذف دائمی انتخاب شده‌ها'),
                ]),
            ]);
    }
}
