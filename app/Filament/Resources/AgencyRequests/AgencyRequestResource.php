<?php

namespace App\Filament\Resources\AgencyRequests;

use App\Filament\Resources\AgencyRequests\Pages\ListAgencyRequests;
use App\Filament\Resources\AgencyRequests\Pages\ViewAgencyRequest;
use App\Filament\Resources\AgencyRequests\Tables\AgencyRequestsTable;
use App\Models\AgencyRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AgencyRequestResource extends Resource
{
    protected static ?string $model = AgencyRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $recordTitleAttribute = 'last_name';

    protected static ?string $navigationLabel = 'درخواست‌های نمایندگی';

    protected static string|\UnitEnum|null $navigationGroup = 'مدیریت محتوا';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return 'درخواست نمایندگی';
    }

    public static function getPluralModelLabel(): string
    {
        return 'درخواست‌های نمایندگی';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return AgencyRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgencyRequests::route('/'),
            'view' => ViewAgencyRequest::route('/{record}'),
        ];
    }
}
