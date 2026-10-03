<?php

namespace App\Filament\Widgets\Traffic;

use App\Filament\Widgets\Concerns\InteractsWithAnalyticsPeriod;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Spatie\Analytics\Period;

/**
 * Shared rendering for the "one dimension, one metric" GA4 breakdowns
 * (browsers, devices, operating systems, referrers, countries, user types).
 */
abstract class TrafficBreakdownChart extends ChartWidget
{
    use InteractsWithAnalyticsPeriod;

    protected ?string $maxHeight = '18rem';

    protected string|BackedEnum|null $emptyStateIcon = Heroicon::OutlinedChartPie;

    /**
     * The brand-neutral palette shared by every breakdown chart, derived from the
     * panel's gold primary colour.
     *
     * @var list<string>
     */
    protected const PALETTE = [
        '#BFA772',
        '#8C754A',
        '#D0B873',
        '#5A4C30',
        '#E0CC91',
        '#A88E5D',
        '#705E3C',
        '#EDDFB7',
        '#332B1A',
        '#F5EDD6',
    ];

    protected ?Collection $breakdown = null;

    abstract protected function getType(): string;

    /**
     * The heading doubles as the chart's accessible label, so it must describe
     * both the dimension and the metric.
     */
    abstract protected function breakdownHeading(): string;

    /**
     * The service collection key that holds the raw dimension value.
     */
    abstract protected function dimensionKey(): string;

    abstract protected function limit(): int;

    abstract protected function translate(string $value): string;

    /**
     * @return Collection<int, array<string, int|string>>
     */
    abstract protected function fetch(Period $period): Collection;

    public function getHeading(): string|Htmlable|null
    {
        return $this->breakdownHeading();
    }

    /**
     * The service collection key holding the numeric metric being plotted.
     */
    protected function metricKey(): string
    {
        return 'pageViews';
    }

    protected function metricLabel(): string
    {
        return 'بازدید صفحه';
    }

    public function getDescription(): ?string
    {
        if (! $this->isAnalyticsAvailable()) {
            return $this->unavailableDescription();
        }

        // Totalling forces the query, so the failure reason is known by now.
        $total = $this->total();

        if ($this->analyticsFailureReason() !== null) {
            return $this->analyticsFailureReason();
        }

        return sprintf('بازه %s · مجموع %s %s', $this->analyticsWindowLabel(), $this->metricLabel(), number_format($total));
    }

    public function getEmptyStateHeading(): string
    {
        return $this->emptyStateHeading();
    }

    public function getEmptyStateDescription(): ?string
    {
        return $this->emptyStateDescription();
    }

    protected function isHorizontalBar(): bool
    {
        return $this->getType() === 'bar';
    }

    protected function getOptions(): array
    {
        $options = [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => $this->isHorizontalBar()
                    ? ['display' => false]
                    : ['position' => 'bottom'],
            ],
        ];

        if ($this->isHorizontalBar()) {
            $options['indexAxis'] = 'y';
            $options['scales'] = [
                'x' => ['beginAtZero' => true, 'grid' => ['display' => false]],
            ];
        } else {
            $options['cutout'] = '62%';
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = $this->breakdown();

        if ($rows->isEmpty()) {
            return [];
        }

        return [
            'labels' => $rows
                ->map(fn (array $row): string => $this->translate((string) ($row[$this->dimensionKey()] ?? '')))
                ->values()
                ->all(),
            'datasets' => [
                [
                    'label' => $this->metricLabel(),
                    'data' => $rows->pluck($this->metricKey())->all(),
                    'backgroundColor' => $this->colorsFor($rows->count()),
                    'borderWidth' => $this->isHorizontalBar() ? 0 : 2,
                    'borderColor' => 'transparent',
                ],
            ],
        ];
    }

    protected function breakdown(): Collection
    {
        $metricKey = $this->metricKey();

        return $this->breakdown ??= $this->fetch($this->analyticsWindow())
            ->filter(fn (array $row): bool => (int) ($row[$metricKey] ?? 0) > 0)
            ->values();
    }

    protected function total(): int
    {
        return (int) $this->breakdown()->sum($this->metricKey());
    }

    /**
     * @return list<string>
     */
    protected function colorsFor(int $count): array
    {
        return array_slice(
            array_merge(self::PALETTE, self::PALETTE),
            0,
            max($count, 1),
        );
    }
}
