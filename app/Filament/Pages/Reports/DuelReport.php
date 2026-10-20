<?php

namespace App\Filament\Pages\Reports;

use App\Support\Platform;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Modules\Order\Models\Order;

class DuelReport extends WeightSalesReport
{
    protected static ?string $slug = 'reports/duel';

    protected static ?string $title = 'دوئل';

    protected string $view = 'filament.pages.reports.duel';

    /** @var Collection<string, Collection<int, array<string, int|float|string>>> */
    public $reports;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrows-right-left';
    }

    public static function getNavigationLabel(): string
    {
        return 'دوئل';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public function mount(): void
    {
        parent::mount();

        $this->reports = collect([
            'site' => collect(),
            'tapsi' => collect(),
            'digipay' => collect(),
        ]);
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

        $query = Order::withoutTenantScope()
            ->where(static function ($query): void {
                $query->whereIn('status', ['completed', 'confirmed'])
                    ->orWhere(static function ($query): void {
                        $query->where('platform', Platform::TAPSI)
                            ->where('status', 'pending');
                    });
            })
            ->whereDoesntHave('items.product', static fn ($query) => $query->where('slug', 'test'))
            ->with(['items.product', 'payments']);

        if ($gregorianFrom) {
            $query->whereDate('created_at', '>=', $gregorianFrom);
        }

        if ($gregorianTo) {
            $query->whereDate('created_at', '<=', $gregorianTo);
        }

        $orders = $query->get();

        $this->reports = collect([
            'site' => $this->buildReportRows($orders->filter(
                static fn (Order $order): bool => $order->platform === Platform::MAIN
                    && $order->payments->contains('gateway', 'parsian')
                    && ! $order->payments->contains('gateway', 'digipay'),
            )),
            'digipay' => $this->buildReportRows($orders->filter(
                static fn (Order $order): bool => $order->platform !== Platform::TAPSI
                    && $order->payments->contains('gateway', 'digipay'),
            )),
            'tapsi' => $this->buildReportRows($orders->filter(
                static fn (Order $order): bool => $order->platform === Platform::TAPSI,
            )),
        ]);
    }
}
