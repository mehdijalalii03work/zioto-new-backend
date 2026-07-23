<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\Product\Ayar;
use App\Enums\Product\MetalType;
use App\Enums\Product\ProductShape;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('اطلاعات عمومی')
                    ->description('نام، آدرس محصول و توضیحات محصول')
                    ->icon('heroicon-o-information-circle')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('name')
                                    ->label('نام محصول')
                                    ->required()
                                    ->live(onBlur: true),

                                TextInput::make('slug')
                                    ->label('آدرس محصول')
                                    ->required()
                                    ->unique(ignoreRecord: true),

                                TextInput::make('sku')
                                    ->label('کد اختصاصی محصول')
                                    ->unique(ignoreRecord: true),

                                Select::make('status')
                                    ->label('وضعیت انتشار')
                                    ->options([
                                        'published' => 'منشتر شده',
                                        'draft' => 'پیش‌نویس',
                                    ])
                                    ->default('published')
                                    ->required(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('tokeniko_sku')
                                    ->label('کد توکنیکو')
                                    ->placeholder('مثلاً zioto-gold-bar-1gram-995')
                                    ->helperText('نام محصول در API توکنیکو (حالت مستقیم)'),

                                TextInput::make('tapsi_product_id')
                                    ->label('کد تپسی شاپ')
                                    ->placeholder('مثلاً ZGB5-0001-0')
                                    ->helperText('شناسه محصول در تپسی شاپ'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('metal_type')
                                    ->label('نوع')
                                    ->options(collect(MetalType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                                    ->placeholder('انتخاب نوع')
                                    ->required()
                                    ->live(),

                                Select::make('form')
                                    ->label('نوع کالا')
                                    ->options(collect(ProductShape::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                                    ->placeholder('انتخاب نوع کالا')
                                    ->required()
                                    ->live(),

                                Select::make('ayar')
                                    ->label('عیار')
                                    ->options(collect(Ayar::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                                    ->placeholder('انتخاب عیار')
                                    ->required()
                                    ->live(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('category_id')
                                    ->label('دسته‌بندی')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('انتخاب دسته‌بندی'),

                                Select::make('brand_id')
                                    ->label('برند')
                                    ->relationship('brand', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('انتخاب برند'),
                            ]),

                        RichEditor::make('description')
                            ->label('توضیحات کامل')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('products/content')
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
                                ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
                                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                                ['attachFiles', 'table', 'horizontalRule'],
                                ['highlight', 'textColor', 'details'],
                                ['undo', 'redo'],
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
                                Select::make('price_type')
                                    ->label('نوع قیمت‌گذاری')
                                    ->options([
                                        'fixed' => 'قیمت ثابت',
                                        'dynamic' => 'قیمت پویا (از تابلو قیمت)',
                                    ])
                                    ->default('fixed')
                                    ->live()
                                    ->required(),

                                TextInput::make('price')
                                    ->label('قیمت')
                                    ->required(fn ($get) => ($get('price_type') ?? 'fixed') === 'fixed')
                                    ->disabled(fn ($get) => ($get('price_type') ?? 'fixed') === 'dynamic')
                                    ->dehydrated(fn ($get) => ($get('price_type') ?? 'fixed') === 'fixed')
                                    ->numeric()
                                    ->suffix('ریال')
                                    ->minValue(0),

                                TextInput::make('weight')
                                    ->label('وزن')
                                    ->numeric()
                                    ->suffix('گرم')
                                    ->step(0.01)
                                    ->placeholder('مثلاً 10'),

                                TextInput::make('stock_quantity')
                                    ->label('موجودی انبار')
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('تنظیمات قیمت‌گذاری پویا')
                    ->description('تنها برای قیمت‌گذاری پویا')
                    ->icon('heroicon-o-calculator')
                    ->collapsible()
                    ->visible(fn ($get) => ($get('price_type') ?? 'fixed') === 'dynamic')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('price_board_item')
                                    ->label('آیتم تابلو قیمت')
                                    ->options([
                                        'Gold995' => 'طلای ۹۹۵ (شمش)',
                                        'Gold999' => 'طلای ۹۹۹',
                                        'Gold9999' => 'طلای ۹۹۹.۹',
                                        'Gold750' => 'طلای ۱۸ عیار (۷۵۰)',
                                        'Gold705' => 'طلای ۱۷.۵ عیار (۷۰۵)',
                                        'Silver990' => 'نقره ۹۹۰',
                                        'Silver999' => 'نقره ۹۹۹',
                                        'Silver9999' => 'نقره ۹۹۹.۹',
                                        'Silver 925' => 'نقره ۹۲۵',
                                        'Euro' => 'یورو',
                                        'USDollar' => 'دلار آمریکا',
                                    ])
                                    ->placeholder('انتخاب آیتم')
                                    ->searchable(),

                                TextInput::make('fee_off_hours')
                                    ->label('اجرت (ساعت ۱۸ تا ۸:۵۹)')
                                    ->numeric()
                                    ->suffix('٪')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->nullable()
                                    ->disabled()
                                    ->dehydrated()
                                    ->placeholder('مثلاً 3.5')
                                    ->helperText('از صفحه مدیریت قیمت‌گذاری قابل تغییر است'),

                                TextInput::make('fee_business_hours')
                                    ->label('اجرت (ساعت ۹ تا ۱۷:۵۹)')
                                    ->numeric()
                                    ->suffix('٪')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->nullable()
                                    ->disabled()
                                    ->dehydrated()
                                    ->placeholder('مثلاً 5')
                                    ->helperText('از صفحه مدیریت قیمت‌گذاری قابل تغییر است'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('تنظیمات حسابفا')
                    ->description('همگام‌سازی موجودی با حسابفا')
                    ->icon('heroicon-o-arrow-path')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('hesabfa_physical_stock')
                                    ->label('موجودی فیزیکی (حسابفا)')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('از حسابفا دریافت می‌شود'),

                                TextInput::make('hesabfa_reserved_stock')
                                    ->label('موجودی رزرو شده')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('سفارشات در حال پردازش'),

                                TextInput::make('sellable_stock')
                                    ->label('موجودی قابل فروش')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('فیزیکی - رزرو شده - رزرو دستی'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextInput::make('hesabfa_manual_reserved')
                                    ->label('رزرو دستی')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->helperText('تعداد رزرو شده توسط مدیر'),

                                Toggle::make('hesabfa_exclude_from_sync')
                                    ->label('غیرفعال کردن سینک')
                                    ->helperText('موجودی این محصول از حسابفا سینک نشود')
                                    ->inline(false),

                                Toggle::make('hesabfa_stock_locked')
                                    ->label('قفل سینک')
                                    ->helperText('قفل شده وقتی موجودی دستی صفر شود')
                                    ->inline(false),
                            ]),
                        Grid::make(1)
                            ->schema([
                                TextInput::make('hesabfa_stock_synced_at')
                                    ->label('آخرین همگام‌سازی')
                                    ->disabled()
                                    ->dehydrated()
                                    ->helperText('تاریخ آخرین دریافت موجودی از حسابفا'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
