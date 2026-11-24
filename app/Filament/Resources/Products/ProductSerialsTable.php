<?php

namespace App\Filament\Resources\Products;

use App\Models\RahkaranSerial;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Modules\Product\Models\Product;

class ProductSerialsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public Product $product;

    public function mount(Product $product): void
    {
        Gate::authorize('view', $product);
        $this->product = $product;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                Gate::authorize('view', $this->product);

                return RahkaranSerial::query()
                    ->where('product_id', $this->product->base_product_id ?? $this->product->id)
                    ->whereIn('status', ['available', 'reserved'])
                    ->with('orderItem.order');
            })
            ->columns([
                TextColumn::make('serial')->label('سریال')->searchable()->copyable(),
                TextColumn::make('status')->label('وضعیت')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'reserved' ? 'رزروشده' : 'آزاد')
                    ->color(fn (string $state): string => $state === 'reserved' ? 'warning' : 'success'),
                TextColumn::make('orderItem.order.order_number')->label('شماره سفارش')->placeholder('—'),
                TextColumn::make('synced_at')->label('آخرین دریافت از راهکاران')->jalaliDateTime('H:i Y/m/d'),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')
                    ->options(['available' => 'آزاد', 'reserved' => 'رزروشده']),
            ])
            ->defaultSort('serial')
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('سریال آزاد یا رزروشده‌ای ثبت نشده است');
    }

    public function render(): View
    {
        return view('filament.resources.products.serials-table');
    }
}
