<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Product\Models\Product;

class SiteWarehouse extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.site-warehouse';

    protected static ?string $title = 'انبار سایت';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::HesabfaView->value) ?? false;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-archive-box';
    }

    public static function getNavigationLabel(): string
    {
        return 'انبار سایت';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'مدیریت کالا';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Product::query()
                ->where('price_type', 'dynamic')
                ->where('slug', '!=', 'test')
                ->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label('محصول')
                    ->description(fn (Product $record): string => $record->sku ?? '')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")),

                TextInputColumn::make('stock_quantity')
                    ->label('موجودی انبار')
                    ->type('number')
                    ->rules(['integer', 'min:0'])
                    ->sortable(),

                TextColumn::make('hesabfa_physical_stock')
                    ->label('موجودی حسابفا')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                ToggleColumn::make('hesabfa_exclude_from_sync')
                    ->label('غیرفعال کردن سینک'),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }
}
