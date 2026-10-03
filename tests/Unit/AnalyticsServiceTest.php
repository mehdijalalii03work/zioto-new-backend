<?php

namespace Tests\Unit;

use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\TrafficSummary;
use App\Services\Analytics\TrafficSummaryReport;
use Google\ApiCore\ApiException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Spatie\Analytics\Exceptions\InvalidConfiguration;
use Spatie\Analytics\Facades\Analytics;
use Spatie\Analytics\Period;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'ga-credentials');

        file_put_contents($this->credentialsPath, '{}');

        config([
            'analytics.property_id' => '538760262',
            'analytics.service_account_credentials_json' => $this->credentialsPath,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->credentialsPath)) {
            unlink($this->credentialsPath);
        }

        parent::tearDown();
    }

    public function test_it_is_configured_when_a_property_id_and_credentials_are_present(): void
    {
        $this->assertTrue(app(AnalyticsService::class)->isConfigured());
        $this->assertNull(app(AnalyticsService::class)->unavailableReason());
    }

    public function test_it_reports_a_missing_property_id(): void
    {
        config(['analytics.property_id' => null]);

        $service = app(AnalyticsService::class);

        $this->assertFalse($service->isConfigured());
        $this->assertStringContainsString('ANALYTICS_PROPERTY_ID', (string) $service->unavailableReason());
    }

    public function test_it_reports_missing_credentials(): void
    {
        config(['analytics.service_account_credentials_json' => '/tmp/does-not-exist.json']);

        $service = app(AnalyticsService::class);

        $this->assertFalse($service->isConfigured());
        $this->assertStringContainsString('احراز هویت گوگل', (string) $service->unavailableReason());
    }

    public function test_it_returns_empty_data_when_it_is_not_configured(): void
    {
        config(['analytics.property_id' => null]);

        $service = app(AnalyticsService::class);
        $period = Period::days(7);

        $this->assertTrue($service->summary($period)->current->isEmpty());
        $this->assertTrue($service->dailyTraffic($period)->isEmpty());
        $this->assertTrue($service->topPages($period)->isEmpty());
        $this->assertTrue($service->userTypes($period)->isEmpty());
    }

    public function test_it_swallows_google_api_failures_and_logs_a_warning(): void
    {
        Log::spy();

        Analytics::swap($this->throwingAnalytics(new \RuntimeException('quota exceeded')));

        $series = app(AnalyticsService::class)->dailyTraffic(Period::days(7));

        $this->assertSame([0], $series->pluck('visitors')->unique()->values()->all());
        $this->assertSame([0], $series->pluck('pageViews')->unique()->values()->all());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'Google Analytics request failed'));
    }

    public function test_it_swallows_invalid_configuration_from_the_package(): void
    {
        Log::spy();

        Analytics::swap($this->throwingAnalytics(InvalidConfiguration::propertyIdNotSpecified()));

        $this->assertTrue(app(AnalyticsService::class)->topPages(Period::days(7))->isEmpty());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'Analytics is not configured'));
    }

    private function throwingAnalytics(\Throwable $exception): object
    {
        return new class($exception)
        {
            public function __construct(private \Throwable $exception) {}

            public function __call(string $method, array $arguments): never
            {
                throw $this->exception;
            }
        };
    }

    public function test_it_computes_percentage_changes_against_the_preceding_window(): void
    {
        $report = new TrafficSummaryReport(
            current: new TrafficSummary(visitors: 150, pageViews: 90, sessions: 100, bounceRate: 0.3, averageSessionDuration: 60.0),
            previous: new TrafficSummary(visitors: 100, pageViews: 100, sessions: 100, bounceRate: 0.5, averageSessionDuration: 60.0),
        );

        $this->assertSame(50.0, $report->changeFor('visitors'));
        $this->assertSame(-10.0, $report->changeFor('pageViews'));
        $this->assertSame(0.0, $report->changeFor('sessions'));
        $this->assertSame(-40.0, $report->changeFor('bounceRate'));
    }

    public function test_it_names_the_cause_of_a_google_permission_failure(): void
    {
        Analytics::swap($this->throwingAnalytics(
            new ApiException('User does not have sufficient permissions for this property.', 7),
        ));

        $service = app(AnalyticsService::class);

        $this->assertTrue($service->topPages(Period::days(7))->isEmpty());

        $reason = (string) $service->failureReason();

        $this->assertStringContainsString('دسترسی ندارد', $reason);
        $this->assertStringContainsString('538760262', $reason);
    }

    public function test_it_names_the_cause_of_a_quota_failure(): void
    {
        Analytics::swap($this->throwingAnalytics(new ApiException('quota', 8)));

        $service = app(AnalyticsService::class);
        $service->topPages(Period::days(7));

        $this->assertStringContainsString('سهمیه', (string) $service->failureReason());
    }

    public function test_it_falls_back_to_a_raw_message_for_unknown_failures(): void
    {
        Analytics::swap($this->throwingAnalytics(new ApiException('something odd', 999)));

        $service = app(AnalyticsService::class);
        $service->topPages(Period::days(7));

        $this->assertStringContainsString('something odd', (string) $service->failureReason());
    }

    public function test_it_reports_the_newest_day_a_property_recorded_any_traffic(): void
    {
        Analytics::fake(collect([
            ['date' => Carbon::today()->subDays(120), 'screenPageViews' => 4],
        ]));

        $service = app(AnalyticsService::class);

        $visit = $service->lastRecordedVisit();

        $this->assertNotNull($visit);
        $this->assertSame(Carbon::today()->subDays(120)->format('Y-m-d'), $visit['date']->format('Y-m-d'));
        $this->assertSame(4, $visit['pageViews']);
    }

    public function test_it_reports_no_recorded_traffic_when_the_property_is_silent(): void
    {
        Analytics::fake(collect());

        $this->assertNull(app(AnalyticsService::class)->lastRecordedVisit());
    }

    public function test_it_reports_no_recorded_traffic_when_not_configured(): void
    {
        config(['analytics.property_id' => null]);

        $this->assertNull(app(AnalyticsService::class)->lastRecordedVisit());
    }

    public function test_summary_casts_the_string_metrics_returned_by_google(): void
    {
        Analytics::fake(collect([
            [
                'totalUsers' => '1234',
                'screenPageViews' => '5678',
                'sessions' => '890',
                'bounceRate' => '0.4231',
                'averageSessionDuration' => '125.4',
            ],
        ]));

        $summary = app(AnalyticsService::class)->summary(Period::days(7))->current;

        $this->assertSame(1234, $summary->visitors);
        $this->assertSame(5678, $summary->pageViews);
        $this->assertSame(890, $summary->sessions);
        $this->assertSame(42.3, $summary->bounceRateAsPercentage());
        $this->assertSame(6.38, $summary->pagesPerSession());
        $this->assertSame('2:05', $summary->averageSessionDurationForHumans());
    }

    public function test_summary_compares_against_the_preceding_window(): void
    {
        Analytics::fake(collect([
            [
                'totalUsers' => '110',
                'screenPageViews' => '220',
                'sessions' => '100',
                'bounceRate' => '0.5',
                'averageSessionDuration' => '60',
            ],
        ]));

        $report = app(AnalyticsService::class)->summary(Period::days(7));

        $this->assertSame(0.0, $report->changeFor('visitors'));
        $this->assertSame(0.0, $report->changeFor('bounceRate'));
    }

    public function test_summary_reports_no_comparison_when_the_baseline_is_empty(): void
    {
        Analytics::fake(collect([
            [
                'totalUsers' => '0',
                'screenPageViews' => '0',
                'sessions' => '0',
                'bounceRate' => '0',
                'averageSessionDuration' => '0',
            ],
        ]));

        $this->assertSame(0.0, app(AnalyticsService::class)->summary(Period::days(7))->changeFor('visitors'));
    }

    public function test_summary_reports_no_baseline_when_only_the_current_window_has_data(): void
    {
        Analytics::fake(collect([
            [
                'totalUsers' => '0',
                'screenPageViews' => '0',
                'sessions' => '0',
                'bounceRate' => '0',
                'averageSessionDuration' => '0',
            ],
        ]));

        $withData = new TrafficSummaryReport(
            current: new TrafficSummary(10, 20, 5, 0.5, 60.0),
            previous: TrafficSummary::empty(),
        );

        $empty = new TrafficSummaryReport(
            current: TrafficSummary::empty(),
            previous: TrafficSummary::empty(),
        );

        $this->assertNull($withData->changeFor('visitors'));
        $this->assertNull($withData->changeFor('pagesPerSession'));
        $this->assertSame(0.0, $empty->changeFor('visitors'));
    }

    public function test_daily_traffic_fills_every_calendar_day_of_the_window(): void
    {
        $start = Carbon::today()->subDays(3);

        Analytics::fake(collect([
            [
                'date' => $start->copy(),
                'totalUsers' => 10,
                'screenPageViews' => 20,
            ],
            [
                'date' => $start->copy()->addDays(2),
                'totalUsers' => 5,
                'screenPageViews' => 6,
            ],
        ]));

        $series = app(AnalyticsService::class)->dailyTraffic(Period::create($start, Carbon::today()));

        $this->assertCount(4, $series);
        $this->assertSame([10, 0, 5, 0], $series->pluck('visitors')->all());
        $this->assertSame([20, 0, 6, 0], $series->pluck('pageViews')->all());
        $this->assertSame(
            $start->format('Y-m-d'),
            $series->first()['date']->format('Y-m-d'),
        );
    }

    public function test_top_pages_normalises_titles_urls_and_views(): void
    {
        Analytics::fake(collect([
            [
                'pageTitle' => '  صفحه محصول  ',
                'fullPageUrl' => 'https://zioto.test/product/1?utm_source=x',
                'screenPageViews' => 42,
            ],
            [
                'pageTitle' => '',
                'fullPageUrl' => 'https://zioto.test/collections/gold',
                'screenPageViews' => 7,
            ],
        ]));

        $pages = app(AnalyticsService::class)->topPages(Period::days(7));

        $this->assertSame('صفحه محصول', $pages->first()['title']);
        $this->assertSame(42, $pages->first()['pageViews']);
        $this->assertSame('/collections/gold', $pages->last()['title']);
    }

    public function test_user_types_are_returned_with_the_raw_ga_dimension_value(): void
    {
        Analytics::fake(collect([
            ['newVsReturning' => 'new', 'activeUsers' => 30],
            ['newVsReturning' => 'returning', 'activeUsers' => 70],
        ]));

        $types = app(AnalyticsService::class)->userTypes(Period::days(7));

        $this->assertSame(
            [['type' => 'new', 'visitors' => 30], ['type' => 'returning', 'visitors' => 70]],
            $types->all(),
        );
    }
}
