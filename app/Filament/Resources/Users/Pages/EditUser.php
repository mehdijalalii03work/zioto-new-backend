<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\UserResource;
use App\Models\City;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'ویرایش کاربر';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('UserTabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('اطلاعات کاربر')
                            ->icon('heroicon-o-user')
                            ->schema(UserForm::sections()),

                        Tab::make('آدرس‌ها')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Repeater::make('addresses')
                                    ->relationship('addresses')
                                    ->label('آدرس‌ها')
                                    ->columnSpanFull()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('label')
                                                    ->label('عنوان')
                                                    ->required()
                                                    ->maxLength(50),
                                                TextInput::make('receiver_name')
                                                    ->label('نام گیرنده')
                                                    ->required()
                                                    ->maxLength(100),
                                                TextInput::make('receiver_phone')
                                                    ->label('تلفن گیرنده')
                                                    ->required()
                                                    ->maxLength(20),
                                                TextInput::make('receiver_national_code')
                                                    ->label('کد ملی گیرنده')
                                                    ->maxLength(10),
                                                Select::make('province_id')
                                                    ->label('استان')
                                                    ->relationship('province', 'name')
                                                    ->searchable()
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn ($set) => $set('city_id', null)),
                                                Select::make('city_id')
                                                    ->label('شهر')
                                                    ->options(fn ($get) => City::where('province_id', $get('province_id'))->pluck('name', 'id'))
                                                    ->searchable()
                                                    ->required()
                                                    ->live(),
                                                TextInput::make('district')
                                                    ->label('منطقه')
                                                    ->maxLength(100),
                                                TextInput::make('postal_code')
                                                    ->label('کد پستی')
                                                    ->required()
                                                    ->maxLength(20),
                                                Textarea::make('address_line')
                                                    ->label('آدرس')
                                                    ->required()
                                                    ->columnSpanFull(),
                                                Toggle::make('is_default')
                                                    ->label('آدرس پیش‌فرض'),
                                                Toggle::make('is_billing')
                                                    ->label('آدرس صورت‌حساب'),
                                            ]),
                                    ])
                                    ->defaultItems(0)
                                    ->addActionLabel('افزودن آدرس جدید'),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('حذف کاربر'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
