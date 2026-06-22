<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'مشاهده کاربر';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('UserTabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('اطلاعات کاربر')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('اطلاعات هویتی')
                                    ->icon('heroicon-o-user')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('first_name')
                                                    ->label('نام'),
                                                TextEntry::make('last_name')
                                                    ->label('نام خانوادگی'),
                                                TextEntry::make('birth_date')
                                                    ->label('تاریخ تولد')
                                                    ->jalaliDate('Y/m/d'),
                                            ]),

                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('national_code')
                                                    ->label('کد ملی'),
                                                TextEntry::make('phone')
                                                    ->label('موبایل'),
                                                IconEntry::make('shahkar_verified')
                                                    ->label('احراز هویت شاهکار')
                                                    ->boolean(),
                                                TextEntry::make('email')
                                                    ->default('-')
                                                    ->label('ایمیل'),

                                                TextEntry::make('created_at')
                                                    ->label('تاریخ ثبت‌نام')
                                                    ->jalaliDateTime('H:i Y/m/d'),
                                                TextEntry::make('updated_at')
                                                    ->label('آخرین بروزرسانی')
                                                    ->jalaliDateTime('H:i Y/m/d'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('آدرس‌ها')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                RepeatableEntry::make('addresses')
                                    ->schema([
                                        TextEntry::make('label')
                                            ->label('عنوان'),
                                        TextEntry::make('receiver_name')
                                            ->label('گیرنده'),
                                        TextEntry::make('receiver_phone')
                                            ->label('تلفن'),
                                        TextEntry::make('receiver_national_code')
                                            ->label('کد ملی'),
                                        TextEntry::make('province.name')
                                            ->label('استان'),
                                        TextEntry::make('city.name')
                                            ->label('شهر'),
                                        TextEntry::make('district')
                                            ->label('منطقه'),
                                        TextEntry::make('postal_code')
                                            ->label('کد پستی'),
                                        TextEntry::make('address_line')
                                            ->label('آدرس')
                                            ->columnSpanFull(),
                                        IconEntry::make('is_default')
                                            ->label('پیش‌فرض')
                                            ->boolean(),
                                        IconEntry::make('is_billing')
                                            ->label('صورت‌حساب')
                                            ->boolean(),
                                    ])
                                    ->columns(2)
                                    ->grid(2)
                                    ->label('آدرس‌ها'),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make()
                ->label('بازیابی')
                ->visible(fn () => filled($this->record->deleted_at)),
            ForceDeleteAction::make()
                ->label('حذف دائمی')
                ->visible(fn () => filled($this->record->deleted_at)),
            DeleteAction::make()
                ->label('حذف')
                ->visible(fn () => is_null($this->record->deleted_at)),
        ];
    }
}
