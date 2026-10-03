<?php

namespace App\Services\Analytics;

/**
 * A `TrafficSummary` paired with the equivalent preceding window so widgets can
 * render period-over-period trends.
 */
final readonly class TrafficSummaryReport
{
    public function __construct(
        public TrafficSummary $current,
        public TrafficSummary $previous,
    ) {}

    public static function empty(): self
    {
        return new self(TrafficSummary::empty(), TrafficSummary::empty());
    }

    /**
     * Percentage change of a metric against the preceding window, or `null` when
     * there is no baseline to compare against.
     */
    public function changeFor(string $metric): ?float
    {
        $current = $this->valueFor($this->current, $metric);
        $previous = $this->valueFor($this->previous, $metric);

        if ($previous == 0.0) {
            return $current > 0 ? null : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function valueFor(TrafficSummary $summary, string $metric): float
    {
        return match ($metric) {
            'visitors' => (float) $summary->visitors,
            'pageViews' => (float) $summary->pageViews,
            'sessions' => (float) $summary->sessions,
            'bounceRate' => $summary->bounceRateAsPercentage(),
            'averageSessionDuration' => $summary->averageSessionDuration,
            'pagesPerSession' => $summary->pagesPerSession(),
            default => 0.0,
        };
    }
}
