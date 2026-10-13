<?php

namespace App\Filament\Pages;

use App\Enums\Inventory\InventoryMode;
use App\Enums\Permission;
use App\Services\Inventory\ProductInventorySettingsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Modules\Product\Models\Product;

class SiteWarehouse extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.site-warehouse';

    protected static ?string $title = 'انبار سایت';

    public static function canAccess(): bool
    {
        return auth()->user()?->checkPermissionTo(Permission::SerialView->value) ?? false;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-building-storefront';
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('serialImport')
                ->label('ایمپورت سریال')
                ->icon('heroicon-o-qr-code')
                ->color('success')
                ->authorize(Permission::SerialImport->value)
                ->url(fn (): string => SerialImport::getUrl())
                ->openUrlInNewTab(false),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Product::query()
                ->where('slug', '!=', 'test')
                ->withCount(['serials as available_serial_count' => fn ($query) => $query->where('status', 'available')])
                ->withSum(['manualReservations as active_manual_reserved' => fn ($query) => $query->where('status', 'reserved')], 'quantity')
                ->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label('محصول')
                    ->description(fn (Product $record): string => $record->sku ?? '')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")),

                TextColumn::make('serial_prefix')
                    ->label('پیشوند')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('inventory_mode')
                    ->label('روش موجودی')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof InventoryMode ? $state->label() : ($state ?? '—'))
                    ->color(fn ($state): string => ($state instanceof InventoryMode ? $state->value : $state) === InventoryMode::Serial->value ? 'info' : 'gray'),

                TextColumn::make('sellable_stock')
                    ->label('موجودی قابل فروش')
                    ->numeric()
                    ->color(fn ($state): string => $state > 0 ? 'success' : 'danger'),

                TextColumn::make('active_manual_reserved')
                    ->label('ظرفیت رزروشده')
                    ->numeric()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('stock_quantity')->label('ظرفیت دستی')->numeric(),
            ])
            ->recordActions([
                Action::make('updateCapacity')
                    ->label('ویرایش ظرفیت')
                    ->authorize(fn (): bool => (auth()->user()?->checkPermissionTo('product.edit') ?? false) && (auth()->user()?->checkPermissionTo('serial.manage') ?? false))
                    ->visible(fn (Product $record): bool => ($record->inventory_mode ?? InventoryMode::Manual) === InventoryMode::Manual)
                    ->schema([
                        TextInput::make('stock_quantity')->label('ظرفیت دستی')->integer()->minValue(0)->required(),
                        Textarea::make('reason')->label('دلیل تغییر')->required()->maxLength(500),
                    ])
                    ->fillForm(fn (Product $record): array => ['stock_quantity' => $record->stock_quantity])
                    ->action(function (Product $record, array $data): void {
                        try {
                            app(ProductInventorySettingsService::class)->update($record, ['stock_quantity' => $data['stock_quantity']], auth()->user(), $data['reason']);
                        } catch (ValidationException $exception) {
                            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field): array => [$this->getMountedActionSchema()->getStatePath().'.'.$field => $messages])->all());
                        }
                    }),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25);
    }
}
