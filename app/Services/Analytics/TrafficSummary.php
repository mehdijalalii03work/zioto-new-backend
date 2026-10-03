<?php

namespace App\Services\Analytics;

/**
 * Aggregate GA4 totals for a single reporting window.
 */
final readonly class TrafficSummary
{
    public function __construct(
        public int $visitors,
        public int $pageViews,
        public int $sessions,
        public float $bounceRate,
        public float $averageSessionDuration,
    ) {}

    public static function empty(): self
    {
        return new self(
            visitors: 0,
            pageViews: 0,
            sessions: 0,
            bounceRate: 0.0,
            averageSessionDuration: 0.0,
        );
    }

    public function pagesPerSession(): float
    {
        return $this->sessions > 0 ? round($this->pageViews / $this->sessions, 2) : 0.0;
    }

    public function bounceRateAsPercentage(): float
    {
        return round($this->bounceRate * 100, 1);
    }

    /**
     * Seconds rendered as `m:ss`, e.g. `2:05`.
     */
    public function averageSessionDurationForHumans(): string
    {
        $seconds = (int) round($this->averageSessionDuration);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    public function isEmpty(): bool
    {
        return $this->visitors === 0
            && $this->pageViews === 0
            && $this->sessions === 0;
    }
}
