<?php

namespace Tests\Feature\Admin;

use App\Enums\AnalyticsPeriod;
use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Pages\Reports\TrafficReport;
use App\Filament\Widgets\Traffic\TopPagesTable;
use App\Filament\Widgets\Traffic\TrafficBrowsersChart;
use App\Filament\Widgets\Traffic\TrafficDeviceCategoriesChart;
use App\Filament\Widgets\Traffic\TrafficOverviewChart;
use App\Filament\Widgets\Traffic\TrafficReferrersChart;
use App\Filament\Widgets\Traffic\TrafficSummaryStats;
use App\Filament\Widgets\Traffic\TrafficUserTypesChart;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Google\ApiCore\ApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Morilog\Jalali\Jalalian;
use Spatie\Analytics\Facades\Analytics;
use Tests\TestCase;

class TrafficReportTest extends TestCase
{
    use RefreshDatabase;

    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('admin');

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

    public function test_the_report_page_renders_every_traffic_widget_for_an_authorised_user(): void
    {
        // Filament renders widgets as lazy Livewire islands, so the page markup only
        // carries the component names; the widget bodies are covered below.
        $this->actingAs($this->admin(), 'web')
            ->get(URL::route('filament.admin.pages.reports.traffic'))
            ->assertOk()
            ->assertSee('گزارش ترافیک')
            ->assertSee('بازه گزارش')
            ->assertSee(TrafficSummaryStats::class, escape: false)
            ->assertSee(TrafficOverviewChart::class, escape: false)
            ->assertSee('traffic-user-types-chart', escape: false)
            ->assertSee('traffic-device-categories-chart', escape: false)
            ->assertSee('traffic-browsers-chart', escape: false)
            ->assertSee('traffic-operating-systems-chart', escape: false)
            ->assertSee('traffic-referrers-chart', escape: false)
            ->assertSee('traffic-countries-chart', escape: false)
            ->assertSee('top-pages-table', escape: false);
    }

    public function test_the_report_page_is_hidden_without_the_analytics_permission(): void
    {
        $user = User::factory()->create()->assignRole(Role::Operator->value);

        $this->assertFalse(TrafficReport::canAccess());

        $this->actingAs($user, 'web')
            ->get(URL::route('filament.admin.pages.reports.traffic'))
            ->assertForbidden();
    }

    public function test_the_report_page_is_hidden_from_guests(): void
    {
        $this->assertFalse(TrafficReport::canAccess());

        $this->get(URL::route('filament.admin.pages.reports.traffic'))
            ->assertRedirect();
    }

    public function test_the_dashboard_lists_the_traffic_widgets(): void
    {
        $this->assertSame(
            [TrafficSummaryStats::class, TrafficOverviewChart::class],
            Filament::getWidgets(),
        );

        $this->actingAs($this->admin(), 'web')
            ->get(URL::route('filament.admin.pages.dashboard'))
            ->assertOk()
            ->assertSee(TrafficSummaryStats::class, escape: false)
            ->assertSee(TrafficOverviewChart::class, escape: false);
    }

    public function test_the_dashboard_hides_traffic_widgets_without_the_analytics_permission(): void
    {
        $user = User::factory()->create()->assignRole(Role::Operator->value);

        $this->assertFalse(TrafficSummaryStats::canView());
        $this->assertFalse(TrafficOverviewChart::canView());

        $this->actingAs($user, 'web')
            ->get(URL::route('filament.admin.pages.dashboard'))
            ->assertOk()
            ->assertDontSee(TrafficSummaryStats::class, escape: false)
            ->assertDontSee(TrafficOverviewChart::class, escape: false);
    }

    public function test_the_dashboard_access_rule_is_unchanged(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->get(URL::route('filament.admin.pages.dashboard'))
            ->assertOk();
    }

    public function test_the_summary_widget_renders_the_aggregated_totals(): void
    {
        Analytics::fake($this->summaryRows());

        Livewire::actingAs($this->admin(), 'web')
            ->test(TrafficSummaryStats::class, ['pageFilters' => ['period' => 'last_7_days']])
            ->assertOk()
            ->assertSee('بازدیدکننده یکتا')
            ->assertSee('1,234')
            ->assertSee('5,678')
            ->assertSee('42.3٪');
    }

