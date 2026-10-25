<?php

namespace Tests\Feature\Services;

use App\Models\Setting;
use App\Services\Pricing\LaborCalculator;
use App\Services\Pricing\PricingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LaborCalculatorHolidayTest extends TestCase
{
    use RefreshDatabase;

    public static function weeklyHolidayDates(): array
    {
        return [
            'Thursday during the day' => ['2026-10-22 13:00:00'],
            'Friday during the day' => ['2026-10-23 13:00:00'],
        ];
    }

    #[DataProvider('weeklyHolidayDates')]
    public function test_weekly_holidays_use_nightly_period_all_day(string $dateTime): void
    {
        $this->travelTo(Carbon::parse($dateTime, 'Asia/Tehran'));

        $this->assertSame('nightly', app(LaborCalculator::class)->getCurrentTimePeriod());
    }

    public function test_weekly_holiday_days_can_be_changed_and_other_weekdays_keep_time_rules(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_weekly_holiday_days',
            'value' => '[5]',
            'type' => 'json',
            'category' => 'pricing',
            'label' => 'روزهای تعطیل هفتگی',
        ]);

        $this->travelTo(Carbon::parse('2026-10-22 13:00:00', 'Asia/Tehran'));
        $thursdayPeriod = app(LaborCalculator::class)->getCurrentTimePeriod();

        $this->travelTo(Carbon::parse('2026-10-23 13:00:00', 'Asia/Tehran'));
        $fridayPeriod = app(LaborCalculator::class)->getCurrentTimePeriod();

        $this->assertSame('daily', $thursdayPeriod);
        $this->assertSame('nightly', $fridayPeriod);
    }

    public function test_invalid_weekly_holiday_values_are_ignored(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_weekly_holiday_days',
            'value' => '[-1,4,8,"invalid"]',
            'type' => 'json',
            'category' => 'pricing',
            'label' => 'روزهای تعطیل هفتگی',
        ]);

        $this->assertSame([4], PricingSettings::weeklyHolidayDays());
    }

    public function test_special_holiday_uses_nightly_period_only_on_the_selected_date(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_weekly_holiday_days',
            'value' => '[]',
            'type' => 'json',
            'category' => 'pricing',
            'label' => 'روزهای تعطیل هفتگی',
        ]);
        Setting::create([
            'key' => 'zioto_pricing_special_holiday_dates',
            'value' => '["2026-10-21"]',
            'type' => 'json',
            'category' => 'pricing',
            'label' => 'تعطیلی‌های مناسبتی',
        ]);

        $this->travelTo(Carbon::parse('2026-10-21 13:00:00', 'Asia/Tehran'));
        $holidayPeriod = app(LaborCalculator::class)->getCurrentTimePeriod();

        $this->travelTo(Carbon::parse('2026-10-20 13:00:00', 'Asia/Tehran'));
        $regularPeriod = app(LaborCalculator::class)->getCurrentTimePeriod();

        $this->assertSame('nightly', $holidayPeriod);
        $this->assertSame('daily', $regularPeriod);
    }

    public function test_regular_workdays_keep_existing_hour_based_periods(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_weekly_holiday_days',
            'value' => '[]',
            'type' => 'json',
            'category' => 'pricing',
            'label' => 'روزهای تعطیل هفتگی',
        ]);

        $this->travelTo(Carbon::parse('2026-10-25 13:00:00', 'Asia/Tehran'));
        $dayPeriod = app(LaborCalculator::class)->getCurrentTimePeriod();

        $this->travelTo(Carbon::parse('2026-10-25 20:00:00', 'Asia/Tehran'));
        $nightPeriod = app(LaborCalculator::class)->getCurrentTimePeriod();

        $this->assertSame('daily', $dayPeriod);
        $this->assertSame('nightly', $nightPeriod);
    }

    public function test_crisis_mode_forces_nightly_period_during_daytime(): void
    {
        Setting::create([
            'key' => 'zioto_pricing_force_nightly_pricing',
            'value' => 'true',
            'type' => 'boolean',
            'category' => 'pricing',
            'label' => 'حالت بحران',
        ]);
        $this->travelTo(Carbon::parse('2026-10-25 13:00:00', 'Asia/Tehran'));

        $this->assertSame('nightly', app(LaborCalculator::class)->getCurrentTimePeriod());
    }
}
