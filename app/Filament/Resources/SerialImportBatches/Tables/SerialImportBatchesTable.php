<?php

namespace App\Filament\Resources\SerialImportBatches\Tables;

use App\Filament\Resources\SerialImportBatches\SerialImportBatchResource;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SerialImportBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('original_filename')
                    ->label('فایل')
                    ->searchable()
                    ->url(fn ($record): string => SerialImportBatchResource::getUrl('view', ['record' => $record])),

                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state->label())
                    ->color(fn ($state): string => match ($state->value) {
                        'completed' => 'success',
                        'completed_with_errors' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('creator.name')
                    ->label('کاربر')
                    ->placeholder('سیستم'),

                TextColumn::make('created_at')
                    ->label('تاریخ')
                    ->dateTime()
                    ->jalaliDateTime('H:i Y/m/d')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->recordActions([
                ViewAction::make()
                    ->label('مشاهده'),
            ]);
    }
}
