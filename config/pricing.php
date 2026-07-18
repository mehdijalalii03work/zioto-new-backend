<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pricing Mode
    |--------------------------------------------------------------------------
    |
    | Controls how product prices are calculated:
    |
    |   'dynamic' - Prices calculated from price board (tablo) + fee markup
    |   'direct'  - Prices fetched directly from Tokeniko shop API
    |
    */

    'mode' => env('PRICING_MODE', 'dynamic'),

];
