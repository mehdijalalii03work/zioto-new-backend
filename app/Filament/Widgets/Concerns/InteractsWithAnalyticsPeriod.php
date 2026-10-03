<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\AnalyticsPeriod;
use App\Enums\Permission;
use App\Services\Analytics\AnalyticsService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;
use Spatie\Analytics\Period;

/**
 * Shared plumbing for traffic widgets: resolves the period selected on the
 * hosting page's filters form and memoises the service for the request.
 */
trait InteractsWithAnalyticsPeriod
{
    use InteractsWithPageFilters;

    protected ?AnalyticsService $analytics = null;

    protected ?Period $analyticsPeriod = null;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::AnalyticsReportView->value) ?? false;
    }

    protected function analyticsService(): AnalyticsService
    {
        return $this->analytics ??= app(AnalyticsService::class);
    }

    protected function selectedAnalyticsPeriod(): AnalyticsPeriod
    {
        return AnalyticsPeriod::fromFilters($this->pageFilters);
    }

    protected function analyticsWindow(): Period
    {
        if ($this->analyticsPeriod instanceof Period) {
            return $this->analyticsPeriod;
        }

        [$from, $to] = AnalyticsPeriod::customBoundariesFromFilters($this->pageFilters);

        return $this->analyticsPeriod = $this->selectedAnalyticsPeriod()->toPeriod($from, $to);
    }

    /**
     * Persian, human readable window label used in widget headings.
     */
    protected function analyticsWindowLabel(): string
    {
        return AnalyticsPeriod::describe($this->pageFilters);
    }

    /**
     * Traffic widgets must never poll: the underlying GA4 responses are cached.
     */
    protected function getPollingInterval(): ?string
    {
        return null;
    }

    protected function isAnalyticsAvailable(): bool
    {
        return $this->analyticsService()->isConfigured();
    }

    protected function unavailableHeading(): string
    {
        return 'گزارش ترافیک در دسترس نیست';
    }

    protected function unavailableDescription(): ?string
    {
        return $this->analyticsService()->unavailableReason();
    }

    /**
     * Set once a query was actually rejected by Google. Distinct from
     * `unavailableReason()`, which only covers local configuration.
     */
    protected function analyticsFailureReason(): ?string
    {
        return $this->analyticsService()->failureReason();
    }

    protected function analyticsErrorHeading(): string
    {
        return 'دریافت داده از گوگل آنالیتیکس ناموفق بود';
    }

    /**
     * Empty-state copy: prefer the API error over "no data", so a missing
     * permission is never mistaken for an empty property.
     */
    protected function emptyStateHeading(): string
    {
        if ($this->analyticsFailureReason() !== null) {
            return $this->analyticsErrorHeading();
        }

        return $this->unavailableDescription() === null ? 'داده‌ای برای نمایش نیست' : $this->unavailableHeading();
    }

    protected function emptyStateDescription(): ?string
    {
        return $this->analyticsFailureReason()
            ?? $this->unavailableDescription()
            ?? $this->noDataDescription();
    }

    protected function noDataDescription(): string
    {
        return sprintf('داده‌ای برای بازه «%s» در گوگل آنالیتیکس یافت نشد.', $this->analyticsWindowLabel());
    }

    /**
     * Formats a GA4 date as a short Jalali axis label.
     */
    protected function jalaliDateLabel(mixed $date, bool $withYear = false): string
    {
        if (! $date instanceof Carbon) {
            return '';
        }

        return Jalalian::fromCarbon($date)->format($withYear ? 'Y/m/d' : 'm/d');
    }
}
