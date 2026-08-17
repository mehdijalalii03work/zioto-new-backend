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

class TotalInvoiceReport extends Page
{
    protected static ?string $slug = 'reports/total-invoice-report';

    protected static ?string $title = 'گزارش مجموع فاکتورها';

    protected string $view = 'filament.pages.reports.total-invoice-report';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::ManagementReportView->value) ?? false;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-chart-bar';
    }

    public static function getNavigationLabel(): string
    {
        return 'مجموع فاکتورها';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'گزارشات مدیریتی';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public ?string $dateRange = 'all';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    /** @var Collection<int, array{date: string, invoice_count: int, net_amount: float, gold_count: int, gold_amount: float, silver_count: int, silver_amount: float, total_count: int}> */
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

                $goldItems = $allItems->filter(
                    static fn ($item) => $item->product && $item->product->metal_type?->value === 'gold',
                );

                $silverItems = $allItems->filter(
                    static fn ($item) => $item->product && $item->product->metal_type?->value === 'silver',
                );

                return [
                    'date' => $date,
                    'invoice_count' => $orders->count(),
                    'net_amount' => $orders->sum('total_amount'),
                    'gold_count' => (int) $goldItems->sum('quantity'),
                    'gold_amount' => (float) $goldItems->sum('subtotal'),
                    'silver_count' => (int) $silverItems->sum('quantity'),
                    'silver_amount' => (float) $silverItems->sum('subtotal'),
                    'total_count' => (int) $allItems->sum('quantity'),
                ];
            })
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
