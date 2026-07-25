<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Schemas\Components\Fieldset;
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
                Grid::make(4)
                    ->columnSpanFull()
                    ->schema([
                        // ==========================================
                        // بخش اصلی (ستون سمت چپ - ۳ ستون از ۴)
                        // ==========================================
                        Section::make('اطلاعات سفارش')
                            ->icon('heroicon-o-shopping-cart')
                            ->columnSpan(3)
                            ->schema([
                                // ---------- اطلاعات پایه ----------
                                Fieldset::make('اطلاعات پایه')
                                    ->schema([
                                        Grid::make(5)
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

                                                TextEntry::make('created_at')
                                                    ->label('تاریخ ثبت')
                                                    ->dateTime()
                                                    ->jalaliDateTime('H:i Y/m/d'),

                                                TextEntry::make('updated_at')
                                                    ->label('آخرین بروزرسانی')
                                                    ->dateTime('Y/m/d H:i')
                                                    ->jalaliDateTime('H:i Y/m/d'),
                                            ])->columnSpanFull(),
                                    ]),

                                // ---------- اطلاعات مالی ----------
                                Fieldset::make('اطلاعات مالی')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                            TextEntry::make('payments.gateway')
                                                ->label('درگاه پرداخت')
                                                ->placeholder('—')
                                                ->badge()
                                                ->color(fn ($state): string => match ($state) {
                                                    'parsian' => 'info',
                                                    'digipay' => 'primary',
                                                    'kamanlend' => 'warning',
                                                    'smartis' => 'success',
                                                    default => 'gray',
                                                })
                                                ->formatStateUsing(fn ($state): string => match ($state) {
                                                    'parsian' => 'پارسیان',
                                                    'digipay' => 'دیجی‌پی',
                                                    'kamanlend' => 'کمان‌لند',
                                                    'smartis' => 'اسمارتیس',
                                                    default => $state ?? '—',
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
                                                    ->formatStateUsing(fn ($state): string => number_format($state) . ' ریال'),

                                                TextEntry::make('items_subtotal')
                                                    ->label('مجموع مبلغ اقلام')
                                                    ->state(fn ($record): string => number_format($record->items->sum('subtotal')))
                                                    ->formatStateUsing(fn ($state): string => $state . ' ریال'),

                                                TextEntry::make('shipping.shipping_cost')
                                                    ->label('هزینه ارسال')
                                                    ->formatStateUsing(fn ($state): string => $state ? number_format($state) . ' ریال' : '0')
                                                    ->placeholder('-'),

                                                TextEntry::make('installment_fee')
                                                    ->label('کارمزد اقساط')
                                                    ->state(fn ($record): ?string => data_get(json_decode($record->notes, true), 'installment_fee'))
                                                    ->formatStateUsing(fn ($state): string => $state ? number_format($state) . ' ریال' : '—')
                                                    ->placeholder('—'),
                                            ])->columnSpanFull(),
                                    ]),

                                // ---------- اطلاعات گیرنده و آدرس ----------
                                Fieldset::make('اطلاعات گیرنده و آدرس')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextEntry::make('deliverTo')
                                                    ->label('تحویل به')
                                                    ->state(fn ($record): string => filled($record->address?->receiver_name) && $record->address->receiver_name !== $record->user?->name ? 'شخص دیگر' : 'خریدار')
                                                    ->badge()
                                                    ->color(fn ($record): string => filled($record->address?->receiver_name) && $record->address->receiver_name !== $record->user?->name ? 'warning' : 'success'),


                                                TextEntry::make('shipping.shippingMethod.name')
                                                    ->label('روش ارسال')
                                                    ->placeholder('—'),

                                                TextEntry::make('address.receiver_name')
                                                    ->label('نام گیرنده')
                                                    ->placeholder('—')
                                                    ->visible(fn ($record): bool => filled($record->address?->receiver_name) && $record->address->receiver_name !== $record->user?->name),

                                                TextEntry::make('address.receiver_phone')
                                                    ->label('تلفن گیرنده')
                                                    ->placeholder('—')
                                                    ->visible(fn ($record): bool => filled($record->address?->receiver_name) && $record->address->receiver_name !== $record->user?->name),

                                                TextEntry::make('address.province.name')
                                                    ->label('استان')
                                                    ->placeholder('—'),

                                                TextEntry::make('address.postal_code')
                                                    ->label('کد پستی')
                                                    ->placeholder('—'),

                                                TextEntry::make('address.receiver_national_code')
                                                    ->label('کد ملی')
                                                    ->placeholder('—')
                                                    ->visible(fn ($record): bool => filled($record->address?->receiver_name) && $record->address->receiver_name !== $record->user?->name),

                                            ])->columnSpanFull(),

                                        TextEntry::make('address.full_address')
                                            ->label('آدرس کامل')
                                            ->placeholder('—')
                                            ->columnSpanFull(),

                                    ])->columnSpanFull(),

                                // ---------- اقلام سفارش ----------
                                Fieldset::make('اقلام سفارش')
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
                                                            ->formatStateUsing(fn ($state): string => number_format($state) . ' ریال'),

                                                        TextEntry::make('quantity')
                                                            ->label('تعداد'),

                                                        TextEntry::make('subtotal')
                                                            ->label('قیمت کل')
                                                            ->formatStateUsing(fn ($state): string => number_format($state) . ' ریال'),
                                                    ]),
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // ==========================================
                        // سایدبار (ستون سمت راست - ۱ ستون از ۴)
                        // ==========================================
                        Section::make('یادداشت‌ها')
                            ->icon('heroicon-o-document-text')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('notes')
                                    ->label('یادداشت‌ها')
                                    ->formatStateUsing(function ($state): HtmlString {
                                        if (! $state) {
                                            return new HtmlString('<span class="text-muted-foreground">—</span>');
                                        }

                                        $data = json_decode($state, true);
                                        if (! is_array($data)) {
                                            return new HtmlString('<span class="text-muted-foreground">' . e($state) . '</span>');
                                        }

                                        $items = [];
                                         if (isset($data['name'])) {
                                             $items[] = '<strong>نام:</strong> ' . e($data['name']);
                                         }
                                         if (isset($data['phone'])) {
                                             $items[] = '<strong>تلفن:</strong> ' . e($data['phone']);
                                         }
                                         if (isset($data['national_code'])) {
                                             $items[] = '<strong>کد ملی:</strong> ' . e($data['national_code']);
                                         }
                                        // if (isset($data['installment_fee'])) {
                                        //     $items[] = '<strong>کارمزد اقساط:</strong> ' . number_format($data['installment_fee']) . ' ریال';
                                        // }

                                        return new HtmlString(implode('<br>', $items) ?: '<span class="text-muted-foreground">—</span>');
                                    }),

                                Grid::make(1)
                                    ->schema([
                                        TextEntry::make('hesabfa_contact_code')
                                            ->label('کد مشتری حسابفا')
                                            ->placeholder('—'),

                                        TextEntry::make('hesabfa_invoice_number')
                                            ->label('شماره فاکتور حسابفا')
                                            ->placeholder('—'),

                                        TextEntry::make('hesabfa_synced_at')
                                            ->label('تاریخ همگام‌سازی حسابفا')
                                            ->placeholder('—'),
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
