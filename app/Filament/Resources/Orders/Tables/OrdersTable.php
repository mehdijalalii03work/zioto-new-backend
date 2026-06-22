<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('شماره سفارش')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('مشتری')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'processing' => 'primary',
                        'shipped' => 'info',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'در انتظار بررسی',
                        'confirmed' => 'تایید شده',
                        'processing' => 'در حال پردازش',
                        'shipped' => 'ارسال شده',
                        'delivered' => 'تحویل شده',
                        'cancelled' => 'لغو شده',
                        default => $state,
                    }),

                TextColumn::make('total_amount')
                    ->label('مبلغ کل (تومان)')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('وضعیت پرداخت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'در انتظار پرداخت',
                        'paid' => 'پرداخت شده',
                        'failed' => 'ناموفق',
                        'refunded' => 'مسترد شده',
                        default => $state,
                    }),

                TextColumn::make('shipping.shipping_method_name')
                    ->label('روش ارسال')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('shipping.tracking_number')
                    ->label('رهگیری')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state) => $state ?: '—'),

                TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->defaultSort('created_at', 'desc')
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
