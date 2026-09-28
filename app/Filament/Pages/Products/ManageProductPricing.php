<?php

namespace App\Filament\Pages\Products;

use App\Enums\Permission;
use App\Filament\Pages\Pricing\PricePreviewPage;
use App\Filament\Resources\Products\ProductResource;
use App\Services\Pricing\DynamicPriceService;
use App\Services\Pricing\LaborCalculator;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Product\Models\Product;

/**
 * WordPress-style «Products Labor Coefficients» screen: every dynamically
 * priced product against each time period × labor role, edited as a percent
 * and stored as a coefficient. Periods are switched with the same tabs used
 * on the price preview page.
 */
class ManageProductPricing extends Page
{
    protected static ?string $slug = 'products/manage-pricing';

    protected static ?string $title = 'تنظیمات ضریب';

    protected string $view = 'filament.pages.manage-product-pricing';

    /**
     * @var list<array{slug: string, name: string, start: string, end: string}>
     */
    public array $periods = [];

    /**
     * @var list<array{slug: string, name: string}>
     */
    public array $roles = [];

    /**
     * Slug of the time period shown as the active tab.
     */
    public string $activePeriod = '';

    public bool $isEditing = false;

    /**
     * @var list<array{id: int, name: string, edit_url: string, metal_label: string, weight: float}>
     */
    public array $rows = [];

    /**
     * Percent of labor per product → period → role. Kept in percent, converted
     * to a coefficient only when saved.
     *
     * @var array<int, array<string, array<string, float|string>>>
     */
    public array $values = [];

    public int $productCount = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::ProductEdit->value) ?? false;
    }

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

        $this->periods = array_values($labor->getTimePeriods());
        $this->roles = array_values($labor->getRoles());
        $this->activePeriod = $this->resolveDefaultPeriod($labor);

        $service = app(DynamicPriceService::class);

        $products = Product::query()
            ->whereNotNull('price_board_item')
            ->where('weight', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Product $product): bool => $service->isEligibleProduct($product));

        foreach ($products as $product) {
            $matrix = $product->labor_coefficients ?: [];

            $this->rows[] = [
                'id' => $product->getKey(),
                'name' => $product->name,
                'edit_url' => ProductResource::getUrl('edit', ['record' => $product]),
                'metal_label' => PricePreviewPage::BOARD_LABELS[$product->price_board_item] ?? $product->price_board_item,
                'weight' => (float) $product->weight,
            ];

            foreach ($this->periods as $period) {
                foreach ($this->roles as $role) {
                    $coefficient = (float) ($matrix[$period['slug']][$role['slug']] ?? 1.0);
                    $this->values[$product->getKey()][$period['slug']][$role['slug']] = round($coefficient * 100, 4);
                }
            }
        }

        $this->productCount = count($this->rows);
    }

    /**
     * Switch the visible time period tab.
     */
    public function setPeriod(string $periodSlug): void
    {
        if (! in_array($periodSlug, array_column($this->periods, 'slug'), true)) {
            return;
        }

        $this->activePeriod = $periodSlug;
    }

    public function toggleEdit(): void
    {
        $this->isEditing = ! $this->isEditing;
    }

    public function save(): void
    {
        if ($this->values === []) {
            Notification::make()
                ->title('خطا')
                ->body('هیچ محصولی برای بروزرسانی وجود ندارد.')
                ->danger()
                ->send();

            return;
        }

        $previous = Product::query()
            ->whereIn('id', array_keys($this->values))
            ->pluck('labor_coefficients', 'id');

        $updated = 0;

        foreach ($this->values as $productId => $periodValues) {
            $stored = $previous[$productId] ?: [];
            $matrix = [];

            foreach ($periodValues as $periodSlug => $roleValues) {
                foreach ($roleValues as $roleSlug => $percent) {
                    $matrix[$periodSlug][$roleSlug] = is_numeric($percent)
                        ? round(max(0, (float) $percent) / 100, 6)
                        : (float) ($stored[$periodSlug][$roleSlug] ?? 1.0);
                }
            }

            Product::where('id', $productId)->update(['labor_coefficients' => $matrix]);
            $updated++;
        }

        $this->isEditing = false;

        Notification::make()
            ->title('ذخیره شد')
            ->body("{$updated} محصول بروزرسانی شد.")
            ->success()
            ->send();
    }

    private function resolveDefaultPeriod(LaborCalculator $labor): string
    {
        $slugs = array_column($this->periods, 'slug');
        $current = $labor->getCurrentTimePeriod();

        return in_array($current, $slugs, true) ? $current : (string) ($slugs[0] ?? '');
    }
}
