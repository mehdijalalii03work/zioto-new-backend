<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اطلاعات تراکنش')
                    ->description('اطلاعات اصلی تراکنش پرداخت')
                    ->icon('heroicon-o-currency-dollar')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('transaction_id')
                                    ->label('شماره تراکنش')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50),

                                Select::make('user_id')
                                    ->label('کاربر')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Select::make('order_id')
                                    ->label('سفارش')
                                    ->relationship('order', 'order_number')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('مبلغ (ریال)')
                                    ->required()
                                    ->numeric(),

                                Select::make('payment_method')
                                    ->label('روش پرداخت')
                                    ->options([
                                        'online' => 'آنلاین',
                                        'card_to_card' => 'کارت به کارت',
                                        'installment' => 'اقساطی',
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('اطلاعات پرداخت')
                    ->description('وضعیت و اطلاعات درگاه پرداخت')
                    ->icon('heroicon-o-banknotes')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('status')
                                    ->label('وضعیت')
                                    ->required()
                                    ->options([
                                        'pending' => 'در انتظار',
                                        'successful' => 'موفق',
                                        'failed' => 'ناموفق',
                                        'refunded' => 'مسترد شده',
                                    ])
                                    ->default('pending'),

                                TextInput::make('gateway')
                                    ->label('درگاه پرداخت')
                                    ->maxLength(50)
                                    ->nullable(),

                                DatePicker::make('paid_at')
                                    ->label('تاریخ پرداخت')
                                    ->nullable(),
                            ]),

                        Textarea::make('description')
                            ->label('توضیحات')
                            ->rows(3),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
