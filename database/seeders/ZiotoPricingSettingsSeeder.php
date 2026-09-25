<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Pricing\PricingSettings;
use Illuminate\Database\Seeder;

class ZiotoPricingSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Coefficients
            ['key' => 'zioto_pricing_coef_gold750_to_gold995', 'value' => '1.3333', 'type' => 'number', 'label' => 'ضریب ۷۵۰ به ۹۹۵', 'sort_order' => 3],
            ['key' => 'zioto_pricing_coef_gold995_to_gold9999', 'value' => '1.005', 'type' => 'number', 'label' => 'ضریب ۹۹۵ به ۹۹۹.۹', 'sort_order' => 4],
            ['key' => 'zioto_pricing_coef_buy_price', 'value' => '0.99', 'type' => 'number', 'label' => 'ضریب قیمت خرید', 'sort_order' => 5],
            ['key' => 'zioto_pricing_coef_silver999_to_silver9999', 'value' => '1.04', 'type' => 'number', 'label' => 'ضریب نقره ۹۹۹ به ۹۹۹.۹', 'sort_order' => 6],
            ['key' => 'zioto_pricing_coef_silver9999_to_silver925_sell', 'value' => '0.925', 'type' => 'number', 'label' => 'ضریب فروش ۹۹۹.۹ به ۹۲۵', 'sort_order' => 7],
            ['key' => 'zioto_pricing_coef_silver9999_to_silver925_buy', 'value' => '0.975', 'type' => 'number', 'label' => 'ضریب خرید ۹۹۹.۹ به ۹۲۵', 'sort_order' => 8],

            // Manual prices
            ['key' => 'zioto_pricing_manual_gold750_sell', 'value' => '', 'type' => 'number', 'label' => 'قیمت دستی فروش طلای ۷۵۰', 'sort_order' => 10],
            ['key' => 'zioto_pricing_manual_silver9999_sell', 'value' => '', 'type' => 'number', 'label' => 'قیمت دستی فروش نقره ۹۹۹.۹', 'sort_order' => 11],

            // Board / cache
            ['key' => 'zioto_pricing_cache_duration_seconds', 'value' => '300', 'type' => 'integer', 'label' => 'مدت کش تابلو (ثانیه)', 'sort_order' => 20],
            ['key' => 'zioto_pricing_frontend_refresh_seconds', 'value' => '300', 'type' => 'integer', 'label' => 'بازه رفرش فرانت (ثانیه)', 'sort_order' => 21],
            ['key' => 'zioto_pricing_stale_max_age_seconds', 'value' => '21600', 'type' => 'integer', 'label' => 'حداکثر سن داده کهنه (ثانیه)', 'sort_order' => 22],
            ['key' => 'zioto_pricing_stale_block', 'value' => 'false', 'type' => 'boolean', 'label' => 'مسدودسازی ارائه داده کهنه', 'sort_order' => 23],
            ['key' => 'zioto_pricing_trend_threshold', 'value' => '100', 'type' => 'integer', 'label' => 'آستانه روند قیمت (تومان)', 'sort_order' => 24],

            // Features
            ['key' => 'zioto_pricing_enable_dynamic_pricing', 'value' => 'true', 'type' => 'boolean', 'label' => 'فعال‌سازی قیمت‌گذاری پویا', 'sort_order' => 30],
            ['key' => 'zioto_pricing_round_prices', 'value' => 'false', 'type' => 'boolean', 'label' => 'گردکردن قیمت نهایی', 'sort_order' => 31],
            ['key' => 'zioto_pricing_round_to', 'value' => '1000', 'type' => 'integer', 'label' => 'گردکردن به مضرب', 'sort_order' => 32],
            ['key' => 'zioto_pricing_metal_categories', 'value' => '[]', 'type' => 'json', 'label' => 'دسته‌های فلزی (خالی = همه)', 'sort_order' => 33],
            ['key' => 'zioto_pricing_show_discount', 'value' => 'false', 'type' => 'boolean', 'label' => 'نمایش نشان تخفیف روی محصول', 'sort_order' => 34],

            // Sales control
            ['key' => 'zioto_pricing_disabled_products', 'value' => '[]', 'type' => 'json', 'label' => 'محصولات ممنوع از فروش', 'sort_order' => 40],
            ['key' => 'zioto_pricing_disabled_categories', 'value' => '[]', 'type' => 'json', 'label' => 'دسته‌های ممنوع از فروش', 'sort_order' => 41],
            ['key' => 'zioto_pricing_enable_sales_hours', 'value' => 'false', 'type' => 'boolean', 'label' => 'محدودیت ساعات فروش', 'sort_order' => 42],
            ['key' => 'zioto_pricing_sales_start_time', 'value' => '09:00', 'type' => 'string', 'label' => 'شروع ساعات فروش', 'sort_order' => 43],
            ['key' => 'zioto_pricing_sales_end_time', 'value' => '23:00', 'type' => 'string', 'label' => 'پایان ساعات فروش', 'sort_order' => 44],

            // Source toggles (DB-editable; tokens live in .env)
            ['key' => 'zioto_pricing_persian_api_enabled', 'value' => 'true', 'type' => 'boolean', 'label' => 'فعال‌سازی PersianAPI', 'sort_order' => 50],
            ['key' => 'zioto_pricing_tala_api_enabled', 'value' => 'true', 'type' => 'boolean', 'label' => 'فعال‌سازی Tala.ir', 'sort_order' => 51],

            // Labor roles / periods
            ['key' => 'zioto_pricing_labor_roles', 'value' => json_encode(PricingSettings::DEFAULT_LABOR_ROLES), 'type' => 'json', 'label' => 'نقش‌های لابور', 'sort_order' => 60],
            ['key' => 'zioto_pricing_time_periods', 'value' => json_encode(PricingSettings::DEFAULT_TIME_PERIODS), 'type' => 'json', 'label' => 'بازه‌های زمانی', 'sort_order' => 61],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting + ['category' => 'pricing'],
            );
        }
    }
}
