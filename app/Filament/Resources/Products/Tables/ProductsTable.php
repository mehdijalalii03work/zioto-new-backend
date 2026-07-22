<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('product-images')
                    ->label('تصویر')
                    ->collection('product-images')
                    ->circular()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('نام محصول')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('دسته‌بندی')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sku')
                    ->label('شناسه محصول')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('آدرس محصول')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('metal_type')
                    ->label('نوع')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->sortable(),

                TextColumn::make('form')
                    ->label('نوع کالا')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->sortable(),

                TextColumn::make('ayar')
                    ->label('عیار')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('قیمت (تومان)')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('stock_quantity')
                    ->label('موجودی')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->defaultPaginationPageOption(25)
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make()
                    ->label('ویرایش'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('حذف انتخاب شده‌ها'),
                ]),
            ]);
    }
}
