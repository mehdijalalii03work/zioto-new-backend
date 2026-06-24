<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'تصاویر محصول';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('image_path')
                    ->label('تصویر')
                    ->image()
                    ->disk('public')
                    ->directory('product-images')
                    ->required(),

                TextInput::make('alt')
                    ->label('متن جایگزین (Alt)')
                    ->placeholder('توضیح تصویر برای SEO'),

                Toggle::make('is_primary')
                    ->label('تصویر اصلی')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('تصویر')
                    ->disk('public')
                    ->circular()
                    ->size(60),

                IconColumn::make('is_primary')
                    ->label('اصلی')
                    ->boolean(),

                TextColumn::make('alt')
                    ->label('Alt')
                    ->limit(30),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->label('افزودن تصویر'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->label('ویرایش'),
                \Filament\Actions\DeleteAction::make()
                    ->label('حذف'),
            ])
            ->bulkActions([
                \Filament\Actions\DeleteBulkAction::make()
                    ->label('حذف انتخاب شده‌ها'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