    public function test_the_overview_chart_uses_jalali_axis_labels(): void
    {
        Analytics::fake($this->summaryRows());

        $window = AnalyticsPeriod::Last7Days->toPeriod();

        $component = Livewire::actingAs($this->admin(), 'web')
            ->test(TrafficOverviewChart::class, ['pageFilters' => ['period' => 'last_7_days']])
            ->assertOk();

        $data = $this->callProtected($component->instance(), 'getCachedData');

        $this->assertCount(7, $data['labels']);
        $this->assertSame(
            Jalalian::fromCarbon(Carbon::parse($window->startDate->format('Y-m-d')))->format('m/d'),
            $data['labels'][0],
        );
        $this->assertSame(['بازدیدکننده یکتا', 'بازدید صفحه'], array_column($data['datasets'], 'label'));
        $this->assertSame(
            Jalalian::fromCarbon(Carbon::parse($window->endDate->format('Y-m-d')))->format('m/d'),
            $data['labels'][6],
        );
    }

    public function test_the_top_pages_table_ranks_pages_by_page_views(): void
    {
        Analytics::fake(collect([
            ['pageTitle' => 'صفحه اصلی', 'fullPageUrl' => 'https://zioto.test/', 'screenPageViews' => 300],
            ['pageTitle' => 'فروشگاه', 'fullPageUrl' => 'https://zioto.test/shop', 'screenPageViews' => 100],
        ]));

        $records = Livewire::actingAs($this->admin(), 'web')
            ->test(TopPagesTable::class, ['pageFilters' => ['period' => 'last_7_days']])
            ->assertOk()
            ->instance()
            ->getTableRecords();

        $this->assertSame(['صفحه اصلی', 'فروشگاه'], $records->pluck('title')->all());
        $this->assertSame([1, 2], $records->pluck('rank')->all());
        $this->assertSame([75.0, 25.0], $records->pluck('share')->all());
    }

    public function test_the_user_types_doughnut_is_labelled_in_persian(): void
    {
        Analytics::fake(collect([
            ['newVsReturning' => 'new', 'activeUsers' => 30],
            ['newVsReturning' => 'returning', 'activeUsers' => 70],
        ]));

        $component = Livewire::actingAs($this->admin(), 'web')
            ->test(TrafficUserTypesChart::class, ['pageFilters' => ['period' => 'last_7_days']])
            ->assertOk();

        $data = $this->callProtected($component->instance(), 'getCachedData');

        $this->assertSame(['کاربران جدید', 'کاربران بازگشتی'], $data['labels']);
        $this->assertSame([30, 70], $data['datasets'][0]['data']);
        $this->assertSame('بازدیدکننده', $data['datasets'][0]['label']);
    }

    public function test_the_referrers_chart_strips_urls_down_to_their_host(): void
    {
        Analytics::fake(collect([
            ['pageReferrer' => 'https://news.google.com/rss/tech', 'screenPageViews' => 120],
            ['pageReferrer' => '(direct)', 'screenPageViews' => 80],
            ['pageReferrer' => '(not set)', 'screenPageViews' => 0],
        ]));

        $component = Livewire::actingAs($this->admin(), 'web')
            ->test(TrafficReferrersChart::class, ['pageFilters' => ['period' => 'last_7_days']])
            ->assertOk();

        $data = $this->callProtected($component->instance(), 'getCachedData');

        $this->assertSame(['news.google.com', 'ورود مستقیم'], $data['labels']);
        $this->assertSame([120, 80], $data['datasets'][0]['data']);
    }

    public function test_breakdown_charts_explain_an_empty_but_working_property(): void
    {
        Analytics::fake(collect());

        Livewire::actingAs($this->admin(), 'web')
            ->test(TrafficDeviceCategoriesChart::class, ['pageFilters' => ['period' => 'last_30_days']])
            ->assertOk()
            ->assertSee('داده‌ای برای نمایش نیست')
            ->assertSee('۳۰ روز اخیر');
    }

