<?php

namespace App\Filament\Resources\BlogPosts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlogPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('featured_image')
                    ->label('تصویر')
                    ->collection('featured-image')
                    ->circular()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('دسته‌بندی')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('وضعیت')
                    ->formatStateUsing(fn ($state): string => $state === 'published' ? 'منتشر شده' : 'پیش‌نویس')
                    ->badge(fn ($state): string => $state === 'published' ? 'success' : 'warning'),

                TextColumn::make('slug')
                    ->label('شناسه')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('published_at')
                    ->label('تاریخ انتشار')
                    ->dateTime()
                    ->jalaliDateTime('H:i Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->dateTime()
                    ->jalaliDateTime('H:i Y/m/d')
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
