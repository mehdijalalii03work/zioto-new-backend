<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'مشاهده کاربر';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                                    ->date('Y/m/d'),
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
