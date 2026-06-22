<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [
            ['id' => 1, 'name' => 'آذربایجان شرقی', 'slug' => 'azarbayjan-e-sharqi'],
            ['id' => 2, 'name' => 'آذربایجان غربی', 'slug' => 'azarbayjan-e-gharbi'],
            ['id' => 3, 'name' => 'اردبیل', 'slug' => 'ardabil'],
            ['id' => 4, 'name' => 'اصفهان', 'slug' => 'isfahan'],
            ['id' => 5, 'name' => 'البرز', 'slug' => 'alborz'],
            ['id' => 6, 'name' => 'ایلام', 'slug' => 'ilam'],
            ['id' => 7, 'name' => 'بوشهر', 'slug' => 'bushehr'],
            ['id' => 8, 'name' => 'تهران', 'slug' => 'tehran'],
            ['id' => 9, 'name' => 'چهارمحال و بختیاری', 'slug' => 'chahar-mahal-and-bakhtiari'],
            ['id' => 10, 'name' => 'خراسان جنوبی', 'slug' => 'khorasan-e-jonubi'],
            ['id' => 11, 'name' => 'خراسان رضوی', 'slug' => 'khorasan-e-razavi'],
            ['id' => 12, 'name' => 'خراسان شمالی', 'slug' => 'khorasan-e-shomali'],
            ['id' => 13, 'name' => 'خوزستان', 'slug' => 'khuzestan'],
            ['id' => 14, 'name' => 'زنجان', 'slug' => 'zanjan'],
            ['id' => 15, 'name' => 'سمنان', 'slug' => 'semnan'],
            ['id' => 16, 'name' => 'سیستان و بلوچستان', 'slug' => 'sistan-and-baluchestan'],
            ['id' => 17, 'name' => 'فارس', 'slug' => 'fars'],
            ['id' => 18, 'name' => 'قزوین', 'slug' => 'qazvin'],
            ['id' => 19, 'name' => 'قم', 'slug' => 'qom'],
            ['id' => 20, 'name' => 'کردستان', 'slug' => 'kurdistan'],
            ['id' => 21, 'name' => 'کرمان', 'slug' => 'kerman'],
            ['id' => 22, 'name' => 'کرمانشاه', 'slug' => 'kermanshah'],
            ['id' => 23, 'name' => 'کهگیلویه و بویراحمد', 'slug' => 'kohgiluyeh-and-boyer-ahmad'],
            ['id' => 24, 'name' => 'گلستان', 'slug' => 'golestan'],
            ['id' => 25, 'name' => 'گیلان', 'slug' => 'gilan'],
            ['id' => 26, 'name' => 'لرستان', 'slug' => 'lorestan'],
            ['id' => 27, 'name' => 'مازندران', 'slug' => 'mazandaran'],
            ['id' => 28, 'name' => 'مرکزی', 'slug' => 'markazi'],
            ['id' => 29, 'name' => 'هرمزگان', 'slug' => 'hormozgan'],
            ['id' => 30, 'name' => 'همدان', 'slug' => 'hamadan'],
            ['id' => 31, 'name' => 'یزد', 'slug' => 'yazd'],
        ];

        foreach ($provinces as $province) {
            Province::updateOrCreate(
                ['id' => $province['id']],
                $province
            );
        }
    }
}
