<?php

namespace App\Filament\Resources\SerialImportBatches;

use App\Filament\Resources\SerialImportBatches\Pages\ListSerialImportBatches;
use App\Filament\Resources\SerialImportBatches\Pages\ViewSerialImportBatch;
use App\Filament\Resources\SerialImportBatches\RelationManagers\SerialImportRowsRelationManager;
use App\Filament\Resources\SerialImportBatches\Tables\SerialImportBatchesTable;
use App\Models\SerialImportBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SerialImportBatchResource extends Resource
{
    protected static ?string $model = SerialImportBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $recordTitleAttribute = 'original_filename';

    protected static ?string $navigationLabel = 'تاریخچه ایمپورت سریال';

    protected static string|\UnitEnum|null $navigationGroup = 'مدیریت کالا';

    protected static ?int $navigationSort = 12;

    public static function getModelLabel(): string
    {
        return 'بچ ایمپورت';
    }

    public static function getPluralModelLabel(): string
    {
        return 'تاریخچه ایمپورت سریال';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return SerialImportBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SerialImportRowsRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSerialImportBatches::route('/'),
            'view' => ViewSerialImportBatch::route('/{record}'),
        ];
    }
}
