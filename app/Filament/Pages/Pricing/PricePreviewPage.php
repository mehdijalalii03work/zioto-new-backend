<?php

namespace App\Filament\Pages\Pricing;

use App\Enums\Permission;
use App\Services\Pricing\DynamicPriceService;
use App\Services\Pricing\LaborCalculator;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Modules\Product\Models\Product;

class PricePreviewPage extends Page
{
    protected static ?string $slug = 'pricing/preview';

    protected static ?string $title = 'پیش‌نمایش قیمت';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'پیش‌نمایش قیمت';

    protected static string|\UnitEnum|null $navigationGroup = 'قیمت‌گذاری زیوتو';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.pricing.price-preview';

    public array $data = [];

    public ?array $preview = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::PricingView->value) ?? false;
    }

    public function mount(): void
    {
        $labor = app(LaborCalculator::class);

        $this->form->fill([
            'product_id' => Product::query()->where('price_type', 'dynamic')->value('id'),
            'role' => 'basic',
            'time_period' => $labor->getCurrentTimePeriod(),
        ]);

        $this->runPreview();
    }

    public function form(Schema $schema): Schema
    {
        $labor = app(LaborCalculator::class);
        $roleOptions = collect($labor->getRoles())->mapWithKeys(fn (array $r) => [$r['slug'] => $r['name']]);
        $periodOptions = collect($labor->getTimePeriods())->mapWithKeys(fn (array $p) => [$p['slug'] => $p['name']]);

        return $schema
            ->components([
                Grid::make(3)->schema([
                    Select::make('product_id')
                        ->label('محصول')
                        ->options(fn () => Product::query()
                            ->whereNotNull('price_board_item')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray())
                        ->required()
                        ->live(),

                    Select::make('role')
                        ->label('نقش اجرت')
                        ->options($roleOptions->toArray())
                        ->required()
                        ->live(),

                    Select::make('time_period')
                        ->label('بازه زمانی')
                        ->options($periodOptions->toArray())
                        ->required()
                        ->live(),
                ]),
            ])
            ->statePath('data');
    }

    public function runPreview(): void
    {
        $state = $this->data;
        $productId = $state['product_id'] ?? null;

        if (! $productId) {
            $this->preview = null;

            return;
        }

        $product = Product::find($productId);
        if (! $product) {
            $this->preview = null;

            return;
        }

        $this->preview = app(DynamicPriceService::class)->detailsFor(
            $product,
            $state['role'] ?? 'basic',
            $state['time_period'] ?? null,
        );
    }
}
