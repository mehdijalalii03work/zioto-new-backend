<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\Inventory\SerialStatus;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\ProductSerialsRelationManager;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewProductSerials extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'سریال‌های محصول';

    public function infolist(Schema $schema): Schema
    {
        $total = $this->record->serials()->count();
        $free = $this->record->serials()->where('status', SerialStatus::Available->value)->count();

        return $schema
            ->components([
                Section::make('محصول')
                    ->schema([
                        Grid::make(5)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('نام'),

                                TextEntry::make('sku')
                                    ->label('شناسه'),

                                TextEntry::make('serial_prefix')
                                    ->label('پیشوند سریال')
                                    ->placeholder('—'),

                                TextEntry::make('inventory_mode')
                                    ->label('روش موجودی')
                                    ->formatStateUsing(fn ($state): string => $state?->label() ?? '—'),

                                TextEntry::make('serials_summary')
                                    ->label('آزاد / کل')
                                    ->state("{$free} / {$total}"),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<class-string> */
    protected function getAllRelationManagers(): array
    {
        return [ProductSerialsRelationManager::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('ویرایش محصول'),
        ];
    }
}
