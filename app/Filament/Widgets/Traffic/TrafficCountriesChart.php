<?php

namespace App\Filament\Widgets\Traffic;

use App\Support\AnalyticsLabels;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Spatie\Analytics\Period;

/**
 * Page views per country, as a horizontal bar chart.
 */
class TrafficCountriesChart extends TrafficBreakdownChart
{
    protected ?string $maxHeight = '22rem';

    protected string|BackedEnum|null $emptyStateIcon = Heroicon::OutlinedGlobeAlt;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function breakdownHeading(): string
    {
        return 'کشورهای بازدیدکننده';
    }

    protected function dimensionKey(): string
    {
        return 'country';
    }

    protected function limit(): int
    {
        return 10;
    }

    protected function translate(string $value): string
    {
        return AnalyticsLabels::country($value);
    }

    protected function fetch(Period $period): Collection
    {
        return $this->analyticsService()->topCountries($period, $this->limit());
    }
}
