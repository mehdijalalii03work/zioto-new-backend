<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Tax settings
            ['key' => 'tax_gold', 'value' => '0', 'type' => 'number', 'category' => 'tax', 'label' => 'درصد مالیات طلا', 'sort_order' => 1],
            ['key' => 'tax_silver', 'value' => '10', 'type' => 'number', 'category' => 'tax', 'label' => 'درصد مالیات نقره', 'sort_order' => 2],
            // Display settings
            ['key' => 'show_price_with_tax', 'value' => 'true', 'type' => 'boolean', 'category' => 'display', 'label' => 'نمایش قیمت با مالیات', 'sort_order' => 1],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }
}
