<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\Inventory\SerialStatus;
use App\Enums\Permission;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductSerialsRelationManager extends RelationManager
{
    protected static string $relationship = 'serials';

    protected static ?string $title = 'سریال‌های محصول';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->checkPermissionTo(Permission::SerialView->value) ?? false;
    }

    public function form(Schema $schema): Schema
    {
        // Serials are system-managed (import/ship/return flows only).
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['importRow.batch', 'currentOrderItem.order']))
            ->columns([
                TextColumn::make('serial_code')
                    ->label('کد سریال')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof SerialStatus ? $state->value : (string) $state) {
                        SerialStatus::Available->value => 'success',
                        SerialStatus::Reserved->value, SerialStatus::Returned->value => 'warning',
                        SerialStatus::Allocated->value => 'info',
                        SerialStatus::Shipped->value => 'gray',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn ($state): string => $state instanceof SerialStatus ? $state->label() : (string) $state),

                TextColumn::make('importRow.batch.id')
                    ->label('بچ ایمپورت')
                    ->description(fn ($record): ?string => $record->importRow?->batch?->original_filename)
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('currentOrderItem.order.id')
                    ->label('سفارش')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('shipped_at')
                    ->label('خروج')
                    ->dateTime()
                    ->jalaliDateTime('H:i Y/m/d')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('ثبت')
                    ->dateTime()
                    ->jalaliDateTime('H:i Y/m/d')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(collect(SerialStatus::cases())->mapWithKeys(fn (SerialStatus $status): array => [$status->value => $status->label()])->all()),
            ])
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(25);
    }
}
