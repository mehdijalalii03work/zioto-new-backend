<?php

namespace App\Filament\Resources\BlogCategories\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Blog\Models\BlogCategory;

class BlogCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اطلاعات دسته‌بندی')
                    ->description('نام، شناسه و توضیحات دسته‌بندی')
                    ->icon('heroicon-o-information-circle')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('نام دسته‌بندی')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true),

                                TextInput::make('slug')
                                    ->label('شناسه')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->helperText('به صورت خودکار از نام تولید می‌شود'),
                            ]),

                        Select::make('parent_id')
                            ->label('دسته‌بندی والد')
                            ->searchable()
                            ->placeholder('بدون والد (دسته اصلی)')
                            ->options(function (?BlogCategory $record = null): array {
                                $query = BlogCategory::query()
                                    ->whereNull('parent_id')
                                    ->with('children');

                                if ($record?->exists) {
                                    $query->where('id', '!=', $record->id);
                                }

                                $categories = $query->get();

                                return $categories
                                    ->mapWithKeys(fn ($cat) => static::buildOptions($cat, $record))
                                    ->toArray();
                            }),

                        RichEditor::make('description')
                            ->label('توضیحات')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'link', 'orderedList', 'bulletList',
                                'h2', 'h3', 'blockquote', 'redo', 'undo',
                            ])
                            ->extraAttributes(['style' => 'min-height: 200px']),
                    ])
                    ->columnSpanFull(),

                Section::make('تنظیمات')
                    ->description('فعال‌سازی و ترتیب نمایش')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsible()
                    ->schema([
                        Toggle::make('is_active')
                            ->label('فعال')
                            ->default(true)
                            ->inline(false),
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

    private static function buildOptions(BlogCategory $category, ?BlogCategory $record, string $prefix = ''): array
    {
        $excludeId = $record?->exists ? $record->id : null;

        $options = [];

        if ($category->id !== $excludeId) {
            $label = $prefix ? "{$prefix} {$category->name}" : $category->name;
            $options[$category->id] = $label;
        }

        foreach ($category->children as $child) {
            $childPrefix = $prefix ? "{$prefix}—" : '—';
            $options += static::buildOptions($child, $record, $childPrefix);
        }

        return $options;
    }
}
