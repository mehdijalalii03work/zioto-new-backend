<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Product\Models\ProductCategory;

class ProductCategoryForm
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
                        Grid::make(3)
                            ->schema([
                                TextInput::make('name')
                                    ->label('نام دسته‌بندی')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true),

                                TextInput::make('slug')
                                    ->label('آدرس محصول')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->helperText('به صورت خودکار از نام تولید می‌شود'),

                                Select::make('parent_id')
                                    ->label('دسته‌بندی والد')
                                    ->searchable()
                                    ->placeholder('بدون والد (دسته اصلی)')
                                    ->options(function (?ProductCategory $record = null): array {
                                        $query = ProductCategory::query()
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
                            ]),

                        RichEditor::make('description')
                            ->label('توضیحات')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'link', 'orderedList', 'bulletList',
                                'h2', 'h3', 'blockquote', 'redo', 'undo',
                            ])
                            ->extraAttributes(['style' => 'min-height: 300px']),
                    ])
                    ->columnSpanFull(),

                Section::make('تنظیمات ظاهری')
                    ->description('آیکون، رنگ و ترتیب نمایش')
                    ->icon('heroicon-o-paint-brush')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('icon')
                                    ->label('آیکون')
                                    ->searchable()
                                    ->placeholder('انتخاب آیکون')
                                    ->options([
                                        'heroicon-o-tag' => 'برچسب',
                                        'heroicon-o-star' => 'ستاره',
                                        'heroicon-o-heart' => 'قلب',
                                        'heroicon-o-sparkles' => 'ویژه',
                                        'heroicon-o-cube' => 'جعبه',
                                        'heroicon-o-archive-box' => 'بایگانی',
                                        'heroicon-o-gift' => 'هدیه',
                                        'heroicon-o-bookmark' => 'نشانک',
                                        'heroicon-o-shopping-bag' => 'کیف خرید',
                                        'heroicon-o-rocket-launch' => 'راکت',
                                        'heroicon-o-globe-alt' => 'جهان',
                                        'heroicon-o-camera' => 'دوربین',
                                        'heroicon-o-device-phone-mobile' => 'موبایل',
                                        'heroicon-o-computer-desktop' => 'کامپیوتر',
                                        'heroicon-o-watch' => 'ساعت',
                                    ]),

                                Select::make('color')
                                    ->label('رنگ')
                                    ->searchable()
                                    ->placeholder('انتخاب رنگ')
                                    ->options([
                                        'primary' => 'آبی',
                                        'success' => 'سبز',
                                        'warning' => 'نارنجی',
                                        'danger' => 'قرمز',
                                        'info' => 'آبی روشن',
                                        'gray' => 'خاکستری',
                                        'purple' => 'بنفش',
                                        'pink' => 'صورتی',
                                        'teal' => 'فیروزه‌ای',
                                        'amber' => 'کهربایی',
                                        'indigo' => 'نیلی',
                                        'lime' => 'لیمویی',
                                    ]),

                                Toggle::make('is_active')
                                    ->label('فعال')
                                    ->default(true)
                                    ->inline(false),
                            ]),
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

                                RichEditor::make('seo_description')
                                    ->label('توضیحات SEO')
                                    ->toolbarButtons(['bold', 'italic'])
                                    ->helperText('حداکثر ۱۶۰ کاراکتر')
                                    ->extraAttributes(['style' => 'min-height: 100px']),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function buildOptions(ProductCategory $category, ?ProductCategory $record, string $prefix = ''): array
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
