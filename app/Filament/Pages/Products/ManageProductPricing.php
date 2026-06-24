<?php

namespace App\Filament\Pages\Products;

use Filament\Pages\Page;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Modules\Product\Models\Product;

class ManageProductPricing extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'products/manage-pricing';

    protected static ?string $title = 'مدیریت قیمت‌گذاری';

    protected string $view = 'filament.pages.manage-product-pricing';

    public static function getNavigationLabel(): string
    {
        return 'مدیریت قیمت‌گذاری';
    }

    public static function getNavigationGroup(): string
    {
        return 'مدیریت کالا';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-currency-dollar';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query())
            ->columns([
                TextColumn::make('name')
                    ->label('نام محصول')
                    ->searchable()
                    ->sortable(),
                TextInputColumn::make('weight')
                    ->label('وزن (گرم)'),
                SelectColumn::make('price_board_item')
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
                    ->searchable(),
                TextInputColumn::make('fee_business_hours')
                    ->label('اجرت (۹ تا ۱۷:۵۹)'),
                TextInputColumn::make('fee_off_hours')
                    ->label('اجرت (۱۸ تا ۸:۵۹)'),
            ]);
    }
}
