<?php

namespace App\Filament\Pages\Reports;

use App\Enums\Permission;
use App\Filament\Pages\Concerns\HasAnalyticsFiltersForm;
use App\Filament\Widgets\Traffic\TopPagesTable;
use App\Filament\Widgets\Traffic\TrafficBrowsersChart;
use App\Filament\Widgets\Traffic\TrafficCountriesChart;
use App\Filament\Widgets\Traffic\TrafficDeviceCategoriesChart;
use App\Filament\Widgets\Traffic\TrafficOperatingSystemsChart;
use App\Filament\Widgets\Traffic\TrafficOverviewChart;
use App\Filament\Widgets\Traffic\TrafficReferrersChart;
use App\Filament\Widgets\Traffic\TrafficSummaryStats;
use App\Filament\Widgets\Traffic\TrafficUserTypesChart;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

/**
 * The full Google Analytics traffic report: summary, trend, acquisition,
 * technology and geography breakdowns for a selectable window.
 */
class TrafficReport extends Page
{
    use HasAnalyticsFiltersForm;

    protected static ?string $slug = 'reports/traffic';

    protected static ?string $title = 'گزارش ترافیک';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::AnalyticsReportView->value) ?? false;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chart-bar';
    }

    public static function getNavigationLabel(): string
    {
        return 'گزارش ترافیک';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'گزارشات مدیریتی';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedSchema::make('filtersForm'),
                Grid::make(['@lg' => 2, '@2xl' => 3])
                    ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getTrafficWidgets())),
            ]);
    }

    /**
     * @return list<class-string<Widget>>
     */
    protected function getTrafficWidgets(): array
    {
        return [
            TrafficSummaryStats::class,
            TrafficOverviewChart::class,
            TrafficUserTypesChart::class,
            TrafficDeviceCategoriesChart::class,
            TrafficBrowsersChart::class,
            TrafficOperatingSystemsChart::class,
            TrafficReferrersChart::class,
            TrafficCountriesChart::class,
            TopPagesTable::class,
        ];
    }
}
