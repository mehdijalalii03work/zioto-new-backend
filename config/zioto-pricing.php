<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PersianAPI (TGJU) Source
    |--------------------------------------------------------------------------
    */

    'persian_api' => [
        'enabled' => env('ZIOTO_PERSIAN_API_ENABLED', true),
        'url' => env('ZIOTO_PERSIAN_API_URL', 'https://studio.persianapi.com/index.php/web-service/common/gold-currency-coin?format=json&limit=30&page=1'),
        'token' => env('ZIOTO_PERSIAN_API_TOKEN', '35xgstesrimm9xbkduoy'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tala.ir Source
    |--------------------------------------------------------------------------
    */

    'tala_api' => [
        'enabled' => env('ZIOTO_TALA_API_ENABLED', true),
        'token' => env('ZIOTO_TALA_API_TOKEN', '9ECFEB063AB3C1504403629F4B4D0EB4C4855C580922AFAA42A4848B608948BC'),
        'cookie' => env('ZIOTO_TALA_API_COOKIE', ''),
        'base_url' => env('ZIOTO_TALA_API_URL', 'https://api.tala.ir/v1/rates'),
        'keys' => env('ZIOTO_TALA_API_KEYS', 'silver,bazartehran,ounce,geram18k,sekke-jad,sekke-gad,sekke-nim,sekke-rob,geram24k,sekke-grm'),
    ],

];
