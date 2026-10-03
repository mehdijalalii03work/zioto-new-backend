<?php

namespace Tests\Unit;

use App\Enums\AnalyticsPeriod;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AnalyticsPeriodTest extends TestCase
{
    public function test_it_derives_day_count_from_the_resolved_window(): void
    {
        $this->assertSame(1, AnalyticsPeriod::Today->dayCount());
        $this->assertSame(7, AnalyticsPeriod::Last7Days->dayCount());
        $this->assertSame(30, AnalyticsPeriod::Last30Days->dayCount());
        $this->assertSame(90, AnalyticsPeriod::Last90Days->dayCount());
        $this->assertSame(180, AnalyticsPeriod::Last180Days->dayCount());
    }

    public function test_previous_period_is_the_immediately_preceding_window_of_equal_length(): void
    {
        $previous = AnalyticsPeriod::Last7Days->previousPeriod();

        $this->assertSame(
            Carbon::today()->subDays(13)->format('Y-m-d'),
            $previous->startDate->format('Y-m-d'),
        );
        $this->assertSame(
            Carbon::today()->subDays(7)->format('Y-m-d'),
            $previous->endDate->format('Y-m-d'),
        );
    }

    public function test_custom_range_is_read_from_jalali_boundaries(): void
    {
        $period = AnalyticsPeriod::Custom->toPeriod('1405/05/01', '1405/05/07');

        $this->assertSame('2026-07-23', $period->startDate->format('Y-m-d'));
        $this->assertSame('2026-07-29', $period->endDate->format('Y-m-d'));
        $this->assertTrue(AnalyticsPeriod::Custom->requiresCustomRange());
    }

    public function test_custom_range_swaps_reversed_boundaries(): void
    {
        $period = AnalyticsPeriod::Custom->toPeriod('1405/05/07', '1405/05/01');

        $this->assertSame('2026-07-23', $period->startDate->format('Y-m-d'));
        $this->assertSame('2026-07-29', $period->endDate->format('Y-m-d'));
    }

    public function test_custom_range_falls_back_to_the_last_seven_days_without_boundaries(): void
    {
        $period = AnalyticsPeriod::Custom->toPeriod();

        $this->assertSame(
            Carbon::today()->subDays(6)->format('Y-m-d'),
            $period->startDate->format('Y-m-d'),
        );
        $this->assertSame(Carbon::today()->format('Y-m-d'), $period->endDate->format('Y-m-d'));
    }

    public function test_it_resolves_the_selection_from_a_page_filters_array(): void
    {
        $this->assertSame(AnalyticsPeriod::Last30Days, AnalyticsPeriod::fromFilters(null));
        $this->assertSame(AnalyticsPeriod::Last30Days, AnalyticsPeriod::fromFilters([]));
        $this->assertSame(AnalyticsPeriod::Last30Days, AnalyticsPeriod::fromFilters(['period' => 'nonsense']));
        $this->assertSame(AnalyticsPeriod::Last90Days, AnalyticsPeriod::fromFilters(['period' => 'last_90_days']));
        $this->assertSame(
            AnalyticsPeriod::Custom,
            AnalyticsPeriod::fromFilters(['period' => 'custom', 'from' => '1405/05/01']),
        );
    }

    public function test_it_reads_custom_boundaries_from_a_page_filters_array(): void
    {
        $this->assertSame([null, null], AnalyticsPeriod::customBoundariesFromFilters(null));
        $this->assertSame(
            ['1405/05/01', '1405/05/07'],
            AnalyticsPeriod::customBoundariesFromFilters([
                'period' => 'custom',
                'from' => '1405/05/01',
                'to' => '1405/05/07',
            ]),
        );
    }

    public function test_it_describes_the_selected_window(): void
    {
        $this->assertSame('۳۰ روز اخیر', AnalyticsPeriod::describe(['period' => 'last_30_days']));
        $this->assertSame(
            'بازه دلخواه (1405/05/01 تا 1405/05/07)',
            AnalyticsPeriod::describe(['period' => 'custom', 'from' => '1405/05/01', 'to' => '1405/05/07']),
        );
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function periodOptionProvider(): array
    {
        return [
            'today' => [AnalyticsPeriod::Today->value, 'امروز'],
            'last 7 days' => [AnalyticsPeriod::Last7Days->value, '۷ روز اخیر'],
            'this month' => [AnalyticsPeriod::ThisMonth->value, 'ماه جاری'],
        ];
    }

    #[DataProvider('periodOptionProvider')]
    public function test_every_option_is_labelled_in_persian(string $value, string $label): void
    {
        $this->assertSame($label, AnalyticsPeriod::options()[$value]);
    }
}
