<?php

namespace App\Filament\Widgets\Traffic;

use App\Support\AnalyticsLabels;
use Illuminate\Support\Collection;
use Spatie\Analytics\Period;

/**
 * Page views per browser, as a horizontal bar chart.
 */
class TrafficBrowsersChart extends TrafficBreakdownChart
{
    protected function getType(): string
    {
        return 'bar';
    }

    protected function breakdownHeading(): string
    {
        return 'مرورگرهای پرکاربرد';
    }

    protected function dimensionKey(): string
    {
        return 'browser';
    }

    protected function limit(): int
    {
        return 6;
    }

    protected function translate(string $value): string
    {
        return AnalyticsLabels::unknown($value);
    }

    protected function fetch(Period $period): Collection
    {
        return $this->analyticsService()->topBrowsers($period, $this->limit());
    }
}
