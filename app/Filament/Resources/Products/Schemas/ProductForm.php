<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اطلاعات عمومی')
                    ->description('نام، شناسه یکتا و توضیحات محصول')
                    ->icon('heroicon-o-information-circle')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('name')
                                    ->label('نام محصول')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),

                                TextInput::make('slug')
                                    ->label('شناسه یکتا')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->helperText('به صورت خودکار از نام محصول تولید می‌شود'),

                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->unique(ignoreRecord: true)
                                    ->helperText('کد اختصاصی محصول (اختیاری)'),
                            ]),

                        RichEditor::make('description')
                            ->label('توضیحات کامل')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'link', 'orderedList', 'bulletList',
                                'h2', 'h3', 'blockquote', 'redo', 'undo',
                            ])
                            ->extraAttributes(['style' => 'min-height: 400px']),
                    ])
                    ->columnSpanFull(),

                Section::make('قیمت و موجودی')
                    ->description('قیمت، وزن و موجودی انبار')
                    ->icon('heroicon-o-currency-dollar')
                    ->collapsible()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('price')
                                    ->label('قیمت')
                                    ->required()
                                    ->numeric()
                                    ->suffix('تومان')
                                    ->minValue(0),

                                TextInput::make('weight')
                                    ->label('وزن')
                                    ->numeric()
                                    ->suffix('kg')
                                    ->step(0.01)
                                    ->placeholder('مثلاً 1.5'),

                                TextInput::make('stock_quantity')
                                    ->label('موجودی انبار')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('تصاویر محصول')
                    ->description('مدیریت تصاویر و تعیین تصویر اصلی')
                    ->icon('heroicon-o-photo')
                    ->collapsible()
                    ->schema([
                        Repeater::make('images')
                            ->relationship()
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        FileUpload::make('image_path')
                                            ->label('تصویر')
                                            ->image()
                                            ->disk('public')
                                            ->directory('products')
                                            ->required()
                                            ->columnSpan(2),

                                        Toggle::make('is_primary')
                                            ->label('تصویر اصلی')
                                            ->inline(false)
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                if ($state) {
                                                    $items = $get('../../images');
                                                    if ($items) {
                                                        foreach ($items as $index => $item) {
                                                            if ($item['is_primary'] ?? false) {
                                                                $set("../../images.{$index}.is_primary", false);
                                                            }
                                                        }
                                                    }
                                                    $set('is_primary', true);
                                                }
                                            }),

                                        TextInput::make('sort_order')
                                            ->label('ترتیب')
                                            ->numeric()
                                            ->default(0),
                                    ]),
                            ])
                            ->addActionLabel('افزودن تصویر')
                            ->reorderable()
                            ->defaultItems(0)
                            ->collapsible(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
