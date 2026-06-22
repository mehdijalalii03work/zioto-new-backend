<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        ShippingMethod::insert([
            [
                'id' => 1,
                'code' => 'in_store_pickup',
                'name' => 'تحویل حضوری',
                'description' => 'مراجعه حضوری به دفتر شرکت برای دریافت سفارش',
                'icon' => 'heroicon-o-building-storefront',
                'is_active' => true,
                'is_pickup' => true,
                'sort_order' => 1,
            ],
            [
                'id' => 2,
                'code' => 'tipax',
                'name' => 'تیپاکس',
                'description' => 'ارسال با تیپاکس به سراسر کشور',
                'icon' => 'heroicon-o-truck',
                'is_active' => true,
                'is_pickup' => false,
                'sort_order' => 2,
            ],
            [
                'id' => 3,
                'code' => 'peyk',
                'name' => 'پیک شهری',
                'description' => 'ارسال با پیک در محدوده شهر تهران',
                'icon' => 'heroicon-o-bicycle',
                'is_active' => true,
                'is_pickup' => false,
                'sort_order' => 3,
            ],
            [
                'id' => 4,
                'code' => 'post_precious',
                'name' => 'پست فلزات گرانبها',
                'description' => 'پست ویژه و بیمه‌شده برای امانات گرانبها',
                'icon' => 'heroicon-o-envelope',
                'is_active' => true,
                'is_pickup' => false,
                'sort_order' => 4,
            ],
        ]);

        ShippingRate::create([
            'shipping_method_id' => 1,
            'rate_type' => 'flat',
            'base_rate' => 0,
            'estimated_days_min' => 0,
            'estimated_days_max' => 1,
        ]);

        ShippingRate::create([
            'shipping_method_id' => 2,
            'rate_type' => 'weight',
            'min_weight' => 0,
            'max_weight' => 200,
            'base_rate' => 150000,
            'per_kg_rate' => 0,
            'estimated_days_min' => 2,
            'estimated_days_max' => 5,
        ]);

        ShippingRate::create([
            'shipping_method_id' => 2,
            'rate_type' => 'weight',
            'min_weight' => 201,
            'max_weight' => 1000,
            'base_rate' => 200000,
            'per_kg_rate' => 50000,
            'estimated_days_min' => 2,
            'estimated_days_max' => 5,
        ]);

        ShippingRate::create([
            'shipping_method_id' => 3,
            'rate_type' => 'flat',
            'base_rate' => 150000,
            'estimated_days_min' => 0,
            'estimated_days_max' => 0,
        ]);

        ShippingRate::create([
            'shipping_method_id' => 4,
            'rate_type' => 'province',
            'province_id' => 1,
            'base_rate' => 200000,
            'estimated_days_min' => 3,
            'estimated_days_max' => 7,
        ]);

        ShippingRate::create([
            'shipping_method_id' => 4,
            'rate_type' => 'province',
            'province_id' => 2,
            'base_rate' => 200000,
            'estimated_days_min' => 3,
            'estimated_days_max' => 7,
        ]);

        ShippingRate::create([
            'shipping_method_id' => 4,
            'rate_type' => 'province',
            'province_id' => 3,
            'base_rate' => 250000,
            'estimated_days_min' => 3,
            'estimated_days_max' => 7,
        ]);

        ShippingRate::create([
            'shipping_method_id' => 4,
            'rate_type' => 'province',
            'province_id' => 4,
            'base_rate' => 300000,
            'estimated_days_min' => 3,
            'estimated_days_max' => 7,
        ]);

        $provinceRates = [
            5 => 200000, 6 => 300000, 7 => 300000, 8 => 200000,
            9 => 250000, 10 => 350000, 11 => 300000, 12 => 350000,
            13 => 300000, 14 => 250000, 15 => 250000, 16 => 400000,
            17 => 300000, 18 => 250000, 19 => 200000, 20 => 300000,
            21 => 300000, 22 => 300000, 23 => 350000, 24 => 300000,
            25 => 300000, 26 => 300000, 27 => 300000, 28 => 250000,
            29 => 350000, 30 => 300000, 31 => 350000,
        ];

        foreach ($provinceRates as $provinceId => $baseRate) {
            ShippingRate::create([
                'shipping_method_id' => 4,
                'rate_type' => 'province',
                'province_id' => $provinceId,
                'base_rate' => $baseRate,
                'estimated_days_min' => 3,
                'estimated_days_max' => 7,
            ]);
        }
    }
}
