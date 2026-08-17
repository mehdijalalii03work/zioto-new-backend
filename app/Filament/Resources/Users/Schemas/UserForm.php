<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function sections(): array
    {
        return [
            Hidden::make('name')
                ->dehydrateStateUsing(fn (callable $get): string => trim(($get('first_name') ?? '').' '.($get('last_name') ?? ''))),

            Section::make('اطلاعات هویتی')
                ->description('نام، نام خانوادگی و تاریخ تولد')
                ->icon('heroicon-o-user')
                ->collapsible()
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('first_name')
                                ->label('نام')
                                ->required()
                                ->maxLength(50)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, $get, $set) => $set('name', trim(($get('first_name') ?? '').' '.($get('last_name') ?? '')))),

                            TextInput::make('last_name')
                                ->label('نام خانوادگی')
                                ->required()
                                ->maxLength(50)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, $get, $set) => $set('name', trim(($get('first_name') ?? '').' '.($get('last_name') ?? '')))),

                            DatePicker::make('birth_date')
                                ->label('تاریخ تولد')
                                ->native(false)
                                ->jalali()
                                ->displayFormat('Y/m/d'),

                            TextInput::make('national_code')
                                ->label('کد ملی')
                                ->maxLength(10)
                                ->unique(ignoreRecord: true)
                                ->regex('/^[0-9]{10}$/'),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make('اطلاعات تماس')
                ->description('شماره موبایل و ایمیل')
                ->icon('heroicon-o-phone')
                ->collapsible()
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('phone')
                                ->label('شماره موبایل')
                                ->tel()
                                ->unique(ignoreRecord: true)
                                ->regex('/^09[0-9]{9}$/')
                                ->placeholder('09123456789'),

                            TextInput::make('email')
                                ->label('ایمیل')
                                ->email()
                                ->unique(ignoreRecord: true),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make('احراز هویت')
                ->description('وضعیت احراز هویت شاهکار و ایمیل')
                ->icon('heroicon-o-shield-check')
                ->collapsible()
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Toggle::make('shahkar_verified')
                                ->label('احراز هویت شاهکار')
                                ->inline(false),

                            Toggle::make('email_verified_at')
                                ->label('ایمیل تأیید شده')
                                ->inline(false)
                                ->dehydrated(false)
                                ->afterStateHydrated(function ($state, $set, $record) {
                                    $set('email_verified_at', filled($record?->email_verified_at));
                                })
                                ->dehydrateStateUsing(fn ($state) => $state ? now() : null),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make('امنیت')
                ->description('رمز عبور (اختیاری - در صورت خالی گذاشتن به صورت خودکار ساخته می‌شود)')
                ->icon('heroicon-o-key')
                ->collapsible()
                ->schema([
                    TextInput::make('password')
                        ->label('رمز عبور')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn ($state) => filled($state))
                        ->maxLength(255)
                        ->helperText('در صورت خالی گذاشتن، رمز عبور به صورت خودکار تولید می‌شود.'),
                ])
                ->columnSpanFull(),

            Section::make('نقش و دسترسی')
                ->description('انتخاب نقش کاربر')
                ->icon('heroicon-o-shield-check')
                ->collapsible()
                ->schema([
                    Select::make('roles')
                        ->relationship('roles', 'name')
                        ->label('نقش')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(static::sections());
    }
}
