<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\Exports\OrderExcelExport;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
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
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => str_pad($state, 5, '0', STR_PAD_LEFT)),

                TextColumn::make('user.name')
                    ->label('مشتری')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('address.receiver_name')
                    ->label('تحویل گیرنده')
                    ->state(function ($record): string {
                        $receiverName = $record->address?->receiver_name;
                        $buyerName = $record->user?->name;

                        if ($receiverName && $receiverName !== $buyerName) {
                            return $receiverName;
                        }

                        return '—';
                    }),

                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'در انتظار بررسی',
                        'confirmed' => 'تایید شده',
                        'completed' => 'تکمیل شده',
                        'cancelled' => 'لغو شده',
                        default => $state,
                    }),

                TextColumn::make('total_amount')
                    ->label('مبلغ کل (تومان)')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => number_format($state / 10)),

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

                TextColumn::make('payments.gateway')
                    ->label('درگاه')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'parsian' => 'info',
                        'digipay' => 'primary',
                        'kamanlend' => 'warning',
                        'smartis' => 'success',
                        'nopay' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'parsian' => 'پارسیان',
                        'digipay' => 'دیجی‌پی',
                        'kamanlend' => 'کمان‌لند',
                        'smartis' => ' اسمارتیس',
                        'nopay' => 'نوپی',
                        default => $state ?? '—',
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

                TextColumn::make('hesabfa_invoice_number')
                    ->label('حسابفا')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ? "فاکتور #{$state}" : 'ارسال نشده')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime()
                    ->jalaliDateTime('H:i Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('gateway')
                    ->label('درگاه پرداخت')
                    ->placeholder('همه')
                    ->options([
                        'parsian' => 'پارسیان',
                        'digipay' => 'دیجی‌پی',
                        'kamanlend' => 'کمان‌لند',
                        'smartis' => 'اسمارتیس',
                        'nopay' => 'نوپی',
                    ])
                    ->query(function ($query, array $data) {
                        if (blank($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('payments', fn ($q) => $q->where('gateway', $data['value']));
                    }),
                TrashedFilter::make(),
            ])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->label('مشاهده'),

                EditAction::make()
                    ->label('ویرایش'),

                RestoreAction::make()
                    ->label('بازیابی'),

                ForceDeleteAction::make()
                    ->label('حذف دائمی'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export_excel')
                        ->label('دانلود خروجی اکسل')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn ($records) => app(OrderExcelExport::class)->export($records))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('change_status')
                        ->label('تغییر وضعیت')
                        ->icon('heroicon-o-arrow-path')
                        ->modalHeading('تغییر وضعیت سفارش‌ها')
                        ->modalDescription('وضعیت جدید را برای سفارش‌های انتخاب شده مشخص کنید.')
                        ->form([
                            Select::make('status')
                                ->label('وضعیت جدید')
                                ->options([
                                    'pending' => 'در انتظار بررسی',
                                    'confirmed' => 'تایید شده',
                                    'completed' => 'تکمیل شده',
                                    'cancelled' => 'لغو شده',
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(fn ($record) => $record->update(['status' => $data['status']]));

                            Notification::make()
                                ->title('وضعیت سفارش‌ها با موفقیت تغییر یافت')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation(),

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
