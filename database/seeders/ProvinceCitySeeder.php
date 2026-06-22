<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceCitySeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [
            ['id' => 1, 'name' => 'آذربایجان شرقی', 'slug' => 'azarbaijan-sharghi'],
            ['id' => 2, 'name' => 'آذربایجان غربی', 'slug' => 'azarbaijan-gharbi'],
            ['id' => 3, 'name' => 'اردبیل', 'slug' => 'ardabil'],
            ['id' => 4, 'name' => 'اصفهان', 'slug' => 'isfahan'],
            ['id' => 5, 'name' => 'البرز', 'slug' => 'alborz'],
            ['id' => 6, 'name' => 'ایلام', 'slug' => 'ilam'],
            ['id' => 7, 'name' => 'بوشهر', 'slug' => 'bushehr'],
            ['id' => 8, 'name' => 'تهران', 'slug' => 'tehran'],
            ['id' => 9, 'name' => 'چهارمحال و بختیاری', 'slug' => 'chaharmahal-bakhtiari'],
            ['id' => 10, 'name' => 'خراسان جنوبی', 'slug' => 'khorasan-jonubi'],
            ['id' => 11, 'name' => 'خراسان رضوی', 'slug' => 'khorasan-razavi'],
            ['id' => 12, 'name' => 'خراسان شمالی', 'slug' => 'khorasan-shomali'],
            ['id' => 13, 'name' => 'خوزستان', 'slug' => 'khuzestan'],
            ['id' => 14, 'name' => 'زنجان', 'slug' => 'zanjan'],
            ['id' => 15, 'name' => 'سمنان', 'slug' => 'semnan'],
            ['id' => 16, 'name' => 'سیستان و بلوچستان', 'slug' => 'sistan-baluchestan'],
            ['id' => 17, 'name' => 'فارس', 'slug' => 'fars'],
            ['id' => 18, 'name' => 'قزوین', 'slug' => 'qazvin'],
            ['id' => 19, 'name' => 'قم', 'slug' => 'qom'],
            ['id' => 20, 'name' => 'کردستان', 'slug' => 'kordestan'],
            ['id' => 21, 'name' => 'کرمان', 'slug' => 'kerman'],
            ['id' => 22, 'name' => 'کرمانشاه', 'slug' => 'kermanshah'],
            ['id' => 23, 'name' => 'کهگیلویه و بویراحمد', 'slug' => 'kohgiluyeh-boyer'],
            ['id' => 24, 'name' => 'گلستان', 'slug' => 'golestan'],
            ['id' => 25, 'name' => 'گیلان', 'slug' => 'gilan'],
            ['id' => 26, 'name' => 'لرستان', 'slug' => 'lorestan'],
            ['id' => 27, 'name' => 'مازندران', 'slug' => 'mazandaran'],
            ['id' => 28, 'name' => 'مرکزی', 'slug' => 'markazi'],
            ['id' => 29, 'name' => 'هرمزگان', 'slug' => 'hormozgan'],
            ['id' => 30, 'name' => 'همدان', 'slug' => 'hamedan'],
            ['id' => 31, 'name' => 'یزد', 'slug' => 'yazd'],
        ];

        Province::insert($provinces);

        // Sample cities for each province (capital cities)
        $cities = [
            ['province_id' => 1, 'name' => 'تبریز', 'slug' => 'tabriz'],
            ['province_id' => 2, 'name' => 'ارومیه', 'slug' => 'urmia'],
            ['province_id' => 3, 'name' => 'اردبیل', 'slug' => 'ardabil'],
            ['province_id' => 4, 'name' => 'اصفهان', 'slug' => 'isfahan'],
            ['province_id' => 5, 'name' => 'کرج', 'slug' => 'karaj'],
            ['province_id' => 6, 'name' => 'ایلام', 'slug' => 'ilam'],
            ['province_id' => 7, 'name' => 'بوشهر', 'slug' => 'bushehr'],
            ['province_id' => 8, 'name' => 'تهران', 'slug' => 'tehran'],
            ['province_id' => 9, 'name' => 'شهرکرد', 'slug' => 'shahrekord'],
            ['province_id' => 10, 'name' => 'بیرجند', 'slug' => 'birjand'],
            ['province_id' => 11, 'name' => 'مشهد', 'slug' => 'mashhad'],
            ['province_id' => 12, 'name' => 'بجنورد', 'slug' => 'bojnord'],
            ['province_id' => 13, 'name' => 'اهواز', 'slug' => 'ahvaz'],
            ['province_id' => 14, 'name' => 'زنجان', 'slug' => 'zanjan'],
            ['province_id' => 15, 'name' => 'سمنان', 'slug' => 'semnan'],
            ['province_id' => 16, 'name' => 'زاهدان', 'slug' => 'zahedan'],
            ['province_id' => 17, 'name' => 'شیراز', 'slug' => 'shiraz'],
            ['province_id' => 18, 'name' => 'قزوین', 'slug' => 'qazvin'],
            ['province_id' => 19, 'name' => 'قم', 'slug' => 'qom'],
            ['province_id' => 20, 'name' => 'سنندج', 'slug' => 'sanandaj'],
            ['province_id' => 21, 'name' => 'کرمان', 'slug' => 'kerman'],
            ['province_id' => 22, 'name' => 'کرمانشاه', 'slug' => 'kermanshah'],
            ['province_id' => 23, 'name' => 'یاسوج', 'slug' => 'yasuj'],
            ['province_id' => 24, 'name' => 'گرگان', 'slug' => 'gorgan'],
            ['province_id' => 25, 'name' => 'رشت', 'slug' => 'rasht'],
            ['province_id' => 26, 'name' => 'خرم‌آباد', 'slug' => 'khorramabad'],
            ['province_id' => 27, 'name' => 'ساری', 'slug' => 'sari'],
            ['province_id' => 28, 'name' => 'اراک', 'slug' => 'arak'],
            ['province_id' => 29, 'name' => 'بندرعباس', 'slug' => 'bandarabbas'],
            ['province_id' => 30, 'name' => 'همدان', 'slug' => 'hamedan'],
            ['province_id' => 31, 'name' => 'یزد', 'slug' => 'yazd'],
        ];

        City::insert($cities);
    }
}
