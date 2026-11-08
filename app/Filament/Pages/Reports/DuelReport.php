<?php

namespace App\Filament\Pages\Reports;

use App\Models\Setting;
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

    public ?string $tapsiSyncStatus = null;

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
            'smartis' => collect(),
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
        $this->tapsiSyncStatus = Setting::getValue('tapsi_duel_last_error')
            ?: (Setting::getValue('tapsi_duel_backfill_complete') === '1'
                ? 'آخرین همگام‌سازی: '.(Setting::getValue('tapsi_duel_last_success_at') ?: 'نامشخص')
                : 'واردسازی سوابق تپسی‌شاپ هنوز کامل نشده است.');

        $this->reports = collect([
            'site' => $this->buildReportRows($orders->filter(
                static fn (Order $order): bool => $order->platform === Platform::MAIN
                    && $order->payments->contains('gateway', 'parsian')
                    && ! $order->payments->contains('gateway', 'digipay')
                    && ! $order->payments->contains('gateway', 'smartis'),
            )),
            'digipay' => $this->buildReportRows($orders->filter(
                static fn (Order $order): bool => $order->platform !== Platform::TAPSI
                    && $order->payments->contains('gateway', 'digipay'),
            )),
            'smartis' => $this->buildReportRows($orders->filter(
                static fn (Order $order): bool => $order->platform !== Platform::TAPSI
                    && $order->payments->contains('gateway', 'smartis'),
            )),
            'tapsi' => $this->buildReportRows($orders->filter(
                static fn (Order $order): bool => $order->platform === Platform::TAPSI
                    && in_array((string) $order->tapsi_order_status_id, ['4', '9'], true),
            )),
        ]);
    }
}
