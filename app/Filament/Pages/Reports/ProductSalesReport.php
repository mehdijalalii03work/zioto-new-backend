<?php

namespace App\Filament\Pages\Reports;

use App\Enums\Permission;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
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

    public int $periodOffset = 0;

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

    public function updatedDateRange(): void
    {
        $this->periodOffset = 0;
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
        if (! $this->canNavigateToNextDay()) {
            return;
        }

        $this->navigateDay(1);
    }

    public function canNavigateToNextDay(): bool
    {
        $selectedDay = $this->selectedDay();

        return $selectedDay !== null && $selectedDay->lt(Jalalian::now()->toCarbon()->startOfDay());
    }

    public function canNavigateByPeriod(): bool
    {
        return in_array($this->dateRange, ['week', 'month', 'year'], true);
    }

    public function canNavigateToNextPeriod(): bool
    {
        return $this->canNavigateByPeriod() && $this->periodOffset < 0;
    }

    public function selectedPeriodLabel(): ?string
    {
        if (! $this->canNavigateByPeriod()) {
            return null;
        }

        [$start, $end] = $this->selectedPeriodDates();

        return match ($this->dateRange) {
            'week' => 'گزارش هفته '.Jalalian::fromCarbon($start->toCarbon())->format('Y/m/d').' تا '.Jalalian::fromCarbon($end->toCarbon())->format('Y/m/d'),
            'month' => 'گزارش ماه '.Jalalian::fromCarbon($start->toCarbon())->format('Y/m'),
            'year' => 'گزارش سال '.Jalalian::fromCarbon($start->toCarbon())->format('Y'),
        };
    }

    public function previousPeriod(): void
    {
        $this->navigatePeriod(-1);
    }

    public function nextPeriod(): void
    {
        if (! $this->canNavigateToNextPeriod()) {
            return;
        }

        $this->navigatePeriod(1);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveDateRange(): array
    {
        return match ($this->dateRange) {
            'week', 'month', 'year' => array_map(
                static fn (Jalalian $date): string => $date->toCarbon()->format('Y-m-d'),
                $this->selectedPeriodDates(),
            ),
            'today' => [
                Jalalian::now()->toCarbon()->startOfDay()->format('Y-m-d'),
                Jalalian::now()->toCarbon()->endOfDay()->format('Y-m-d'),
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

    private function navigatePeriod(int $offset): void
    {
        if (! $this->canNavigateByPeriod()) {
            return;
        }

        $targetOffset = $this->periodOffset + $offset;

        if ($targetOffset > 0) {
            return;
        }

        $this->periodOffset = $targetOffset;
        $this->loadReport();
    }

    /**
     * @return array{0: Jalalian, 1: Jalalian}
     */
    private function selectedPeriodDates(): array
    {
        $now = Jalalian::now();

        if ($this->dateRange === 'week') {
            $start = $now->getFirstDayOfWeek();
            $start = $this->periodOffset < 0 ? $start->subDays(abs($this->periodOffset) * 7) : $start;

            return [$start, $start->addDays(6)];
        }

        if ($this->dateRange === 'month') {
            $start = $now->getFirstDayOfMonth();
            $start = $this->periodOffset < 0 ? $start->subMonths(abs($this->periodOffset)) : $start;

            return [$start, $start->getEndDayOfMonth()];
        }

        if ($this->dateRange === 'year') {
            $start = $now->getFirstDayOfYear();
            $start = $this->periodOffset < 0 ? $start->subYears(abs($this->periodOffset)) : $start;

            return [$start, $start->getEndDayOfYear()];
        }

        return [$now, $now];
    }
}
