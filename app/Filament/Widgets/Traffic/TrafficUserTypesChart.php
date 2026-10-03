<?php

namespace App\Filament\Widgets\Traffic;

use App\Support\AnalyticsLabels;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Spatie\Analytics\Period;

/**
 * New versus returning visitors, as a share of total visitors.
 */
class TrafficUserTypesChart extends TrafficBreakdownChart
{
    protected ?string $maxHeight = '14rem';

    protected string|BackedEnum|null $emptyStateIcon = Heroicon::OutlinedUserGroup;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function breakdownHeading(): string
    {
        return 'کاربران جدید در برابر بازگشتی';
    }

    protected function dimensionKey(): string
    {
        return 'type';
    }

    protected function limit(): int
    {
        return 2;
    }

    protected function translate(string $value): string
    {
        return AnalyticsLabels::userType($value);
    }

    protected function fetch(Period $period): Collection
    {
        return $this->analyticsService()->userTypes($period);
    }

    protected function metricKey(): string
    {
        return 'visitors';
    }

    protected function metricLabel(): string
    {
        return 'بازدیدکننده';
    }
}
