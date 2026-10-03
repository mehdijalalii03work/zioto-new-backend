<?php

namespace App\Filament\Widgets\Traffic;

use App\Support\AnalyticsLabels;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Spatie\Analytics\Period;

/**
 * Desktop / mobile / tablet split of page views.
 */
class TrafficDeviceCategoriesChart extends TrafficBreakdownChart
{
    protected ?string $maxHeight = '14rem';

    protected string|BackedEnum|null $emptyStateIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function breakdownHeading(): string
    {
        return 'دستگاه‌های استفاده‌کنندگان';
    }

    protected function dimensionKey(): string
    {
        return 'device';
    }

    protected function limit(): int
    {
        return 6;
    }

    protected function translate(string $value): string
    {
        return AnalyticsLabels::deviceCategory($value);
    }

    protected function fetch(Period $period): Collection
    {
        return $this->analyticsService()->topDeviceCategories($period, $this->limit());
    }
}
