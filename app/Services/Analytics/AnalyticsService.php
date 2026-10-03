<?php

namespace App\Services\Analytics;

use Google\ApiCore\ApiException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Analytics\Exceptions\InvalidConfiguration;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\OrderBy;
use Spatie\Analytics\Period;
use Throwable;

/**
 * Read-only gateway to the GA4 property behind `spatie/laravel-analytics`.
 *
 * Reporting must never take the admin panel down, so every query is guarded:
 * a missing property id, a missing service account key or a Google API failure
 * degrades to empty data plus a logged warning. Callers check `isConfigured()`
 * to render setup guidance instead of an empty report.
 */
class AnalyticsService
{
    /**
     * GA4 keeps 14 months of data by default, so this covers the whole window a
     * property can report on. Used to tell "no data in this range" apart from
     * "this property is not the one receiving your traffic".
     */
    private const LOOKBACK_DAYS = 400;

    private ?string $failureReason = null;

    private ?array $lastRecordedVisit = null;

    private bool $lastRecordedVisitResolved = false;

    public function isConfigured(): bool
    {
        return filled(config('analytics.property_id'))
            && $this->credentialsExist();
    }

    /**
     * A human readable explanation of why analytics is unavailable, in Persian.
     */
    public function unavailableReason(): ?string
    {
        if (blank(config('analytics.property_id'))) {
            return 'مقدار ANALYTICS_PROPERTY_ID در فایل .env تنظیم نشده است.';
        }

        if (! $this->credentialsExist()) {
            return 'فایل اطلاعات احراز هویت گوگل (سرویس اکانت) در مسیر تعیین‌شده یافت نشد.';
        }

        return null;
    }

    /**
     * Why the last Google request failed, in Persian, or `null` when it succeeded.
     *
     * Configuration problems are reported by `unavailableReason()`; this only
     * covers requests Google actually rejected, which almost always means the
     * service account was never granted access to the property.
     */
    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function propertyId(): ?string
    {
        $propertyId = config('analytics.property_id');

        return filled($propertyId) ? (string) $propertyId : null;
    }

    /**
     * Current window totals alongside the immediately preceding window of equal
     * length, for period-over-period trend indicators.
     */
    /**
     * The most recent day this property recorded any page view, regardless of the
     * reporting window.
     *
     * @return array{date: Carbon, pageViews: int}|null
     */
    public function lastRecordedVisit(): ?array
    {
        if ($this->lastRecordedVisitResolved) {
            return $this->lastRecordedVisit;
        }

        $this->lastRecordedVisitResolved = true;

        if (! $this->isConfigured()) {
            return $this->lastRecordedVisit = null;
        }

        $rows = $this->guard(
            fn (): Collection => Analytics::get(
                period: Period::create(Carbon::today()->subDays(self::LOOKBACK_DAYS), Carbon::today()),
                metrics: ['screenPageViews'],
                dimensions: ['date'],
                maxResults: 1,
                orderBy: [OrderBy::dimension('date', true)],
            ),
            'lastRecordedVisit',
        );

        $row = $rows->first();

        if (! is_array($row) || ! isset($row['date'])) {
            return $this->lastRecordedVisit = null;
        }

        return $this->lastRecordedVisit = [
            'date' => Carbon::parse(Carbon::parse($row['date'])->format('Y-m-d')),
            'pageViews' => (int) ($row['screenPageViews'] ?? 0),
        ];
    }

    public function summary(Period $period, ?Period $previousPeriod = null): TrafficSummaryReport
    {
        if (! $this->isConfigured()) {
            return TrafficSummaryReport::empty();
        }

        $previousPeriod ??= Period::create(
            Carbon::parse($period->startDate->format('Y-m-d'))->subDays($this->lengthInDays($period)),
            Carbon::parse($period->startDate->format('Y-m-d'))->subDay(),
        );

        return new TrafficSummaryReport(
            current: $this->summaryFor($period),
            previous: $this->summaryFor($previousPeriod),
        );
    }

