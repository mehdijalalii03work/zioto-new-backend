<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اطلاعات پست')
                    ->description('عنوان، دسته‌بندی و محتوای پست')
                    ->icon('heroicon-o-information-circle')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('title')
                                    ->label('عنوان پست')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true),

                                TextInput::make('slug')
                                    ->label('شناسه')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->helperText('به صورت خودکار از عنوان تولید می‌شود'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('category_id')
                                    ->label('دسته‌بندی')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('انتخاب دسته‌بندی')
                                    ->nullable(),

                                Select::make('status')
                                    ->label('وضعیت')
                                    ->options([
                                        'draft' => 'پیش‌نویس',
                                        'published' => 'منتشر شده',
                                    ])
                                    ->default('draft')
                                    ->required(),
                            ]),

                        RichEditor::make('content')
                            ->label('محتوا')
                            ->required()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'link', 'orderedList', 'bulletList',
                                'h2', 'h3', 'h4', 'blockquote',
                                'redo', 'undo',
                            ])
                            ->extraAttributes(['style' => 'min-height: 400px']),
                    ])
                    ->columnSpanFull(),

                Section::make('تصویر شاخص')
                    ->description('تصویر اصلی پست')
                    ->icon('heroicon-o-photo')
                    ->collapsible()
                    ->schema([
                        FileUpload::make('image')
                            ->label('تصویر شاخص')
                            ->image()
                            ->disk('public')
                            ->directory('blog/posts')
                            ->nullable(),
                    ])
                    ->columnSpanFull(),

                Section::make('تگ‌ها')
                    ->description('برچسب‌های پست')
                    ->icon('heroicon-o-tag')
                    ->collapsible()
                    ->schema([
                        Select::make('tags')
                            ->label('تگ‌ها')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->placeholder('انتخاب تگ‌ها'),
                    ])
                    ->columnSpanFull(),

                Section::make('زمان‌بندی انتشار')
                    ->description('تاریخ انتشار پست')
                    ->icon('heroicon-o-calendar')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Toggle::make('published_at')
                            ->label('منتشر شده')
                            ->helperText('با فعال شدن، تاریخ انتشار به زمان فعلی تنظیم می‌شود')
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state, $set) {
                                $set('published_at', $state ? now() : null);
                            }),
                    ])
                    ->columnSpanFull(),

                Section::make('تنظیمات SEO')
                    ->description('بهینه‌سازی موتور جستجو')
                    ->icon('heroicon-o-globe-alt')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('seo_title')
                                    ->label('عنوان SEO')
                                    ->maxLength(70)
                                    ->helperText('حداکثر ۷۰ کاراکتر'),

                                TextInput::make('seo_description')
                                    ->label('توضیحات SEO')
                                    ->maxLength(160)
                                    ->helperText('حداکثر ۱۶۰ کاراکتر'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
