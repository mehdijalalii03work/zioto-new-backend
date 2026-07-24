<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'مشاهده سفارش';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Section::make('اطلاعات سفارش')
                            ->icon('heroicon-o-shopping-cart')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('order_number')
                                    ->label('شماره سفارش')
                                    ->badge(),

                                TextEntry::make('user.name')
                                    ->label('مشتری'),

                                TextEntry::make('status')
                                    ->label('وضعیت')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'confirmed' => 'info',
                                        'processing' => 'info',
                                        'shipped' => 'primary',
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

                                TextEntry::make('payment_method')
                                    ->label('روش پرداخت')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'online' => 'آنلاین',
                                        'installment' => 'قسطی',
                                        default => $state,
                                    }),

                                TextEntry::make('payment_status')
                                    ->label('وضعیت پرداخت')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'paid' => 'success',
                                        'failed' => 'danger',
                                        'refunded' => 'warning',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'pending' => 'در انتظار پرداخت',
                                        'paid' => 'پرداخت شده',
                                        'failed' => 'ناموفق',
                                        'refunded' => 'مسترد شده',
                                        default => $state,
                                    }),

                                TextEntry::make('total_amount')
                                    ->label('مبلغ کل')
                                    ->formatStateUsing(fn ($state): string => number_format($state).' ریال'),

                                TextEntry::make('created_at')
                                    ->label('تاریخ ثبت')
                                    ->dateTime('Y/m/d H:i'),

                                TextEntry::make('updated_at')
                                    ->label('آخرین بروزرسانی')
                                    ->dateTime('Y/m/d H:i'),
                            ]),

                        Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                Section::make('آدرس و ارسال')
                                    ->icon('heroicon-o-truck')
                                    ->schema([
                                        TextEntry::make('address.receiver_name')
                                            ->label('نام گیرنده')
                                            ->placeholder('—')
                                            ->visible(fn ($record): bool => filled($record->address?->receiver_name) && $record->address->receiver_name !== $record->user?->name),

                                        TextEntry::make('address.receiver_phone')
                                            ->label('تلفن گیرنده')
                                            ->placeholder('—')
                                            ->visible(fn ($record): bool => filled($record->address?->receiver_name) && $record->address->receiver_name !== $record->user?->name),

                                        TextEntry::make('address.full_address')
                                            ->label('آدرس کامل')
                                            ->placeholder('—')
                                            ->columnSpanFull(),

                                        TextEntry::make('address.receiver_national_code')
                                            ->label('کد ملی')
                                            ->placeholder('—')
                                            ->visible(fn ($record): bool => filled($record->address?->receiver_name) && $record->address->receiver_name !== $record->user?->name),

                                        TextEntry::make('address.postal_code')
                                            ->label('کد پستی')
                                            ->placeholder('—'),

                                        TextEntry::make('address.province.name')
                                            ->label('استان')
                                            ->placeholder('—'),

                                        TextEntry::make('shipping.shippingMethod.name')
                                            ->label('روش ارسال')
                                            ->placeholder('—'),

                                        TextEntry::make('shipping.shipping_cost')
                                            ->label('هزینه ارسال')
                                            ->formatStateUsing(fn ($state): string => $state ? number_format($state).' ریال' : '—')
                                            ->placeholder('—'),

                                        TextEntry::make('shipping.tracking_number')
                                            ->label('شماره رهگیری')
                                            ->placeholder('—'),

                                        TextEntry::make('shipping.tracking_url')
                                            ->label('لینک رهگیری')
                                            ->url(fn ($state): string => $state ?? '#')
                                            ->openUrlInNewTab()
                                            ->placeholder('—'),

                                        TextEntry::make('shipping.pickup_date')
                                            ->label('تاریخ مراجعه')
                                            ->placeholder('—'),

                                        TextEntry::make('shipping.delivered_at')
                                            ->label('تاریخ تحویل')
                                            ->placeholder('—'),
                                    ]),

                                Section::make('محصولات سفارش')
                                    ->icon('heroicon-o-cube')
                                    ->schema([
                                        RepeatableEntry::make('items')
                                            ->label('')
                                            ->schema([
                                                Grid::make(4)
                                                    ->schema([
                                                        TextEntry::make('product_name')
                                                            ->label('نام محصول'),

                                                        TextEntry::make('product_price')
                                                            ->label('قیمت واحد')
                                                            ->formatStateUsing(fn ($state): string => number_format($state).' ریال'),

                                                        TextEntry::make('quantity')
                                                            ->label('تعداد'),

                                                        TextEntry::make('subtotal')
                                                            ->label('قیمت کل')
                                                            ->formatStateUsing(fn ($state): string => number_format($state).' ریال'),
                                                    ]),
                                            ])
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('یادداشت‌ها و حسابفا')
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                                        TextEntry::make('notes')
                                            ->label('یادداشت‌ها')
                                            ->formatStateUsing(function ($state): HtmlString {
                                                if (! $state) {
                                                    return new HtmlString('<span class="text-muted-foreground">—</span>');
                                                }

                                                $data = json_decode($state, true);
                                                if (! is_array($data)) {
                                                    return new HtmlString('<span class="text-muted-foreground">'.e($state).'</span>');
                                                }

                                                $items = [];
                                                if (isset($data['name'])) {
                                                    $items[] = '<strong>نام:</strong> '.e($data['name']);
                                                }
                                                if (isset($data['phone'])) {
                                                    $items[] = '<strong>تلفن:</strong> '.e($data['phone']);
                                                }
                                                if (isset($data['national_code'])) {
                                                    $items[] = '<strong>کد ملی:</strong> '.e($data['national_code']);
                                                }
                                                if (isset($data['installment_fee'])) {
                                                    $items[] = '<strong>کارمزد اقساط:</strong> '.number_format($data['installment_fee']).' ریال';
                                                }

                                                return new HtmlString(implode('<br>', $items) ?: '<span class="text-muted-foreground">—</span>');
                                            })
                                            ->columnSpanFull(),

                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('hesabfa_contact_code')
                                                    ->label('کد مشتری')
                                                    ->placeholder('—'),

                                                TextEntry::make('hesabfa_invoice_number')
                                                    ->label('شماره فاکتور')
                                                    ->placeholder('—'),

                                                TextEntry::make('hesabfa_synced_at')
                                                    ->label('تاریخ همگام‌سازی')
                                                    ->placeholder('—'),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('ویرایش')
                ->icon('heroicon-o-pencil')
                ->url(fn ($record): string => $record ? $this->getResource()::getUrl('edit', ['record' => $record]) : '#'),
        ];
    }
}
