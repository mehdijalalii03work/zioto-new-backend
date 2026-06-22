<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(database_path('data/cities.json'));
        $cities = json_decode($json, true);

        foreach ($cities as $city) {
            City::updateOrCreate(
                [
                    'province_id' => $city['province_id'],
                    'slug' => $city['slug'],
                ],
                [
                    'name' => $city['name'],
                ]
            );
        }
    }
}
