<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\ProductSerial;
use App\Models\SerialEvent;
use App\Observers\HesabfaObserver;
use App\Services\Inventory\SerialInventoryService;
use App\Support\Platform;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;

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
                                                        'smartis' => 'اسمارتیس',
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

                                                TextEntry::make('installment_fee')
                                                    ->label('کارمزد اقساط')
                                                    ->state(fn ($record): ?string => data_get(json_decode($record->notes, true), 'installment_fee'))
                                                    ->formatStateUsing(fn ($state): string => $state ? number_format($state).' ریال' : '—')
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

                                                        TextEntry::make('serial_list')
                                                            ->label('سریال‌ها')
                                                            ->state(fn ($record): string => $record->loadMissing('serials')
                                                                ->serials->sortBy('unit_number')->pluck('serial_code')->implode('، ') ?: '—')
                                                            ->columnSpan(2),

                                                        TextEntry::make('fulfillment_label')
                                                            ->label('تأمین')
                                                            ->state(fn ($record): string => $record->serial_fulfillment_status?->label() ?? '—')
                                                            ->badge()
                                                            ->columnSpan(2),
                                                    ]),
                                            ])
                                            ->columnSpanFull(),
                                    ]),

                                Fieldset::make('تاریخچه سریال')
                                    ->schema([
                                        RepeatableEntry::make('serialEvents')
                                            ->label('رویدادها')
                                            ->schema([
                                                TextEntry::make('serial.serial_code')->label('سریال'),
                                                TextEntry::make('event_type')->label('رویداد')->formatStateUsing(fn ($state): string => $state?->value ?? '—'),
                                                TextEntry::make('to_status')->label('وضعیت')->formatStateUsing(fn ($state): string => $state?->label() ?? '—'),
                                                TextEntry::make('created_at')->label('زمان')->dateTime(),
                                                TextEntry::make('actor.name')->label('کاربر')->placeholder('—'),
                                                TextEntry::make('reason')->label('دلیل')->placeholder('—'),
                                            ])->columns(3),
                                    ]),

                                // ---------- تأمین سریال ----------
                                Fieldset::make('تأمین سریال')
                                    ->schema([
                                        TextEntry::make('serial_fulfillment_status')
                                            ->label('وضعیت تأمین')
                                            ->formatStateUsing(fn ($state): string => $state?->label() ?? '—')
                                            ->badge(),
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
                    if ($data['status'] === 'cancelled') {
                        if (! app(SerialInventoryService::class)->cancelUnpaidOrder($record->id, $record->platform, 'لغو از پنل')) {
                            throw ValidationException::withMessages([$this->getMountedActionSchema()->getStatePath().'.status' => 'سفارش پرداخت‌شده یا دارای پرداخت باز قابل لغو نیست.']);
                        }
                    } else {
                        $record->update(['status' => $data['status']]);
                    }

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

            Action::make('edit')
                ->label('ویرایش')
                ->icon('heroicon-o-pencil')
                ->authorize(Permission::OrderEdit->value)
                ->url(fn ($record): string => $record ? $this->getResource()::getUrl('edit', ['record' => $record]) : '#'),

            Action::make('retryFinalize')
                ->label('تلاش مجدد تخصیص')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->authorize(fn (): bool => $this->canMutateInventory(Permission::SerialManage))
                ->visible(fn (?Order $record): bool => $record !== null
                    && $record->serial_inventory_enabled
                    && ! in_array($record->serial_fulfillment_status?->value, ['shipped', 'not_required', 'legacy'], true))
                ->action(function (?Order $record) {
                    abort_unless($this->canMutateInventory(Permission::SerialManage), 403);
                    app(SerialInventoryService::class)->finalizePaidOrder($record->id, $record->platform);

                    Notification::make()
                        ->title('تخصیص بررسی شد')
                        ->success()
                        ->send();
                }),

            Action::make('markShipped')
                ->label('ثبت خروج فیزیکی')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->authorize(fn (): bool => $this->canMutateInventory(Permission::SerialShip))
                ->visible(fn (?Order $record): bool => $record !== null
                    && $record->serial_inventory_enabled
                    && ! in_array($record->serial_fulfillment_status?->value, ['shipped', 'not_required', 'legacy'], true))
                ->requiresConfirmation()
                ->modalHeading('ثبت خروج فیزیکی')
                ->modalDescription('همه واحدهای نیازمند سریال باید تخصیص‌یافته باشند؛ ارسال ناقص ممکن نیست.')
                ->action(function (?Order $record) {
                    abort_unless($this->canMutateInventory(Permission::SerialShip), 403);
                    try {
                        app(SerialInventoryService::class)->markShipped($record->id, $record->platform, auth()->id());
                    } catch (LogicException $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('خروج فیزیکی ثبت شد')
                        ->success()
                        ->send();
                }),

            Action::make('completeProduction')
                ->label('تکمیل تولید')
                ->authorize(fn (): bool => $this->canMutateInventory(Permission::SerialManage))
                ->visible(fn (?Order $record): bool => $record?->serial_fulfillment_status?->value === 'awaiting_production')
                ->schema([Textarea::make('reason')->label('دلیل')->required()->maxLength(500)])
                ->action(function (Order $record, array $data): void {
                    abort_unless($this->canMutateInventory(Permission::SerialManage), 403);
                    app(SerialInventoryService::class)->completeProduction($record->id, $record->platform, auth()->id(), $data['reason']);
                }),

            Action::make('receiveReturn')
                ->label('دریافت مرجوعی')
                ->authorize(fn (): bool => $this->canMutateInventory(Permission::SerialReturn))
                ->schema([
                    Select::make('serial_id')->label('سریال')->options(fn (Order $record): array => $this->ownedSerialOptions($record, 'shipped'))->required(),
                    Textarea::make('reason')->label('دلیل')->required()->maxLength(500),
                ])
                ->action(fn (Order $record, array $data) => $this->mutateOwnedSerial($record, $data, Permission::SerialReturn)),

            Action::make('qcRestock')
                ->label('بازگشت به موجودی پس از QC')
                ->authorize(fn (): bool => $this->canMutateInventory(Permission::SerialRestock))
                ->schema([
                    Select::make('serial_id')->label('سریال')->options(fn (Order $record): array => $this->ownedSerialOptions($record, 'returned'))->required(),
                    Textarea::make('reason')->label('دلیل')->required()->maxLength(500),
                ])
                ->action(fn (Order $record, array $data) => $this->mutateOwnedSerial($record, $data, Permission::SerialRestock)),

            Action::make('releaseHoldings')
                ->label('آزادسازی تخصیص')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->authorize(fn (): bool => $this->canMutateInventory(Permission::SerialManage))
                ->visible(fn (?Order $record): bool => $record !== null
                    && $record->serial_inventory_enabled
                    && in_array($record->serial_fulfillment_status?->value, ['reserved', 'allocated', 'needs_review'], true))
                ->form([
                    Textarea::make('reason')
                        ->label('دلیل آزادسازی')
                        ->required()
                        ->maxLength(500),
                ])
                ->action(function (?Order $record, array $data) {
                    abort_unless($this->canMutateInventory(Permission::SerialManage), 403);
                    app(SerialInventoryService::class)->releaseBeforeShipment($record->id, $record->platform, auth()->id(), $data['reason']);

                    Notification::make()
                        ->title('تخصیص آزاد شد')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function canMutateInventory(Permission $permission): bool
    {
        return (auth()->user()?->checkPermissionTo(Permission::OrderEdit->value) ?? false)
            && (auth()->user()?->checkPermissionTo($permission->value) ?? false);
    }

    private function ownedSerialOptions(Order $record, string $status): array
    {
        return ProductSerial::whereIn('current_order_item_id', $record->items()->pluck('id'))
            ->where('status', $status)->pluck('serial_code', 'id')->all();
    }

    private function mutateOwnedSerial(Order $record, array $data, Permission $permission): void
    {
        abort_unless($this->canMutateInventory($permission), 403);
        DB::transaction(function () use ($record, $data, $permission): void {
            Order::withoutTenantScope()->lockForUpdate()->findOrFail($record->id);
            $items = $record->items()->orderBy('id')->lockForUpdate()->get();
            Product::whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();
            $serial = ProductSerial::whereIn('current_order_item_id', $items->pluck('id'))->lockForUpdate()->find($data['serial_id']);
            if (! $serial || ! SerialEvent::where('product_serial_id', $serial->id)->where('order_id', $record->id)->where('event_type', 'shipped')->exists()) {
                throw ValidationException::withMessages(['serial_id' => 'سریال متعلق به خروج این سفارش نیست.']);
            }
            $service = app(SerialInventoryService::class);
            if ($permission === Permission::SerialReturn) {
                $service->receiveReturn($serial->id, auth()->id(), $data['reason']);
            } else {
                $service->restockReturnedSerial($serial->id, auth()->id(), $data['reason']);
            }
        }, 3);
    }
}
