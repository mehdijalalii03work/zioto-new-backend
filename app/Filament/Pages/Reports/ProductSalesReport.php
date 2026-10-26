<?php

namespace App\Filament\Pages\Reports;

use App\Enums\Permission;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Order\Models\Order;
use Morilog\Jalali\Jalalian;

class ProductSalesReport extends Page
{
    protected static ?string $slug = 'reports/product-sales-report';

    protected static ?string $title = 'گزارش فروش محصولات';

    protected string $view = 'filament.pages.reports.product-sales-report';

    public ?string $dateRange = 'all';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    /** @var Collection<int, array{product_id: int|null, name: string, quantity: int, metal_type: string|null, metal_label: string, form_label: string, brand_label: string}> */
    public Collection $report;

    public bool $submitted = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManagementReportView->value) ?? false;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationLabel(): string
    {
        return 'فروش محصولات';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'گزارشات مدیریتی';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public function mount(): void
    {
        $this->report = collect();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بازه زمانی')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Radio::make('dateRange')
                            ->label('بازه زمانی')
                            ->options([
                                'all' => 'همه',
                                'today' => 'امروز',
                                'week' => 'هفته جاری',
                                'month' => 'ماه جاری',
                                'year' => 'سال جاری',
                                'custom' => 'سفارشی',
                            ])
                            ->live()
                            ->default('all')
                            ->inline(),

                        Grid::make(2)
                            ->schema(fn (Get $get): array => $get('dateRange') === 'custom' ? [
                                DatePicker::make('dateFrom')
                                    ->label('از تاریخ')
                                    ->native(false)
                                    ->jalali()
                                    ->displayFormat('Y/m/d'),
                                DatePicker::make('dateTo')
                                    ->label('تا تاریخ')
                                    ->native(false)
                                    ->jalali()
                                    ->displayFormat('Y/m/d'),
                            ] : []),
                    ]),
            ]);
    }

    public function loadReport(): void
    {
        $this->submitted = true;

        [$gregorianFrom, $gregorianTo] = $this->resolveDateRange();

        $query = Order::query()
            ->whereIn('status', ['completed', 'confirmed'])
            ->whereDoesntHave('items.product', static fn ($query) => $query->where('slug', 'test'))
            ->with(['items.product.brand']);

        if ($gregorianFrom !== null) {
            $query->whereDate('created_at', '>=', $gregorianFrom);
        }

        if ($gregorianTo !== null) {
            $query->whereDate('created_at', '<=', $gregorianTo);
        }

        $items = $query->get()->flatMap->items;

        $this->report = $items
            ->groupBy(static function ($item): string {
                if ($item->product_id !== null) {
                    return 'product:'.$item->product_id;
                }

                return 'snapshot:'.mb_strtolower(trim((string) $item->product_name));
            })
            ->map(static function (Collection $items): array {
                $item = $items->first();

                return [
                    'product_id' => $item->product_id ? (int) $item->product_id : null,
                    'name' => (string) ($item->product?->name ?: $item->product_name ?: 'محصول حذف‌شده'),
                    'quantity' => (int) $items->sum(static fn ($orderItem): int => (int) $orderItem->quantity),
                    'metal_type' => $item->product?->metal_type?->value,
                    'metal_label' => $item->product?->metal_type?->label() ?? 'نامشخص',
                    'form_label' => $item->product?->form?->label() ?? 'نامشخص',
                    'brand_label' => self::brandLabel($item->product?->brand?->name, $item->product?->brand?->slug),
                ];
            })
            ->filter(static fn (array $product): bool => $product['quantity'] > 0)
            ->sort(static fn (array $a, array $b): int => ($b['quantity'] <=> $a['quantity']) ?: strnatcasecmp($a['name'], $b['name']))
            ->values();
    }

    private static function brandLabel(?string $name, ?string $slug): string
    {
        $brand = mb_strtolower(trim(($slug ?? '').' '.($name ?? '')));

        if ($brand === '') {
            return 'نامشخص';
        }

        if (str_contains($brand, 'plus') || str_contains($brand, 'پلاس')) {
            return 'زیوتو پلاس';
        }

        if (str_contains($brand, 'zioto') || str_contains($brand, 'زیوتو')) {
            return 'زیوتو';
        }

        return (string) $name;
    }

    public function canNavigateByDay(): bool
    {
        if ($this->dateRange === 'today') {
            return true;
        }

        if ($this->dateRange !== 'custom' || blank($this->dateFrom)) {
            return false;
        }

        $from = $this->parseDate($this->dateFrom);
        $to = filled($this->dateTo) ? $this->parseDate($this->dateTo) : $from;

        return $from !== null && $from === $to;
    }

    public function selectedDayLabel(): ?string
    {
        $date = $this->selectedDay();

        return $date ? Jalalian::fromCarbon($date)->format('Y/m/d') : null;
    }

    public function previousDay(): void
    {
        $this->navigateDay(-1);
    }

    public function nextDay(): void
    {
        $this->navigateDay(1);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveDateRange(): array
    {
        $now = Jalalian::now();

        return match ($this->dateRange) {
            'today' => [
                $now->toCarbon()->startOfDay()->format('Y-m-d'),
                $now->toCarbon()->endOfDay()->format('Y-m-d'),
            ],
            'week' => [
                $now->getFirstDayOfWeek()->toCarbon()->format('Y-m-d'),
                $now->getEndDayOfWeek()->toCarbon()->format('Y-m-d'),
            ],
            'month' => [
                $now->getFirstDayOfMonth()->toCarbon()->format('Y-m-d'),
                $now->getEndDayOfMonth()->toCarbon()->format('Y-m-d'),
            ],
            'year' => [
                $now->getFirstDayOfYear()->toCarbon()->format('Y-m-d'),
                $now->getEndDayOfYear()->toCarbon()->format('Y-m-d'),
            ],
            'custom' => [
                filled($this->dateFrom) ? $this->parseDate($this->dateFrom) : null,
                filled($this->dateTo) ? $this->parseDate($this->dateTo) : ($this->dateFrom ? $this->parseDate($this->dateFrom) : null),
            ],
            default => [null, null],
        };
    }

    private function parseDate(string $date): ?string
    {
        try {
            if (str_contains($date, '/')) {
                $parts = explode('/', $date);

                if (count($parts) === 3 && (int) $parts[0] < 1700) {
                    return Jalalian::fromFormat('Y/m/d', $date)->toCarbon()->format('Y-m-d');
                }
            }

            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function selectedDay(): ?Carbon
    {
        if (! $this->canNavigateByDay()) {
            return null;
        }

        if ($this->dateRange === 'today') {
            return Jalalian::now()->toCarbon()->startOfDay();
        }

        $date = $this->parseDate((string) $this->dateFrom);

        return $date ? Carbon::parse($date)->startOfDay() : null;
    }

    private function navigateDay(int $days): void
    {
        $date = $this->selectedDay();

        if ($date === null) {
            return;
        }

        $targetDate = Jalalian::fromCarbon($date->addDays($days))->format('Y/m/d');

        $this->dateRange = 'custom';
        $this->dateFrom = $targetDate;
        $this->dateTo = $targetDate;

        $this->loadReport();
    }
}
