<?php

namespace App\Support;

/**
 * Translates the raw English dimension values returned by the GA4 Data API into
 * Persian labels. Unknown values pass through unchanged so nothing is ever lost.
 */
final class AnalyticsLabels
{
    /**
     * @var array<string, string>
     */
    private const COUNTRIES = [
        'Iran' => 'ایران',
        'United States' => 'ایالات متحده',
        'United Kingdom' => 'بریتانیا',
        'Canada' => 'کانادا',
        'Germany' => 'آلمان',
        'France' => 'فرانسه',
        'Netherlands' => 'هلند',
        'Belgium' => 'بلژیک',
        'Spain' => 'اسپانیا',
        'Italy' => 'ایتالیا',
        'Portugal' => 'پرتغال',
        'Sweden' => 'سوئد',
        'Norway' => 'نروژ',
        'Denmark' => 'دانمارک',
        'Finland' => 'فنلاند',
        'Poland' => 'لهستان',
        'Austria' => 'اتریش',
        'Switzerland' => 'سوئیس',
        'Greece' => 'یونان',
        'Russia' => 'روسیه',
        'Turkey' => 'ترکیه',
        'United Arab Emirates' => 'امارات متحده عربی',
        'Saudi Arabia' => 'عربستان',
        'Iraq' => 'عراق',
        'Syria' => 'سوریه',
        'Lebanon' => 'لبنان',
        'Jordan' => 'اردن',
        'Qatar' => 'قطر',
        'Kuwait' => 'کویت',
        'Oman' => 'عمان',
        'Bahrain' => 'بحرین',
        'Israel' => 'اسرائیل',
        'Afghanistan' => 'افغانستان',
        'Pakistan' => 'پاکستان',
        'India' => 'هند',
        'China' => 'چین',
        'Japan' => 'ژاپن',
        'South Korea' => 'کره جنوبی',
        'Indonesia' => 'اندونزی',
        'Malaysia' => 'مالزی',
        'Australia' => 'استرالیا',
        'New Zealand' => 'نیوزیلند',
        'Brazil' => 'برزیل',
        'Mexico' => 'مکزیک',
        'Argentina' => 'آرژانتین',
        'Egypt' => 'مصر',
        'South Africa' => 'آفریقای جنوبی',
    ];

    /**
     * @var array<string, string>
     */
    private const DEVICE_CATEGORIES = [
        'mobile' => 'موبایل',
        'desktop' => 'دسکتاپ',
        'tablet' => 'تبلت',
        'smart tv' => 'تلویزیون هوشمند',
        'smart display' => 'نمایشگر هوشمند',
        'wearable' => 'پوشیدنی',
        'console' => 'کنسول',
    ];

    /**
     * @var array<string, string>
     */
    private const USER_TYPES = [
        'new' => 'کاربران جدید',
        'returning' => 'کاربران بازگشتی',
    ];

    /**
     * @var array<string, string>
     */
    private const UNKNOWN_VALUES = [
        '(direct)' => 'ورود مستقیم',
        '(not set)' => 'نامشخص',
        '(none)' => 'نامشخص',
        '(other)' => 'سایر',
    ];

    public static function country(?string $value): string
    {
        $value = trim((string) $value);

        return self::COUNTRIES[$value] ?? self::unknown($value);
    }

    public static function deviceCategory(?string $value): string
    {
        $value = trim((string) $value);

        return self::DEVICE_CATEGORIES[strtolower($value)] ?? self::unknown($value);
    }

    public static function userType(?string $value): string
    {
        $value = trim((string) $value);

        return self::USER_TYPES[strtolower($value)] ?? self::unknown($value);
    }

    public static function referrer(?string $value): string
    {
        $value = trim((string) $value);

        return self::UNKNOWN_VALUES[strtolower($value)] ?? self::host($value);
    }

    public static function unknown(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'نامشخص';
        }

        return self::UNKNOWN_VALUES[strtolower($value)] ?? $value;
    }

    /**
     * Long referrer strings are usually a full URL; show just the host.
     */
    private static function host(string $value): string
    {
        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $value;
    }
}
