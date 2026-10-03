<?php

namespace Tests\Unit;

use App\Support\AnalyticsLabels;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AnalyticsLabelsTest extends TestCase
{
    /** @return array<string, array{0: string|null, 1: string}> */
    public static function countryProvider(): array
    {
        return [
            'known country' => ['Iran', 'ایران'],
            'unknown country passes through' => ['Wakanda', 'Wakanda'],
            'blank' => ['', 'نامشخص'],
            'null' => [null, 'نامشخص'],
        ];
    }

    #[DataProvider('countryProvider')]
    public function test_it_translates_country_names(?string $value, string $expected): void
    {
        $this->assertSame($expected, AnalyticsLabels::country($value));
    }

    /** @return array<string, array{0: string|null, 1: string}> */
    public static function deviceCategoryProvider(): array
    {
        return [
            'mobile' => ['mobile', 'موبایل'],
            'uppercase' => ['Desktop', 'دسکتاپ'],
            'smart tv' => ['smart tv', 'تلویزیون هوشمند'],
            'unknown' => ['toaster', 'toaster'],
        ];
    }

    #[DataProvider('deviceCategoryProvider')]
    public function test_it_translates_device_categories(?string $value, string $expected): void
    {
        $this->assertSame($expected, AnalyticsLabels::deviceCategory($value));
    }

    public function test_it_translates_user_types(): void
    {
        $this->assertSame('کاربران جدید', AnalyticsLabels::userType('new'));
        $this->assertSame('کاربران بازگشتی', AnalyticsLabels::userType('returning'));
        $this->assertSame('نامشخص', AnalyticsLabels::userType(null));
    }

    public function test_it_shortens_referrers_to_their_host(): void
    {
        $this->assertSame('news.google.com', AnalyticsLabels::referrer('https://news.google.com/rss/tech'));
        $this->assertSame('l.instagram.com', AnalyticsLabels::referrer('https://l.instagram.com/?u=1'));
        $this->assertSame('ورود مستقیم', AnalyticsLabels::referrer('(direct)'));
        $this->assertSame('نامشخص', AnalyticsLabels::referrer('(not set)'));
    }

    public function test_unknown_falls_back_to_the_raw_value(): void
    {
        $this->assertSame('Samsung Internet', AnalyticsLabels::unknown('Samsung Internet'));
        $this->assertSame('نامشخص', AnalyticsLabels::unknown('  '));
    }
}
