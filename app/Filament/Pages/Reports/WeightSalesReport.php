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

class WeightSalesReport extends Page
{
    protected static ?string $slug = 'reports/weight-sales-report';

    protected static ?string $title = 'گزارش وزنی و ریالی فروش';

    protected string $view = 'filament.pages.reports.weight-sales-report';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::ManagementReportView->value) ?? false;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-scale';
    }

    public static function getNavigationLabel(): string
    {
        return 'وزنی و ریالی فروش';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'گزارشات مدیریتی';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public ?string $dateRange = 'all';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    /** @var Collection<int, array{date: string, total_rial: float, total_items: int, bar_count: int, bar_weight: float, bar_avg_weight: float, bar_rial: float, gold750_count: int, gold750_weight: float, gold750_avg_weight: float, gold750_rial: float, silver_count: int, silver_weight: float, silver_avg_weight: float, silver_rial: float}> */
    public $report;

    public bool $submitted = false;

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
            ->whereDoesntHave('items.product', static fn ($q) => $q->where('slug', 'test'))
            ->with(['items.product']);

        if ($gregorianFrom) {
            $query->whereDate('created_at', '>=', $gregorianFrom);
        }

        if ($gregorianTo) {
            $query->whereDate('created_at', '<=', $gregorianTo);
        }

        $orders = $query->get();

        $this->report = $orders
            ->groupBy(static fn (Order $order) => $order->created_at->format('Y-m-d'))
            ->map(static function ($orders, $date) {
                $allItems = $orders->flatMap->items;

                $barItems = $allItems->filter(
                    static fn ($item) => $item->product
                        && $item->product->metal_type?->value === 'gold'
                        && $item->product->form?->value === 'shammesh',
                );

                $silverItems = $allItems->filter(
                    static fn ($item) => $item->product && $item->product->metal_type?->value === 'silver',
                );

                $barWeight = (float) $barItems->sum(fn ($item) => (float) ($item->product?->weight ?? 0) * $item->quantity);
                $silverWeight = (float) $silverItems->sum(fn ($item) => (float) ($item->product?->weight ?? 0) * $item->quantity);

                $barCount = (int) $barItems->sum('quantity');
                $silverCount = (int) $silverItems->sum('quantity');

                return [
                    'date' => $date,
                    'total_rial' => (float) $allItems->sum('subtotal'),
                    'total_items' => (int) $allItems->sum('quantity'),
                    'bar_count' => $barCount,
                    'bar_weight' => $barWeight,
                    'bar_avg_weight' => $barCount > 0 ? round($barWeight / $barCount, 2) : 0,
                    'bar_rial' => (float) $barItems->sum('subtotal'),
                    'silver_count' => $silverCount,
                    'silver_weight' => $silverWeight,
                    'silver_avg_weight' => $silverCount > 0 ? round($silverWeight / $silverCount, 2) : 0,
                    'silver_rial' => (float) $silverItems->sum('subtotal'),
                ];
            })
            ->sortByDesc('date')
            ->values();
    }

    private function resolveDateRange(): array
    {
        $now = Carbon::now();

        return match ($this->dateRange) {
            'today' => [
                $now->copy()->startOfDay()->format('Y-m-d'),
                $now->copy()->endOfDay()->format('Y-m-d'),
            ],
            'week' => [
                $now->copy()->startOfWeek()->format('Y-m-d'),
                $now->copy()->endOfWeek()->format('Y-m-d'),
            ],
            'month' => [
                $now->copy()->startOfMonth()->format('Y-m-d'),
                $now->copy()->endOfMonth()->format('Y-m-d'),
            ],
            'year' => [
                $now->copy()->startOfYear()->format('Y-m-d'),
                $now->copy()->endOfYear()->format('Y-m-d'),
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
}
