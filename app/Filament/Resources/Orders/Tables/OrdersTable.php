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
                    ->sortable()
                    ->state(function ($record): string {
                        if ($record->user?->name) {
                            return $record->user->name;
                        }

                        if ($record->platform === 'tapsi') {
                            return 'مشتری تپسی';
                        }

                        return '—';
                    }),

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
                    ->label('مبلغ کل (ریال)')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => number_format($state)),

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

                TextColumn::make('platform')
                    ->label('پلتفرم')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'main' => 'info',
                        'nopay' => 'success',
                        'tapsi' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'main' => 'زیوتو',
                        'nopay' => 'نوپی',
                        'tapsi' => 'تپسی شاپ',
                        default => $state ?? '—',
                    })
                    ->toggleable(),

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
                    ->state(function ($record): ?string {
                        if ($record->platform === 'tapsi') {
                            return 'tapsi';
                        }

                        return $record->payments->first()?->gateway;
                    })
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'parsian' => 'پارسیان',
                        'digipay' => 'دیجی‌پی',
                        'kamanlend' => 'کمان‌لند',
                        'smartis' => ' اسمارتیس',
                        'nopay' => 'نوپی',
                        'tapsi' => 'تپسی شاپ',
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

                TextColumn::make('tapsi_order_id')
                    ->label('شناسه تپسی')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state) => $state ?: '—'),

                TextColumn::make('tapsi_shipment_bundle')
                    ->label('مرسوله تپسی')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state) => $state ?: '—'),

                TextColumn::make('tapsi_delivery_method')
                    ->label('روش ارسال تپسی')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'vendor' => 'info',
                        'platform' => 'primary',
                        'pickup' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'vendor' => 'فروشنده',
                        'platform' => 'پلتفرم',
                        'pickup' => 'حضوری',
                        default => '—',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime()
                    ->jalaliDateTime('H:i Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->label('پلتفرم')
                    ->placeholder('همه')
                    ->options([
                        'main' => 'زیوتو',
                        'nopay' => 'نوپی',
                        'tapsi' => 'تپسی شاپ',
                    ]),
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
                SelectFilter::make('tapsi_orders')
                    ->label('فقط سفارشات تپسی')
                    ->placeholder('همه')
                    ->options([
                        'pending' => 'تپسی - در انتظار بررسی',
                        'all' => 'همه سفارشات تپسی',
                    ])
                    ->query(function ($query, array $data) {
                        if (blank($data['value'])) {
                            return $query;
                        }

                        if ($data['value'] === 'pending') {
                            return $query->where('platform', 'tapsi')->where('status', 'pending');
                        }

                        return $query->where('platform', 'tapsi');
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
                        ->authorize('order.view')
                        ->action(fn ($records) => app(OrderExcelExport::class)->export($records))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('change_status')
                        ->label('تغییر وضعیت')
                        ->icon('heroicon-o-arrow-path')
                        ->authorize('order.edit')
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