    public function test_breakdown_charts_surface_a_google_permission_failure(): void
    {
        Analytics::swap(new class
        {
            public function __call(string $method, array $arguments): never
            {
                throw new ApiException('User does not have sufficient permissions for this property.', 7);
            }
        });

        Livewire::actingAs($this->admin(), 'web')
            ->test(TrafficDeviceCategoriesChart::class, ['pageFilters' => ['period' => 'last_30_days']])
            ->assertOk()
            ->assertSee('دریافت داده از گوگل آنالیتیکس ناموفق بود')
            ->assertSee('دسترسی ندارد');
    }

    public function test_the_summary_widget_blames_a_property_that_stopped_receiving_traffic(): void
    {
        // Empty for the selected window and its predecessor, then the newest day
        // the property ever recorded a view.
        Analytics::swap(new class
        {
            public int $calls = 0;

            public function __call(string $method, array $arguments): mixed
            {
                $this->calls++;

                return $this->calls >= 3
                    ? collect([['date' => Carbon::today()->subDays(120)->startOfDay(), 'screenPageViews' => 4]])
                    : collect();
            }
        });

        Livewire::actingAs($this->admin(), 'web')
            ->test(TrafficSummaryStats::class, ['pageFilters' => ['period' => 'last_30_days']])
            ->assertOk()
            ->assertSee('در این بازه داده‌ای ثبت نشده')
            ->assertSee(Jalalian::fromCarbon(Carbon::today()->subDays(120))->format('Y/m/d'));
    }

    public function test_the_summary_widget_blames_the_property_id_when_nothing_was_ever_recorded(): void
    {
        Analytics::fake(collect());

        Livewire::actingAs($this->admin(), 'web')
            ->test(TrafficSummaryStats::class, ['pageFilters' => ['period' => 'last_30_days']])
            ->assertOk()
            ->assertSee('در این بازه داده‌ای ثبت نشده')
            ->assertSee('ANALYTICS_PROPERTY_ID');
    }

    public function test_the_summary_widget_surfaces_a_google_permission_failure(): void
    {
        Analytics::swap(new class
        {
            public function __call(string $method, array $arguments): never
            {
                throw new ApiException('User does not have sufficient permissions for this property.', 7);
            }
        });

        Livewire::actingAs($this->admin(), 'web')
            ->test(TrafficSummaryStats::class, ['pageFilters' => ['period' => 'last_30_days']])
            ->assertOk()
            ->assertSee('دریافت داده از گوگل آنالیتیکس ناموفق بود');
    }

    public function test_widgets_explain_how_to_configure_analytics_instead_of_failing(): void
    {
        config([
            'analytics.property_id' => null,
            'analytics.service_account_credentials_json' => '/tmp/missing-credentials.json',
        ]);

        Livewire::actingAs($this->admin(), 'web')
            ->test(TrafficSummaryStats::class, ['pageFilters' => ['period' => 'last_30_days']])
            ->assertOk()
            ->assertSee('گزارش ترافیک در دسترس نیست')
            ->assertSee('ANALYTICS_PROPERTY_ID');

        Livewire::actingAs($this->admin(), 'web')
            ->test(TrafficBrowsersChart::class, ['pageFilters' => ['period' => 'last_30_days']])
            ->assertOk()
            ->assertSee('گزارش ترافیک در دسترس نیست');
    }

    public function test_widgets_do_not_poll_the_google_api(): void
    {
        Analytics::fake($this->summaryRows());

        $html = Livewire::actingAs($this->admin(), 'web')
            ->test(TrafficSummaryStats::class)
            ->assertOk()
            ->html();

        $this->assertStringNotContainsString('wire:poll', $html);
    }

    private function callProtected(object $target, string $method): mixed
    {
        return (new \ReflectionMethod($target, $method))->invoke($target);
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function summaryRows(): Collection
    {
        return collect([
            [
                'totalUsers' => '1234',
                'screenPageViews' => '5678',
                'sessions' => '890',
                'bounceRate' => '0.4231',
                'averageSessionDuration' => '125.4',
            ],
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create()->assignRole(Role::Admin->value);

        $this->assertTrue($user->hasPermissionTo(Permission::AnalyticsReportView->value));

        return $user;
    }
}
