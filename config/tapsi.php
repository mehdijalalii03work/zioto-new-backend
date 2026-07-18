<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tapsi Shop API
    |--------------------------------------------------------------------------
    */

    'enabled' => env('TAPSI_SYNC_ENABLED', false),
    'auth_token' => env('TAPSI_AUTH_TOKEN', ''),
    'auth_name' => env('TAPSI_AUTH_NAME', 'zioto_sync_node'),
    'base_url' => env('TAPSI_API_BASE_URL', 'https://vendorgw.tapsi.shop/web/hub/vendors/v1'),

    /*
    |--------------------------------------------------------------------------
    | Price Markup
    |--------------------------------------------------------------------------
    |
    | Additional percentage added to prices before sending to Tapsi Shop.
    | Threshold is in Toman.
    |
    */

    'markup_threshold' => 50_000_000,
    'markup_below_threshold' => (int) env('TAPSI_MARKUP_BELOW_50M', 2),
    'markup_above_threshold' => (int) env('TAPSI_MARKUP_ABOVE_50M', 1),

];
