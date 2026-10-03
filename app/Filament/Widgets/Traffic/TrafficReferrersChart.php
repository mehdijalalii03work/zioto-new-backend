<?php

namespace App\Filament\Widgets\Traffic;

use App\Support\AnalyticsLabels;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Spatie\Analytics\Period;

/**
 * Where the traffic comes from, as a horizontal bar chart.
 */
class TrafficReferrersChart extends TrafficBreakdownChart
{
    protected ?string $maxHeight = '22rem';

    protected string|BackedEnum|null $emptyStateIcon = Heroicon::OutlinedLink;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function breakdownHeading(): string
    {
        return 'منابع ورودی';
    }

    protected function dimensionKey(): string
    {
        return 'referrer';
    }

    protected function limit(): int
    {
        return 10;
    }

    protected function translate(string $value): string
    {
        return AnalyticsLabels::referrer($value);
    }

    protected function fetch(Period $period): Collection
    {
        return $this->analyticsService()->topReferrers($period, $this->limit());
    }
}
