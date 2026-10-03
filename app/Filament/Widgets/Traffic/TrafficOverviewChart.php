<?php

namespace App\Filament\Widgets\Traffic;

use App\Filament\Widgets\Concerns\InteractsWithAnalyticsPeriod;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Visitors and page views per day, plotted against Jalali dates.
 */
class TrafficOverviewChart extends ChartWidget
{
    use InteractsWithAnalyticsPeriod;

    protected int|string|array $columnSpan = ['@lg' => 2, '@2xl' => 3];

    protected ?string $heading = 'روند بازدید';

    protected ?string $maxHeight = '20rem';

    protected string|BackedEnum|null $emptyStateIcon = Heroicon::OutlinedChartBar;

    protected ?Collection $series = null;

    public function getDescription(): ?string
    {
        if (! $this->isAnalyticsAvailable()) {
            return $this->unavailableDescription();
        }

        return 'بازدیدکننده یکتا و بازدید صفحه در بازه '.$this->analyticsWindowLabel();
    }

    public function getEmptyStateHeading(): string
    {
        return $this->emptyStateHeading();
    }

    public function getEmptyStateDescription(): ?string
    {
        return $this->emptyStateDescription();
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
            'elements' => [
                'line' => ['tension' => 0.35, 'borderWidth' => 2],
                'point' => ['radius' => 0, 'hoverRadius' => 4],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $series = $this->dailySeries();

        if ($series->isEmpty()) {
            return [];
        }

        return [
            'labels' => $series
                ->map(fn (array $day): string => $this->jalaliDateLabel($day['date']))
                ->all(),
            'datasets' => [
                [
                    'label' => 'بازدیدکننده یکتا',
                    'data' => $series->pluck('visitors')->all(),
                    'borderColor' => '#BFA772',
                    'backgroundColor' => 'rgba(191, 167, 114, 0.15)',
                    'fill' => true,
                ],
                [
                    'label' => 'بازدید صفحه',
                    'data' => $series->pluck('pageViews')->all(),
                    'borderColor' => '#5A4C30',
                    'backgroundColor' => 'rgba(90, 76, 48, 0.10)',
                    'fill' => true,
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, array{date: Carbon, visitors: int, pageViews: int}>
     */
    protected function dailySeries(): Collection
    {
        return $this->series ??= $this->analyticsService()->dailyTraffic($this->analyticsWindow());
    }
}
