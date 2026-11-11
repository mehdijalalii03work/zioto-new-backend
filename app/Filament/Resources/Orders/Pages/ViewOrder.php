<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Setting;
use App\Observers\HesabfaObserver;
use App\Services\DigiExpressService;
use App\Services\DigiExpressShipmentService;
use App\Services\DigiExpressWebhookService;
use App\Support\Platform;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
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
                                                    ->label('مشتری')
                                                    ->placeholder('—')
                                                    ->state(function ($record): ?string {
                                                        if ($record->user?->name) {
                                                            return $record->user->name;
                                                        }

                                                        return null;
                                                    }),

                                                TextEntry::make('status')
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
                                                        'nopay' => 'danger',
                                                        default => 'gray',
                                                    })
                                                    ->formatStateUsing(fn ($state): string => match ($state) {
                                                        'parsian' => 'پارسیان',
                                                        'digipay' => 'دیجی‌پی',
                                                        'kamanlend' => 'کمان‌لند',
                                                        'smartis' => 'اسمارتیز',
                                                        'nopay' => 'نوپی',
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
                                                    ->formatStateUsing(fn ($state): string => number_format($state).' ریال'),

                                                TextEntry::make('items_subtotal')
                                                    ->label('مجموع مبلغ اقلام')
                                                    ->state(fn ($record): string => number_format($record->items->sum('subtotal')))
                                                    ->formatStateUsing(fn ($state): string => $state.' ریال'),

                                                TextEntry::make('shipping.shipping_cost')
                                                    ->label('هزینه ارسال')
                                                    ->formatStateUsing(fn ($state): string => $state ? number_format($state).' ریال' : '0')
                                                    ->placeholder('-'),

                                                TextEntry::make('shipping.shipping_status')
                                                    ->label('وضعیت ارسال')
                                                    ->badge()
                                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                                        'processing' => 'در حال بررسی',
                                                        'route_searching' => 'یافتن مسیر',
                                                        'route_found' => 'مسیر پیدا شد',
                                                        'awaiting_pickup' => 'در انتظار ارسال',
                                                        'in_transit' => 'در حال ارسال',
                                                        'delivered' => 'تحویل‌شده',
                                                        'failed', 'manual_review' => 'نیازمند بررسی دستی',
                                                        'manual_fallback' => 'شرکت جایگزین ثبت شد',
                                                        'cancelled' => 'لغو شده',
                                                        default => $state ?: '—',
                                                    }),

                                                TextEntry::make('shipping.carrier_code')
                                                    ->label('شرکت حمل (داخلی)')
                                                    ->placeholder('—'),

                                                TextEntry::make('shipping.tracking_number')
                                                    ->label('شماره رهگیری')
                                                    ->placeholder('—'),

                                                TextEntry::make('shipping.provider_error')
                                                    ->label('خطای آخر ارسال')
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),

                                                TextEntry::make('digiExpressEventHistory')
                                                    ->label('تاریخچه رخدادهای ارسال')
                                                    ->state(function ($record): HtmlString {
                                                        $events = app(DigiExpressWebhookService::class)->recentEventsForOrder((int) $record->id);
                                                        if ($events->isEmpty()) {
                                                            return new HtmlString('—');
                                                        }
                                                        $lines = $events->map(fn ($event): string => e(($event->occurred_at?->format('Y-m-d H:i:s') ?? $event->created_at->format('Y-m-d H:i:s')).' · '.($event->shipment_status ?: $event->parcel_status ?: $event->progress_type).' · '.($event->processed_at ? 'پردازش‌شده' : 'در انتظار پردازش')));

                                                        return new HtmlString($lines->implode('<br>'));
                                                    })
                                                    ->columnSpanFull(),

                                                TextEntry::make('installment_fee')
                                                    ->label('کارمزد اقساط')
                                                    ->state(fn ($record): ?string => data_get(json_decode($record->notes, true), 'installment_fee'))
                                                    ->formatStateUsing(fn ($state): string => $state ? number_format($state).' ریال' : '—')
                                                    ->placeholder('—'),

                                                TextEntry::make('discount_status')
                                                    ->label('وضعیت تخفیف')
                                                    ->state(fn ($record): string => (int) $record->discount_amount > 0 ? 'تخفیف اعمال شده' : 'تخفیفی اعمال نشده')
                                                    ->badge()
                                                    ->color(fn ($record): string => (int) $record->discount_amount > 0 ? 'success' : 'gray'),

                                                TextEntry::make('discount_code_snapshot')
                                                    ->label('کد تخفیف')
                                                    ->placeholder('—'),

                                                TextEntry::make('discount_amount')
                                                    ->label('مبلغ تخفیف')
                                                    ->formatStateUsing(fn ($state): string => (int) $state > 0 ? number_format((int) $state).' ریال' : '—'),
                                            ])->columnSpanFull(),
                                    ]),

                                // ---------- اطلاعات گیرنده و آدرس ----------
                                Fieldset::make('اطلاعات گیرنده و آدرس')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextEntry::make('deliverTo')
                                                    ->label('تحویل به')
                                                    ->state(function ($record): string {
                                                        $receiverName = $record->address?->receiver_name ?? data_get($record->shipping_address_snapshot, 'full_name');

                                                        return filled($receiverName) && $receiverName !== $record->user?->name ? 'شخص دیگر' : 'خریدار';
                                                    })
                                                    ->badge()
                                                    ->color(function ($record): string {
                                                        $receiverName = $record->address?->receiver_name ?? data_get($record->shipping_address_snapshot, 'full_name');

                                                        return filled($receiverName) && $receiverName !== $record->user?->name ? 'warning' : 'success';
                                                    }),

                                                TextEntry::make('shipping.shippingMethod.name')
                                                    ->label('روش ارسال')
                                                    ->placeholder('—'),

                                                TextEntry::make('address.receiver_name')
                                                    ->label('نام گیرنده')
                                                    ->placeholder('—')
                                                    ->state(fn ($record): ?string => $record->address?->receiver_name ?? data_get($record->shipping_address_snapshot, 'full_name'))
                                                    ->visible(function ($record): bool {
                                                        $receiverName = $record->address?->receiver_name ?? data_get($record->shipping_address_snapshot, 'full_name');

                                                        return filled($receiverName) && $receiverName !== $record->user?->name;
                                                    }),

                                                TextEntry::make('address.receiver_phone')
                                                    ->label('تلفن گیرنده')
                                                    ->placeholder('—')
                                                    ->state(fn ($record): ?string => $record->address?->receiver_phone ?? data_get($record->shipping_address_snapshot, 'phone'))
                                                    ->visible(function ($record): bool {
                                                        $receiverName = $record->address?->receiver_name ?? data_get($record->shipping_address_snapshot, 'full_name');

                                                        return filled($receiverName) && $receiverName !== $record->user?->name;
                                                    }),

                                                TextEntry::make('address.province.name')
                                                    ->label('استان')
                                                    ->placeholder('—')
                                                    ->state(fn ($record): ?string => $record->address?->province?->name ?? data_get($record->shipping_address_snapshot, 'province')),

                                                TextEntry::make('address.postal_code')
                                                    ->label('کد پستی')
                                                    ->placeholder('—')
                                                    ->state(fn ($record): ?string => $record->address?->postal_code ?? data_get($record->shipping_address_snapshot, 'postal_code')),

                                                TextEntry::make('address.receiver_national_code')
                                                    ->label('کد ملی')
                                                    ->placeholder('—')
                                                    ->state(fn ($record): ?string => $record->address?->receiver_national_code ?? data_get($record->notes, 'customer_national_code'))
                                                    ->visible(function ($record): bool {
                                                        $receiverName = $record->address?->receiver_name ?? data_get($record->shipping_address_snapshot, 'full_name');

                                                        return filled($receiverName) && $receiverName !== $record->user?->name;
                                                    }),

                                            ])->columnSpanFull(),

                                        TextEntry::make('address.full_address')
                                            ->label('آدرس کامل')
                                            ->placeholder('—')
                                            ->state(fn ($record): ?string => $record->address?->full_address ?? data_get($record->shipping_address_snapshot, 'address'))
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
                                                            ->formatStateUsing(fn ($state): string => number_format($state).' ریال'),

                                                        TextEntry::make('quantity')
                                                            ->label('تعداد'),

                                                        TextEntry::make('subtotal')
                                                            ->label('قیمت کل')
                                                            ->formatStateUsing(fn ($state): string => number_format($state).' ریال'),
                                                    ]),
                                                Grid::make(6)
                                                    ->visible(fn ($entry): bool => $entry?->getState() ? filled($entry->getState()['price_board_item'] ?? null) : false)
                                                    ->schema([
                                                        TextEntry::make('price_board_item')
                                                            ->label('آیتم تابلو قیمت')
                                                            ->formatStateUsing(fn ($state): string => match ($state) {
                                                                'Gold750_Sell' => 'طلای ۷۵۰ (فروش)',
                                                                'Gold750_Buy' => 'طلای ۷۵۰ (خرید)',
                                                                'Gold995_Sell' => 'طلای ۹۹۵ (فروش)',
                                                                'Gold995_Buy' => 'طلای ۹۹۵ (خرید)',
                                                                'Gold9999_Sell' => 'طلای ۹۹۹.۹ (فروش)',
                                                                'Gold9999_Buy' => 'طلای ۹۹۹.۹ (خرید)',
                                                                'Silver9999_Sell' => 'نقره ۹۹۹.۹ (فروش)',
                                                                'Silver9999_Buy' => 'نقره ۹۹۹.۹ (خرید)',
                                                                default => $state,
                                                            }),

                                                        TextEntry::make('board_price')
                                                            ->label('قیمت تابلو (تومان/گرم)')
                                                            ->formatStateUsing(fn ($state): string => $state ? number_format($state, 2).' تومان' : '—'),

                                                        TextEntry::make('weight')
                                                            ->label('وزن (گرم)')
                                                            ->formatStateUsing(fn ($state): string => $state ? number_format($state, 2) : '—'),

                                                        TextEntry::make('labor_coefficient')
                                                            ->label('ضریب اجرت')
                                                            ->formatStateUsing(fn ($state): string => $state ? (number_format($state * 100, 4).'٪') : '—'),

                                                        TextEntry::make('labor_cost')
                                                            ->label('مبلغ اجرت (تومان)')
                                                            ->formatStateUsing(fn ($state): string => $state ? number_format($state, 2).' تومان' : '—'),

                                                        TextEntry::make('final_price')
                                                            ->label('قیمت نهایی واحد (تومان)')
                                                            ->formatStateUsing(fn ($state): string => $state ? number_format($state, 2).' تومان' : '—'),
                                                    ]),
                                                Grid::make(1)
                                                    ->visible(fn ($entry): bool => $entry?->getState() ? filled($entry->getState()['taxes'] ?? null) : false)
                                                    ->schema([
                                                        TextEntry::make('taxes')
                                                            ->label('ماليیات‌ها')
                                                            ->formatStateUsing(function ($state): HtmlString {
                                                                if (! is_array($state) || empty($state)) {
                                                                    return new HtmlString('<span class="text-muted-foreground">—</span>');
                                                                }
                                                                $lines = [];
                                                                foreach ($state as $tax) {
                                                                    $scope = $tax['scope'] === 'labor' ? 'اجرت' : 'روی کل';
                                                                    $lines[] = "مالیات {$scope} ({$tax['rate']}٪): ".number_format($tax['amount']).' تومان';
                                                                }

                                                                return new HtmlString(implode('<br>', $lines));
                                                            }),
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

                                Grid::make(1)
                                    ->visible(fn ($record): bool => $record->platform === Platform::TAPSI)
                                    ->schema([
                                        TextEntry::make('tapsi_order_id')
                                            ->label('شناسه سفارش تپسی')
                                            ->placeholder('—'),

                                        TextEntry::make('tapsi_order_number')
                                            ->label('شماره سفارش تپسی')
                                            ->placeholder('—'),

                                        TextEntry::make('tapsi_shipment_bundle')
                                            ->label('شماره مرسوله')
                                            ->placeholder('—'),

                                        TextEntry::make('tapsi_delivery_method')
                                            ->label('روش ارسال')
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
                                            }),

                                        TextEntry::make('tapsi_service_fee')
                                            ->label('کارمزد سرویس')
                                            ->formatStateUsing(fn ($state): string => $state ? number_format($state).' تومان' : '—'),

                                        TextEntry::make('tapsi_voucher_fee')
                                            ->label('تخفیف واوچر')
                                            ->formatStateUsing(fn ($state): string => $state ? number_format($state).' تومان' : '—'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeStatus')
                ->label('تغییر وضعیت')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->authorize(Permission::OrderEdit->value)
                ->modalHeading('تغییر وضعیت سفارش')
                ->modalDescription('وضعیت جدید سفارش را انتخاب کنید.')
                ->form([
                    Select::make('status')
                        ->label('وضعیت جدید')
                        ->options([
                            'pending' => 'در انتظار بررسی',
                            'confirmed' => 'تایید شده',
                            'completed' => 'تکمیل شده',
                            'cancelled' => 'لغو شده',
                        ])
                        ->default(fn ($record) => $record->status)
                        ->required(),
                ])
                ->action(function ($record, array $data) {
                    $record->update(['status' => $data['status']]);

                    Notification::make()
                        ->title('وضعیت سفارش با موفقیت تغییر یافت')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('syncToHesabfa')
                ->label('ارسال به حسابفا')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->authorize(Permission::HesabfaSync->value)
                ->action(function ($record) {
                    $observer = app(HesabfaObserver::class);
                    $result = $observer->syncOrder($record, force: true);

                    if ($result['success']) {
                        Notification::make()
                            ->title($result['message'])
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title($result['message'])
                            ->danger()
                            ->send();
                    }

                    $this->refreshFormData(['hesabfa_contact_code', 'hesabfa_invoice_number', 'hesabfa_synced_at']);
                }),

            Action::make('registerDigiExpressShipment')
                ->label('ثبت مرسوله ارسال به آدرس')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->authorize(Permission::ShippingEdit->value)
                ->visible(fn ($record): bool => $record->shipping?->shippingMethod?->code === 'digiexpress_address' && blank($record->shipping?->provider_shipment_id))
                ->modalHeading('ثبت مرسوله')
                ->form([
                    TextInput::make('weight_grams')->label('وزن تخمینی (گرم)')->numeric()->minValue(1)
                        ->visible(fn (): bool => Setting::getValue('digiexpress_parcel_mode', 'estimated') === 'estimated'),
                    TextInput::make('length_cm')->label('طول (سانتی‌متر)')->numeric()->minValue(1)
                        ->visible(fn (): bool => Setting::getValue('digiexpress_parcel_mode', 'estimated')),
                    TextInput::make('width_cm')->label('عرض (سانتی‌متر)')->numeric()->minValue(1)
                        ->visible(fn (): bool => Setting::getValue('digiexpress_parcel_mode', 'estimated')),
                    TextInput::make('height_cm')->label('ارتفاع (سانتی‌متر)')->numeric()->minValue(1)
                        ->visible(fn (): bool => Setting::getValue('digiexpress_parcel_mode', 'estimated')),
                    Repeater::make('parcels')->label('پارسل‌ها')->schema([
                        TextInput::make('weight_grams')->label('وزن (گرم)')->numeric()->minValue(1)->required(),
                        TextInput::make('length_cm')->label('طول')->numeric()->minValue(1)->required(),
                        TextInput::make('width_cm')->label('عرض')->numeric()->minValue(1)->required(),
                        TextInput::make('height_cm')->label('ارتفاع')->numeric()->minValue(1)->required(),
                        Select::make('packaging_type')->label('نوع بسته‌بندی')->options(['box' => 'جعبه', 'packet' => 'پاکت'])->required(),
                        Select::make('characteristic')->label('ماهیت کالا')->options(['normal' => 'عادی', 'gold' => 'طلا'])->required(),
                    ])->columns(3)->minItems(1)
                        ->visible(fn (): bool => Setting::getValue('digiexpress_parcel_mode', 'estimated') === 'specified'),
                ])
                ->action(function ($record, array $data): void {
                    $preparation = [];
                    if (Setting::getValue('digiexpress_parcel_mode', 'estimated') === 'estimated') {
                        $dims = array_filter([$data['length_cm'] ?? null, $data['width_cm'] ?? null, $data['height_cm'] ?? null], fn ($value): bool => filled($value));
                        if (count($dims) === 3) {
                            $preparation['dimensions_cm'] = array_map('intval', array_values($dims));
                        }
                        if (filled($data['weight_grams'] ?? null)) {
                            $preparation['weight_grams'] = (int) $data['weight_grams'];
                        }
                    } else {
                        $preparation['parcels'] = array_map(fn (array $parcel, int $index): array => [
                            'consignor_reference_id' => $record->id.'-'.($index + 1),
                            'dimensionsInCMs' => [(string) $parcel['length_cm'], (string) $parcel['width_cm'], (string) $parcel['height_cm']],
                            'weight_grams' => (string) $parcel['weight_grams'],
                            'packaging_type' => $parcel['packaging_type'],
                            'characteristics' => [$parcel['characteristic']],
                        ], $data['parcels'] ?? [], array_keys($data['parcels'] ?? []));
                    }
                    $shipping = app(DigiExpressShipmentService::class)->register($record, $preparation);
                    Notification::make()
                        ->title($shipping->shipping_status === 'manual_review' ? 'ثبت مرسوله ناموفق بود؛ سفارش در صف بررسی دستی قرار گرفت' : 'مرسوله ثبت شد')
                        ->color($shipping->shipping_status === 'manual_review' ? 'danger' : 'success')
                        ->send();
                    $this->refreshFormData(['shipping']);
                }),

            Action::make('saveManualCarrier')
                ->label('ثبت شرکت جایگزین و رهگیری')
                ->icon('heroicon-o-pencil-square')
                ->authorize(Permission::ShippingEdit->value)
                ->visible(fn ($record): bool => $record->shipping?->shippingMethod?->code === 'digiexpress_address')
                ->form([
                    TextInput::make('carrier_code')->label('شرکت حمل')->required()->maxLength(50),
                    TextInput::make('tracking_number')->label('شماره رهگیری')->maxLength(100),
                    TextInput::make('tracking_url')->label('لینک رهگیری')->url()->maxLength(500),
                ])
                ->action(function ($record, array $data): void {
                    $record->shipping?->forceFill($data + ['shipping_status' => 'manual_fallback'])->save();
                    Notification::make()->title('اطلاعات شرکت جایگزین ذخیره شد')->success()->send();
                    $this->refreshFormData(['shipping']);
                }),

            Action::make('cancelDigiExpressShipment')
                ->label('لغو مرسوله')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->authorize(Permission::ShippingEdit->value)
                ->visible(fn ($record): bool => $record->shipping?->shippingMethod?->code === 'digiexpress_address' && filled($record->shipping?->provider_shipment_id) && ! in_array($record->shipping?->shipping_status, ['cancelled', 'delivered'], true))
                ->requiresConfirmation()
                ->action(function ($record): void {
                    app(DigiExpressShipmentService::class)->cancel($record->shipping);
                    Notification::make()->title('مرسوله لغو شد')->success()->send();
                    $this->refreshFormData(['shipping']);
                }),

            Action::make('addDigiExpressParcels')
                ->label('افزودن بسته‌های آماده‌شده')
                ->icon('heroicon-o-archive-box')
                ->authorize(Permission::ShippingEdit->value)
                ->visible(fn ($record): bool => $record->shipping?->shippingMethod?->code === 'digiexpress_address' && filled($record->shipping?->provider_shipment_id) && Setting::getValue('digiexpress_parcel_mode', 'estimated') === 'estimated')
                ->form([
                    Repeater::make('parcels')->label('بسته‌ها')->schema([
                        TextInput::make('weight_grams')->label('وزن (گرم)')->numeric()->minValue(1)->required(),
                        TextInput::make('length_cm')->label('طول')->numeric()->minValue(1)->required(),
                        TextInput::make('width_cm')->label('عرض')->numeric()->minValue(1)->required(),
                        TextInput::make('height_cm')->label('ارتفاع')->numeric()->minValue(1)->required(),
                        Select::make('packaging_type')->label('بسته‌بندی')->options(['box' => 'جعبه', 'packet' => 'پاکت'])->required(),
                        Select::make('characteristic')->label('ماهیت')->options(['normal' => 'عادی', 'gold' => 'طلا'])->required(),
                    ])->columns(3)->minItems(1)->required(),
                ])
                ->action(function ($record, array $data): void {
                    $parcels = array_map(fn (array $parcel, int $index): array => [
                        'consignor_reference_id' => $record->id.'-'.now()->format('His').'-'.($index + 1),
                        'dimensionsInCMs' => [(string) $parcel['length_cm'], (string) $parcel['width_cm'], (string) $parcel['height_cm']],
                        'weight_grams' => (string) $parcel['weight_grams'],
                        'content_value_price_rials' => (string) (int) $record->total_amount,
                        'packaging_type' => $parcel['packaging_type'],
                        'characteristics' => [$parcel['characteristic']],
                        'items_description' => 'سفارش اینترنتی',
                    ], $data['parcels'], array_keys($data['parcels']));
                    app(DigiExpressShipmentService::class)->addPreparedParcels($record->shipping, $parcels);
                    Notification::make()->title('بسته‌ها ثبت شدند')->success()->send();
                    $this->refreshFormData(['shipping']);
                }),

            Action::make('downloadDigiExpressLabel')
                ->label('دریافت لیبل پارسل')
                ->icon('heroicon-o-document-arrow-down')
                ->authorize(Permission::ShippingView->value)
                ->visible(fn ($record): bool => $record->shipping?->digiExpressParcels()->exists() ?? false)
                ->form([
                    Select::make('parcel_id')->label('پارسل')->options(fn ($record): array => $record->shipping->digiExpressParcels()->get()->mapWithKeys(fn ($parcel): array => [$parcel->id => $parcel->parcel_code ?: $parcel->consignor_reference_id])->all())->required(),
                ])
                ->action(function ($record, array $data) {
                    $parcel = $record->shipping->digiExpressParcels()->findOrFail($data['parcel_id']);
                    $path = app(DigiExpressShipmentService::class)->downloadParcelLabel($parcel);

                    return Storage::disk('local')->download($path);
                }),

            Action::make('recoverDigiExpressEvents')
                ->label('بازیابی رخدادهای ارسال')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->authorize(Permission::ShippingEdit->value)
                ->visible(fn ($record): bool => $record->shipping?->shippingMethod?->code === 'digiexpress_address')
                ->action(function ($record): void {
                    $shipping = $record->shipping;
                    if (filled($shipping?->provider_reference_id)) {
                        $feed = app(DigiExpressService::class)->webhookFeed([$shipping->provider_reference_id]);
                        app(DigiExpressWebhookService::class)->ingestFeed($feed);
                    }
                    $count = app(DigiExpressWebhookService::class)->replayUnprocessed();
                    Notification::make()->title("رخدادهای بازیابی‌شده: {$count}")->success()->send();
                    $this->refreshFormData(['shipping']);
                }),

            Action::make('edit')
                ->label('ویرایش')
                ->icon('heroicon-o-pencil')
                ->authorize(Permission::OrderEdit->value)
                ->url(fn ($record): string => $record ? $this->getResource()::getUrl('edit', ['record' => $record]) : '#'),
        ];
    }
}
