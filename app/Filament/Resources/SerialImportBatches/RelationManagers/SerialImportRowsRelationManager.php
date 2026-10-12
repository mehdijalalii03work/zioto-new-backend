<?php

namespace App\Filament\Resources\SerialImportBatches\RelationManagers;

use App\Services\Inventory\SerialImportReportService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SerialImportRowsRelationManager extends RelationManager
{
    protected static string $relationship = 'rows';

    protected static ?string $title = 'سطرهای بچ';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')
                    ->label('ردیف شیت')
                    ->sortable(),

                TextColumn::make('raw_value')
                    ->label('مقدار خام')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('normalized_serial')
                    ->label('نرمال‌شده')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('product.name')
                    ->label('محصول')
                    ->placeholder('—'),

                TextColumn::make('validation_status')
                    ->label('وضعیت تحلیل')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => SerialImportReportService::validationLabel($state->value)),

                TextColumn::make('result_status')
                    ->label('نتیجه نهایی')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => SerialImportReportService::resultLabel($state->value)),

                TextColumn::make('reason_code')
                    ->label('دلیل')
                    ->formatStateUsing(fn ($state, $record): string => SerialImportReportService::reasonLabel($record))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('validation_status')
                    ->label('وضعیت تحلیل')
                    ->options([
                        'valid' => 'معتبر',
                        'empty' => 'خالی',
                        'duplicate_in_file' => 'تکراری در فایل',
                        'invalid_format' => 'قالب نامعتبر',
                        'unknown_prefix' => 'پیشوند ناشناخته',
                        'product_not_tracked' => 'محصول غیرسریالی',
                    ]),

                SelectFilter::make('result_status')
                    ->label('نتیجه نهایی')
                    ->options([
                        'pending' => 'در انتظار',
                        'imported' => 'وارد شده',
                        'duplicate_in_db' => 'تکراری در سامانه',
                        'rejected' => 'رد شده',
                        'empty' => 'خالی',
                    ]),
            ])
            ->defaultSort('row_number')
            ->defaultPaginationPageOption(25);
    }
}
