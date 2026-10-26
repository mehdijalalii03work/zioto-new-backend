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

    /** @var Collection<int, array{product_id: int|null, name: string, quantity: int}> */
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
            ->with(['items.product']);

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
                ];
            })
            ->filter(static fn (array $product): bool => $product['quantity'] > 0)
            ->sort(static fn (array $a, array $b): int => ($b['quantity'] <=> $a['quantity']) ?: strnatcasecmp($a['name'], $b['name']))
            ->values();
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
}
