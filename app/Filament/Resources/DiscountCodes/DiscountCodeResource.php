<?php

namespace App\Filament\Resources\DiscountCodes;

use App\Filament\Resources\DiscountCodes\Pages\CreateDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\EditDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\ListDiscountCodes;
use App\Filament\Resources\DiscountCodes\Schemas\DiscountCodeForm;
use App\Filament\Resources\DiscountCodes\Tables\DiscountCodesTable;
use App\Models\DiscountCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DiscountCodeResource extends Resource
{
    protected static ?string $model = DiscountCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $navigationLabel = 'کدهای تخفیف';

    protected static string|\UnitEnum|null $navigationGroup = 'فروش';

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return 'کد تخفیف';
    }

    public static function getPluralModelLabel(): string
    {
        return 'کدهای تخفیف';
    }

    public static function form(Schema $schema): Schema
    {
        return DiscountCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DiscountCodesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscountCodes::route('/'),
            'create' => CreateDiscountCode::route('/create'),
            'edit' => EditDiscountCode::route('/{record}/edit'),
        ];
    }
}
