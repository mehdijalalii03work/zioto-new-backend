<?php

namespace App\Filament\Resources\Orders\Schemas;

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

                        Textarea::make('shipping_address')
                            ->label('آدرس ارسال')
                            ->rows(3),

                        Textarea::make('notes')
                            ->label('یادداشت‌ها')
                            ->rows(3),
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
            ]);
    }
}