    /**
     * Daily visitors and page views, ascending, with every calendar day of the
     * window present so charts do not collapse gaps.
     *
     * @return Collection<int, array{date: Carbon, visitors: int, pageViews: int}>
     */
    public function dailyTraffic(Period $period): Collection
    {
        if (! $this->isConfigured()) {
            return collect();
        }

        $rows = $this->guard(
            fn (): Collection => Analytics::get(
                period: $period,
                metrics: ['totalUsers', 'screenPageViews'],
                dimensions: ['date'],
                maxResults: $this->lengthInDays($period),
                orderBy: [OrderBy::dimension('date', false)],
            ),
            'dailyTraffic',
        );

        $byDate = $rows
            ->filter(fn (array $row): bool => isset($row['date']))
            ->keyBy(fn (array $row): string => $row['date'] instanceof Carbon
                ? $row['date']->format('Y-m-d')
                : Carbon::parse($row['date'])->format('Y-m-d'));

        $cursor = Carbon::parse($period->startDate->format('Y-m-d'));
        $end = Carbon::parse($period->endDate->format('Y-m-d'));

        $series = collect();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m-d');
            $row = $byDate->get($key);

            $series->push([
                'date' => $cursor->copy(),
                'visitors' => (int) ($row['totalUsers'] ?? 0),
                'pageViews' => (int) ($row['screenPageViews'] ?? 0),
            ]);

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @return Collection<int, array{title: string, url: string, pageViews: int}>
     */
    public function topPages(Period $period, int $limit = 10): Collection
    {
        if (! $this->isConfigured()) {
            return collect();
        }

        return $this->guard(
            fn (): Collection => Analytics::fetchMostVisitedPages(period: $period, maxResults: $limit),
            'topPages',
        )->map(fn (array $row): array => [
            'title' => trim((string) ($row['pageTitle'] ?? '')) ?: $this->pathFromUrl($row['fullPageUrl'] ?? null),
            'url' => (string) ($row['fullPageUrl'] ?? ''),
            'pageViews' => (int) ($row['screenPageViews'] ?? 0),
        ])->values();
    }

    /**
     * @return Collection<int, array{referrer: string, pageViews: int}>
     */
    public function topReferrers(Period $period, int $limit = 10): Collection
    {
        return $this->dimensionBreakdown(
            $period,
            metrics: ['screenPageViews'],
            dimension: 'pageReferrer',
            limit: $limit,
            label: 'referrer',
            context: 'topReferrers',
        );
    }

    /**
     * @return Collection<int, array{browser: string, pageViews: int}>
     */
    public function topBrowsers(Period $period, int $limit = 6): Collection
    {
        return $this->dimensionBreakdown(
            $period,
            metrics: ['screenPageViews'],
            dimension: 'browser',
            limit: $limit,
            label: 'browser',
            context: 'topBrowsers',
        );
    }

    /**
     * @return Collection<int, array{operatingSystem: string, pageViews: int}>
     */
    public function topOperatingSystems(Period $period, int $limit = 6): Collection
    {
        return $this->dimensionBreakdown(
            $period,
            metrics: ['screenPageViews'],
            dimension: 'operatingSystem',
            limit: $limit,
            label: 'operatingSystem',
            context: 'topOperatingSystems',
        );
    }

    /**
     * @return Collection<int, array{country: string, pageViews: int}>
     */
    public function topCountries(Period $period, int $limit = 10): Collection
    {
        return $this->dimensionBreakdown(
            $period,
            metrics: ['screenPageViews'],
            dimension: 'country',
            limit: $limit,
            label: 'country',
            context: 'topCountries',
        );
    }

    /**
     * @return Collection<int, array{device: string, pageViews: int}>
     */
    public function topDeviceCategories(Period $period, int $limit = 6): Collection
    {
        return $this->dimensionBreakdown(
            $period,
            metrics: ['screenPageViews'],
            dimension: 'deviceCategory',
            limit: $limit,
            label: 'device',
            context: 'topDeviceCategories',
        );
    }

    /**
     * @return Collection<int, array{type: string, visitors: int}>
     */
    public function userTypes(Period $period): Collection
    {
        if (! $this->isConfigured()) {
            return collect();
        }

        return $this->guard(
            fn (): Collection => Analytics::fetchUserTypes($period),
            'userTypes',
        )->map(fn (array $row): array => [
            'type' => (string) ($row['newVsReturning'] ?? ''),
            'visitors' => (int) ($row['activeUsers'] ?? 0),
        ])->values();
    }

    private function summaryFor(Period $period): TrafficSummary
    {
        $rows = $this->guard(
            fn (): Collection => Analytics::get(
                period: $period,
                metrics: ['totalUsers', 'screenPageViews', 'sessions', 'bounceRate', 'averageSessionDuration'],
                maxResults: 1,
            ),
            'summary',
        );

        $row = $rows->first();

        if (! is_array($row)) {
            return TrafficSummary::empty();
        }

        return new TrafficSummary(
            visitors: (int) ($row['totalUsers'] ?? 0),
            pageViews: (int) ($row['screenPageViews'] ?? 0),
            sessions: (int) ($row['sessions'] ?? 0),
            bounceRate: (float) ($row['bounceRate'] ?? 0),
            averageSessionDuration: (float) ($row['averageSessionDuration'] ?? 0),
        );
    }

    /**
     * @param  callable(): Collection  $query
     * @return Collection<int, array<string, mixed>>
     */
    private function dimensionBreakdown(
        Period $period,
        array $metrics,
        string $dimension,
        int $limit,
        string $label,
        string $context,
    ): Collection {
        if (! $this->isConfigured()) {
            return collect();
        }

        return $this->guard(
            fn (): Collection => Analytics::get(
                period: $period,
                metrics: $metrics,
                dimensions: [$dimension],
                maxResults: $limit,
                orderBy: [OrderBy::metric($metrics[0], true)],
            ),
            $context,
        )->map(fn (array $row): array => [
            $label => (string) ($row[$dimension] ?? ''),
            'pageViews' => (int) ($row[$metrics[0]] ?? 0),
        ])->values();
    }

    private function lengthInDays(Period $period): int
    {
        return (int) Carbon::parse($period->startDate->format('Y-m-d'))
            ->diffInDays(Carbon::parse($period->endDate->format('Y-m-d'))) + 1;
    }

    private function pathFromUrl(?string $url): string
    {
        $path = is_string($url) ? (parse_url($url, PHP_URL_PATH) ?: $url) : '';

        return $path === '' ? 'صفحه بدون عنوان' : $path;
    }

    private function credentialsExist(): bool
    {
        $credentials = config('analytics.service_account_credentials_json');

        if (is_array($credentials)) {
            return filled($credentials);
        }

        return is_string($credentials) && is_file($credentials);
    }

    /**
     * @template TValue
     *
     * @param  callable(): TValue  $query
     * @return TValue|Collection<int, array<string, mixed>>
     */
    private function guard(callable $query, string $context): mixed
    {
        try {
            return $query();
        } catch (InvalidConfiguration $exception) {
            Log::warning('Analytics is not configured.', [
                'context' => $context,
                'message' => $exception->getMessage(),
            ]);

            $this->failureReason = 'پیکربندی گوگل آنالیتیکس ناقص است.';

            return collect();
        } catch (Throwable $exception) {
            $this->failureReason = $this->describeFailure($exception);

            Log::warning('Google Analytics request failed.', [
                'context' => $context,
                'property_id' => $this->propertyId(),
                'reason' => $this->failureReason,
                'exception' => $exception->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Turns a Google API failure into an actionable Persian hint. Reporting an
     * empty chart as "no data" when the real problem is a missing permission is
     * the fastest way to waste an afternoon, so name the cause explicitly.
     */
    private function describeFailure(Throwable $exception): string
    {
        return match ($this->failureStatus($exception)) {
            'PERMISSION_DENIED' => sprintf(
                'سرویس‌اکانت گوگل به پراپرتی %s دسترسی ندارد. در گوگل آنالیتیکس به «مدیریت دسترسی» پراپرتی بروید و %s را با نقش «بیننده» یا «تحلیلگر» اضافه کنید.',
                $this->propertyId() ?? '—',
                $this->serviceAccountEmail() ?? 'client_email سرویس‌اکانت',
            ),
            'RESOURCE_EXHAUSTED' => 'سهمیه API گوگل آنالیتیکس تمام شده است. چند دقیقه بعد دوباره تلاش کنید.',
            'FORBIDDEN' => 'سرویس‌اکانت اجازه استفاده از Google Analytics Data API را ندارد. این API باید در پروژه گوگل سرویس‌اکانت فعال باشد.',
            'NOT_FOUND' => sprintf(
                'پراپرتی %s در گوگل آنالیتیکس یافت نشد. مقدار ANALYTICS_PROPERTY_ID را بررسی کنید.',
                $this->propertyId() ?? '—',
            ),
            default => 'دریافت داده از گوگل آنالیتیکس ناموفق بود: '.$exception->getMessage(),
        };
    }

    /**
     * `getStatus()` carries the gRPC status name on real API errors but is empty
     * when the exception is constructed locally, so fall back to the numeric code.
     */
    private function failureStatus(Throwable $exception): ?string
    {
        if (! $exception instanceof ApiException) {
            return null;
        }

        $status = $exception->getStatus();

        if (filled($status)) {
            return $status;
        }

        return match ($exception->getCode()) {
            7 => 'PERMISSION_DENIED',
            8 => 'RESOURCE_EXHAUSTED',
            16 => 'UNAUTHENTICATED',
            5 => 'NOT_FOUND',
            403 => 'FORBIDDEN',
            default => null,
        };
    }

    private function serviceAccountEmail(): ?string
    {
        $credentials = config('analytics.service_account_credentials_json');
        $path = is_array($credentials) ? null : $credentials;

        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $email = is_array($decoded) ? ($decoded['client_email'] ?? null) : null;

        return is_string($email) ? $email : null;
    }
}
