<?php

namespace App\Filament\Pages\Products;

use App\Enums\Permission;
use App\Services\Pricing\DynamicPriceService;
use App\Services\Pricing\LaborCalculator;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Modules\Product\Models\Product;

class ManageProductPricing extends Page
{
    protected static ?string $slug = 'products/manage-pricing';

    protected static ?string $title = 'تنظیمات ضریب';

    protected string $view = 'filament.pages.manage-product-pricing';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::ProductEdit->value) ?? false;
    }

    public ?array $data = [];

    public bool $isEditing = false;

    public static function getNavigationLabel(): string
    {
        return 'ضریب محصولات';
    }

    public static function getNavigationGroup(): string
    {
        return 'قیمت‌گذاری زیوتو';
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-currency-dollar';
    }

    public function mount(): void
    {
        $labor = app(LaborCalculator::class);
        $roles = $labor->getRoles();
        $periods = $labor->getTimePeriods();

        $this->form->fill([
            'products' => Product::query()
                ->orderBy('sort_order')
                ->get()
                ->map(function (Product $p) use ($roles, $periods) {
                    $matrix = $p->labor_coefficients ?: [];
                    $state = [
                        'id' => $p->id,
                        'name' => $p->name,
                        'weight' => $p->weight,
                        'price_board_item' => $p->price_board_item,
                        'dynamic_pricing_enabled' => (bool) $p->dynamic_pricing_enabled,
                    ];

                    foreach ($periods as $period) {
                        foreach ($roles as $role) {
                            $key = "coef_{$period['slug']}_{$role['slug']}";
                            $state[$key] = $matrix[$period['slug']][$role['slug']] ?? 1.0;
                        }
                    }

                    return $state;
                })
                ->toArray(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $labor = app(LaborCalculator::class);
        $roles = $labor->getRoles();
        $periods = $labor->getTimePeriods();

        $matrixFields = [];
        foreach ($periods as $period) {
            foreach ($roles as $role) {
                $matrixFields[] = Forms\Components\TextInput::make("coef_{$period['slug']}_{$role['slug']}")
                    ->label("{$period['name']} · {$role['name']}")
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->default(1.0)
                    ->disabled(fn () => ! $this->isEditing)
                    ->dehydrated();
            }
        }

        return $schema
            ->schema([
                Forms\Components\Repeater::make('products')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('نام محصول')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('weight')
                            ->label('وزن (گرم)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('price_board_item')
                            ->label('آیتم تابلو قیمت')
                            ->options(DynamicPriceService::METAL_OPTIONS)
                            ->searchable()
                            ->disabled(fn () => ! $this->isEditing)
                            ->dehydrated(),

                        Forms\Components\Toggle::make('dynamic_pricing_enabled')
                            ->label('پویا')
                            ->inline(false)
                            ->disabled(fn () => ! $this->isEditing)
                            ->dehydrated(),

                        ...$matrixFields,
                    ])
                    ->columns([
                        'default' => 5,
                        'sm' => 5,
                        'lg' => 6,
                        '2xl' => 8,
                    ])
                    ->defaultItems(0)
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function toggleEdit(): void
    {
        $this->isEditing = ! $this->isEditing;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (empty($data['products'])) {
            Notification::make()
                ->title('خطا')
                ->body('هیچ محصولی برای بروزرسانی وجود ندارد.')
                ->danger()
                ->send();

            return;
        }

        $labor = app(LaborCalculator::class);
        $roles = $labor->getRoles();
        $periods = $labor->getTimePeriods();
        $updated = 0;

        foreach ($data['products'] as $item) {
            $matrix = [];
            foreach ($periods as $period) {
                foreach ($roles as $role) {
                    $key = "coef_{$period['slug']}_{$role['slug']}";
                    $matrix[$period['slug']][$role['slug']] = (float) ($item[$key] ?? 1.0);
                }
            }

            Product::where('id', $item['id'])->update([
                'price_board_item' => $item['price_board_item'] ?? null,
                'dynamic_pricing_enabled' => (bool) ($item['dynamic_pricing_enabled'] ?? false),
                'labor_coefficients' => $matrix,
            ]);
            $updated++;
        }

        $this->isEditing = false;

        Notification::make()
            ->title('ذخیره شد')
            ->body("{$updated} محصول بروزرسانی شد.")
            ->success()
            ->send();
    }
}
