<?php

namespace App\Filament\Widgets\Traffic;

use App\Filament\Widgets\Concerns\InteractsWithAnalyticsPeriod;
use App\Services\Analytics\TrafficSummaryReport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Headline traffic numbers for the selected window, each with a period-over-period
 * trend and, where meaningful, a sparkline of the daily series.
 */
class TrafficSummaryStats extends StatsOverviewWidget
{
    use InteractsWithAnalyticsPeriod;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'خلاصه ترافیک';

    protected ?TrafficSummaryReport $summaryReport = null;

    protected ?Collection $dailySeries = null;

    public function getDescription(): ?string
    {
        return $this->isAnalyticsAvailable()
            ? 'بازه '.$this->analyticsWindowLabel()
            : $this->unavailableDescription();
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        if (! $this->isAnalyticsAvailable()) {
            return [$this->problemStat($this->unavailableHeading(), $this->unavailableDescription())];
        }

        $current = $this->summary()->current;

        if ($this->analyticsFailureReason() !== null) {
            return [$this->problemStat($this->analyticsErrorHeading(), $this->analyticsFailureReason())];
        }

        if ($current->isEmpty()) {
            return [$this->problemStat('در این بازه داده‌ای ثبت نشده', $this->emptyWindowExplanation())];
        }

        return [
            $this->stat(
                label: 'بازدیدکننده یکتا',
                value: number_format($current->visitors),
                icon: 'heroicon-m-user-group',
                metric: 'visitors',
                sparklineKey: 'visitors',
            ),
            $this->stat(
                label: 'بازدید صفحه',
                value: number_format($current->pageViews),
                icon: 'heroicon-m-eye',
                metric: 'pageViews',
                sparklineKey: 'pageViews',
            ),
            $this->stat(
                label: 'نشست',
                value: number_format($current->sessions),
                icon: 'heroicon-m-signal',
                metric: 'sessions',
            ),
            Stat::make('نرخ پرش', $this->formatPercentage($current->bounceRateAsPercentage()))
                ->description($this->trendDescription('bounceRate'))
                ->descriptionIcon($this->trendIcon('bounceRate', lowerIsBetter: true))
                ->color($this->trendColor('bounceRate', lowerIsBetter: true))
                ->icon('heroicon-m-arrow-uturn-left'),
            Stat::make('میانگین زمان نشست', $current->averageSessionDurationForHumans())
                ->description($this->trendDescription('averageSessionDuration'))
                ->descriptionIcon($this->trendIcon('averageSessionDuration'))
                ->color($this->trendColor('averageSessionDuration'))
                ->icon('heroicon-m-clock'),
            Stat::make('صفحه در هر نشست', number_format($current->pagesPerSession(), 2))
                ->description($this->trendDescription('pagesPerSession'))
                ->descriptionIcon($this->trendIcon('pagesPerSession'))
                ->color($this->trendColor('pagesPerSession'))
                ->icon('heroicon-m-document-text'),
        ];
    }

    protected function summary(): TrafficSummaryReport
    {
        return $this->summaryReport ??= $this->analyticsService()->summary($this->analyticsWindow());
    }

    /**
     * @return Collection<int, array{date: Carbon, visitors: int, pageViews: int}>
     */
    protected function dailySeries(): Collection
    {
        return $this->dailySeries ??= $this->analyticsService()->dailyTraffic($this->analyticsWindow());
    }

    /**
     * Explains an empty window: either the range is genuinely quiet, or the
     * configured property has simply not received traffic — which is the usual
     * symptom of a stale `ANALYTICS_PROPERTY_ID`.
     */
    private function emptyWindowExplanation(): string
    {
        $last = $this->analyticsService()->lastRecordedVisit();

        if ($last === null) {
            return sprintf(
                'در ۱۴ ماه گذشته هیچ بازدیدی در پراپرتی %s ثبت نشده است. مطمئن شوید ANALYTICS_PROPERTY_ID مربوط به پراپرتی‌ای است که سایت روی آن کد گوگل آنالیتیکس را نصب کرده است.',
                $this->analyticsService()->propertyId() ?? '—',
            );
        }

        return sprintf(
            'بازه «%s» خالی است؛ آخرین بازدید ثبت‌شده در این پراپرتی %s (%s بازدید) بوده است. بازه را تغییر دهید یا مقدار ANALYTICS_PROPERTY_ID را بررسی کنید.',
            $this->analyticsWindowLabel(),
            $this->jalaliDateLabel($last['date'], withYear: true),
            number_format($last['pageViews']),
        );
    }

    /**
     * A single placeholder stat replacing the numbers, so a broken connection is
     * never mistaken for a quiet period.
     */
    private function problemStat(string $label, ?string $reason): Stat
    {
        return Stat::make($label, '—')
            ->description($reason)
            ->descriptionIcon('heroicon-m-exclamation-triangle')
            ->color('warning');
    }

    private function stat(string $label, string $value, string $icon, string $metric, ?string $sparklineKey = null): Stat
    {
        $stat = Stat::make($label, $value)
            ->description($this->trendDescription($metric))
            ->descriptionIcon($this->trendIcon($metric))
            ->color($this->trendColor($metric))
            ->icon($icon);

        if ($sparklineKey !== null) {
            $series = $this->dailySeries()->pluck($sparklineKey)->all();

            if ($series !== []) {
                $stat->chart($series);
            }
        }

        return $stat;
    }

    private function trendDescription(string $metric): string
    {
        $change = $this->summary()->changeFor($metric);

        if ($change === null) {
            return 'بازه قبل داده‌ای برای مقایسه ندارد';
        }

        if ($change == 0.0) {
            return 'بدون تغییر نسبت به بازه قبل';
        }

        $isIncrease = $change > 0;

        return sprintf(
            '%s٪ %s نسبت به بازه قبل',
            number_format(abs($change), 1),
            $isIncrease ? 'رشد' : 'افت',
        );
    }

    private function trendIcon(string $metric, bool $lowerIsBetter = false): string
    {
        $change = $this->summary()->changeFor($metric);

        if ($change === null || $change == 0.0) {
            return 'heroicon-m-arrow-path';
        }

        $isIncrease = $change > 0;
        $isFavourable = $lowerIsBetter ? ! $isIncrease : $isIncrease;

        return match (true) {
            $isFavourable && $isIncrease => 'heroicon-m-arrow-trending-up',
            $isFavourable && ! $isIncrease => 'heroicon-m-arrow-trending-down',
            ! $isFavourable && $isIncrease => 'heroicon-m-arrow-trending-down',
            default => 'heroicon-m-arrow-trending-up',
        };
    }

    private function trendColor(string $metric, bool $lowerIsBetter = false): string
    {
        $change = $this->summary()->changeFor($metric);

        if ($change === null || $change == 0.0) {
            return 'gray';
        }

        $isIncrease = $change > 0;
        $isFavourable = $lowerIsBetter ? ! $isIncrease : $isIncrease;

        return $isFavourable ? 'success' : 'danger';
    }

    private function formatPercentage(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.').'٪';
    }
}
