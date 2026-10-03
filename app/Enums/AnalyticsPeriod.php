<?php

namespace App\Enums;

use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;
use Spatie\Analytics\Exceptions\InvalidPeriod;
use Spatie\Analytics\Period;

/**
 * Selectable reporting windows for the Google Analytics traffic reports.
 */
enum AnalyticsPeriod: string
{
    case Today = 'today';

    case Yesterday = 'yesterday';

    case Last7Days = 'last_7_days';

    case Last30Days = 'last_30_days';

    case Last90Days = 'last_90_days';

    case Last180Days = 'last_180_days';

    case ThisMonth = 'this_month';

    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Today => 'امروز',
            self::Yesterday => 'دیروز',
            self::Last7Days => '۷ روز اخیر',
            self::Last30Days => '۳۰ روز اخیر',
            self::Last90Days => '۹۰ روز اخیر',
            self::Last180Days => '۶ ماه اخیر',
            self::ThisMonth => 'ماه جاری',
            self::Custom => 'بازه دلخواه',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $period): array => [$period->value => $period->label()])
            ->all();
    }

    public function requiresCustomRange(): bool
    {
        return $this === self::Custom;
    }

    /**
     * Resolve the selected window from a Filament page-filters array.
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function fromFilters(?array $filters): self
    {
        $value = is_array($filters) ? ($filters['period'] ?? null) : null;

        return self::tryFrom(is_string($value) ? $value : '') ?? self::Last30Days;
    }

    /**
     * @param  array<string, mixed>|null  $filters
     * @return array{0: ?string, 1: ?string}
     */
    public static function customBoundariesFromFilters(?array $filters): array
    {
        if (! is_array($filters)) {
            return [null, null];
        }

        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        return [
            is_string($from) && $from !== '' ? $from : null,
            is_string($to) && $to !== '' ? $to : null,
        ];
    }

    /**
     * A human readable summary of the window, e.g. `۳۰ روز اخیر (۱۴۰۵/۰۵/۰۴ تا ۱۴۰۵/۰۶/۰۳)`.
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function describe(?array $filters): string
    {
        $period = self::fromFilters($filters);

        if (! $period->requiresCustomRange()) {
            return $period->label();
        }

        [$from, $to] = self::customBoundariesFromFilters($filters);
        $resolved = $period->toPeriod($from, $to);

        return sprintf(
            '%s (%s تا %s)',
            $period->label(),
            Jalalian::fromCarbon(Carbon::parse($resolved->startDate->format('Y-m-d')))->format('Y/m/d'),
            Jalalian::fromCarbon(Carbon::parse($resolved->endDate->format('Y-m-d')))->format('Y/m/d'),
        );
    }

    /**
     * Number of daily data points a chart needs to render this window in full.
     */
    public function dayCount(): int
    {
        $period = $this->toPeriod();

        return (int) $period->startDate->diffInDays($period->endDate) + 1;
    }

    /**
     * The immediately preceding window of equal length, used for trend comparisons.
     */
    public function previousPeriod(): Period
    {
        $period = $this->toPeriod();
        $lengthInDays = $this->dayCount();

        return Period::create(
            Carbon::parse($period->startDate->format('Y-m-d'))->subDays($lengthInDays),
            Carbon::parse($period->startDate->format('Y-m-d'))->subDay(),
        );
    }

    /**
     * @param  string|null  $from  A `Y/m/d` Jalali or `Y-m-d` Gregorian boundary.
     */
    public function toPeriod(?string $from = null, ?string $to = null): Period
    {
        $today = Carbon::today();

        [$start, $end] = match ($this) {
            self::Today => [$today->copy(), $today->copy()],
            self::Yesterday => [$today->copy()->subDay(), $today->copy()->subDay()],
            self::Last7Days => [$today->copy()->subDays(6), $today->copy()],
            self::Last30Days => [$today->copy()->subDays(29), $today->copy()],
            self::Last90Days => [$today->copy()->subDays(89), $today->copy()],
            self::Last180Days => [$today->copy()->subDays(179), $today->copy()],
            self::ThisMonth => [$today->copy()->startOfMonth(), $today->copy()],
            self::Custom => $this->resolveCustomBoundaries($from, $to),
        };

        try {
            return Period::create($start, $end);
        } catch (InvalidPeriod) {
            return Period::create($today->copy()->subDays(6), $today->copy());
        }
    }

    /**
     * Default `Y/m/d` Jalali boundaries used to prefill the custom range inputs.
     *
     * @return array{0: string, 1: string}
     */
    public function defaultCustomBoundaries(): array
    {
        $today = Jalalian::now();

        return [
            $today->subDays(6)->format('Y/m/d'),
            $today->format('Y/m/d'),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveCustomBoundaries(?string $from, ?string $to): array
    {
        $start = $this->parseBoundary($from) ?? Carbon::today()->subDays(6);
        $end = $this->parseBoundary($to) ?? Carbon::today();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    private function parseBoundary(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        $value = trim($value);

        if (str_contains($value, '/')) {
            [$year, $month, $day] = array_pad(explode('/', $value), 3, null);

            if ($year !== null && $month !== null && $day !== null && (int) $year < 1700) {
                return Carbon::parse(Jalalian::fromFormat('Y/m/d', sprintf('%s/%s/%s', $year, $month, $day))->toCarbon());
            }

            return Carbon::createFromFormat('Y/m/d', $value)->startOfDay();
        }

        return Carbon::parse($value)->startOfDay();
    }
}
