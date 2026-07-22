<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\ShippingMethod;
use App\Models\UserAddress;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Product\Models\Product;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اطلاعات سفارش')
                    ->description('شماره سفارش، وضعیت و اطلاعات پرداخت')
                    ->icon('heroicon-o-shopping-cart')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('order_number')
                                    ->label('شماره سفارش')
                                    ->required()
                                    ->unique(ignoreRecord: true),

                                Select::make('user_id')
                                    ->label('مشتری')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload(),

                                Select::make('status')
                                    ->label('وضعیت')
                                    ->required()
                                    ->options([
                                        'pending' => 'در انتظار بررسی',
                                        'confirmed' => 'تایید شده',
                                        'processing' => 'در حال پردازش',
                                        'shipped' => 'ارسال شده',
                                        'delivered' => 'تحویل شده',
                                        'cancelled' => 'لغو شده',
                                    ])
                                    ->default('pending'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('payment_method')
                                    ->label('روش پرداخت')
                                    ->options([
                                        'online' => 'آنلاین',
                                        'installment' => 'قسطی',
                                    ]),

                                Select::make('payment_status')
                                    ->label('وضعیت پرداخت')
                                    ->options([
                                        'pending' => 'در انتظار پرداخت',
                                        'paid' => 'پرداخت شده',
                                        'failed' => 'ناموفق',
                                        'refunded' => 'مسترد شده',
                                    ])
                                    ->default('pending'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('آدرس ارسال')
                    ->description('اطلاعات آدرس تحویل سفارش')
                    ->icon('heroicon-o-map-pin')
                    ->collapsible()
                    ->schema([
                        Select::make('user_address_id')
                            ->label('آدرس ذخیره شده')
                            ->relationship('address', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_address)
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if (! $state) {
                                    $set('shipping_address_snapshot', null);

                                    return;
                                }
                                $address = UserAddress::with(['province', 'city'])->find($state);
                                if ($address) {
                                    $set('shipping_address_snapshot', $address->full_address);
                                }
                            }),

                        Textarea::make('shipping_address_snapshot')
                            ->label('متن آدرس')
                            ->rows(3)
                            ->placeholder('متن کامل آدرس در زمان ثبت سفارش'),
                    ])
                    ->columnSpanFull(),

                Section::make('اطلاعات ارسال')
                    ->description('روش ارسال، هزینه و رهگیری')
                    ->icon('heroicon-o-truck')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('shipping.shipping_method_id')
                                    ->label('روش ارسال')
                                    ->relationship('shipping', 'shipping_method_id')
                                    ->options(fn () => ShippingMethod::pluck('name', 'id'))
                                    ->nullable(),

                                TextInput::make('shipping.shipping_cost')
                                    ->label('هزینه ارسال (ریال)')
                                    ->numeric()
                                    ->nullable(),

                                TextInput::make('shipping.tracking_number')
                                    ->label('شماره رهگیری')
                                    ->maxLength(100)
                                    ->nullable(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('shipping.tracking_url')
                                    ->label('لینک رهگیری')
                                    ->maxLength(500)
                                    ->nullable(),

                                DatePicker::make('shipping.pickup_date')
                                    ->label('تاریخ مراجعه (تحویل حضوری)')
                                    ->nullable(),

                                DatePicker::make('shipping.delivered_at')
                                    ->label('تاریخ تحویل')
                                    ->nullable(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('محصولات سفارش')
                    ->description('اقلام موجود در این سفارش')
                    ->icon('heroicon-o-cube')
                    ->collapsible()
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        Select::make('product_id')
                                            ->label('محصول')
                                            ->relationship('product', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set) {
                                                if (! $state) {
                                                    return;
                                                }
                                                $product = Product::find($state);
                                                if ($product) {
                                                    $set('product_name', $product->name);
                                                    $set('product_price', $product->price);
                                                    $set('subtotal', $product->price * ($set('quantity', 1) ?: 1));
                                                }
                                            }),

                                        TextInput::make('product_name')
                                            ->label('نام محصول')
                                            ->disabled()
                                            ->dehydrated(),

                                        TextInput::make('product_price')
                                            ->label('قیمت واحد')
                                            ->numeric()
                                            ->live()
                                            ->afterStateUpdated(fn ($state, $set, $get) => $set('subtotal', ($state ?? 0) * ($get('quantity') ?? 1))),

                                        TextInput::make('quantity')
                                            ->label('تعداد')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->live()
                                            ->afterStateUpdated(fn ($state, $set, $get) => $set('subtotal', ($state ?? 1) * ($get('product_price') ?? 0))),

                                        TextInput::make('subtotal')
                                            ->label('قیمت کل')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(),
                                    ]),
                            ])
                            ->addActionLabel('افزودن محصول')
                            ->reorderable()
                            ->defaultItems(0)
                            ->collapsible(),
                    ])
                    ->columnSpanFull(),

                Section::make('یادداشت‌ها')
                    ->description('یادداشت‌های سفارش')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->schema([
                        Textarea::make('notes')
                            ->label('یادداشت‌ها (JSON)')
                            ->rows(4)
                            ->placeholder('{"name": "...", "phone": "...", "national_code": "..."}')
                            ->helperText('فرمت JSON. این فیلد برای توسعه‌دهندگان است.'),
                    ])
                    ->columnSpanFull(),

                Section::make('اطلاعات حسابفا')
                    ->description('وضعیت همگام‌سازی با حسابفا')
                    ->icon('heroicon-o-arrow-path')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('hesabfa_contact_code')
                                    ->label('کد مشتری')
                                    ->disabled()
                                    ->dehydrated()
                                    ->visible(fn ($record) => $record?->hesabfa_contact_code),

                                TextInput::make('hesabfa_invoice_number')
                                    ->label('شماره فاکتور')
                                    ->disabled()
                                    ->dehydrated()
                                    ->visible(fn ($record) => $record?->hesabfa_invoice_number),

                                TextInput::make('hesabfa_synced_at')
                                    ->label('تاریخ همگام‌سازی')
                                    ->disabled()
                                    ->dehydrated()
                                    ->visible(fn ($record) => $record?->hesabfa_synced_at),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
