<?php

namespace App\Filament\Resources\DigiExpressCityMappings;

use App\Enums\Permission;
use App\Filament\Resources\DigiExpressCityMappings\Pages\EditDigiExpressCityMapping;
use App\Filament\Resources\DigiExpressCityMappings\Pages\ListDigiExpressCityMappings;
use App\Models\City;
use App\Models\DigiExpressCityMapping;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DigiExpressCityMappingResource extends Resource
{
    protected static ?string $model = DigiExpressCityMapping::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'شهرهای دیجی‌اکسپرس';

    protected static ?string $modelLabel = 'نگاشت شهر دیجی‌اکسپرس';

    protected static ?string $pluralModelLabel = 'شهرهای دیجی‌اکسپرس';

    protected static string|UnitEnum|null $navigationGroup = 'تنظیمات';

    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::ShippingView->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can(Permission::ShippingEdit->value) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('تطبیق شهرها')
                    ->description('پیشنهاد نام فقط برای کمک به بررسی است. فعال‌سازی ارسال تنها بعد از تأیید اپراتور انجام می‌شود.')
                    ->schema([
                        Select::make('city_id')
                            ->label('شهر در سایت')
                            ->options(fn (): array => City::query()
                                ->with('province')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (City $city): array => [
                                    $city->id => ($city->province?->name ? $city->province->name.' — ' : '').$city->name,
                                ])
                                ->all())
                            ->searchable()
                            ->required(),
                        Toggle::make('is_verified')
                            ->label('تطبیق شهر بررسی و تأیید شد')
                            ->live(),
                        Toggle::make('is_destination_enabled')
                            ->label('نمایش «ارسال به آدرس» برای این شهر')
                            ->helperText('این گزینه فقط روش ارسال را در checkout فعال می‌کند و تضمین‌کننده یافتن مسیر عملیاتی نیست.')
                            ->disabled(fn (Get $get): bool => ! (bool) $get('is_verified')),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('digiexpressCity.state_name')->label('استان دیجی‌اکسپرس')->searchable(),
                TextColumn::make('digiexpressCity.name')->label('شهر دیجی‌اکسپرس')->searchable(),
                TextColumn::make('suggestedCity.name')->label('پیشنهاد شهر محلی')->placeholder('بدون پیشنهاد'),
                TextColumn::make('city.name')->label('شهر محلی')->placeholder('نگاشت نشده')->searchable(),
                IconColumn::make('is_verified')->label('تأیید')->boolean(),
                IconColumn::make('is_destination_enabled')->label('فعال در checkout')->boolean(),
            ])
            ->filters([])
            ->defaultSort('digiexpress_city_id')
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDigiExpressCityMappings::route('/'),
            'edit' => EditDigiExpressCityMapping::route('/{record}/edit'),
        ];
    }
}
