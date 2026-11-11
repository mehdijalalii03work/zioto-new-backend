<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'shahkar' => [
        'api_key' => env('SHAHKAR_API_KEY'),
        'secret_key' => env('SHAHKAR_SECRET_KEY'),
        'base_url' => env('SHAHKAR_BASE_URL', 'https://napi.jibit.ir/ide'),
    ],

    'digiexpress' => [
        'base_url' => env('DIGIEXPRESS_BASE_URL', 'https://api-demo.digiexpress.ir'),
        'username' => env('DIGIEXPRESS_USERNAME'),
        'password' => env('DIGIEXPRESS_PASSWORD'),
        'webhook_token' => env('DIGIEXPRESS_WEBHOOK_TOKEN'),
        'origin_city_id' => env('DIGIEXPRESS_ORIGIN_CITY_ID'),
        'origin_point_id' => env('DIGIEXPRESS_ORIGIN_POINT_ID'),
        'origin_address' => env('DIGIEXPRESS_ORIGIN_ADDRESS'),
        'origin_building_number' => env('DIGIEXPRESS_ORIGIN_BUILDING_NUMBER'),
        'origin_postal_code' => env('DIGIEXPRESS_ORIGIN_POSTAL_CODE'),
        'origin_latitude' => env('DIGIEXPRESS_ORIGIN_LATITUDE'),
        'origin_longitude' => env('DIGIEXPRESS_ORIGIN_LONGITUDE'),
        'sender_first_name' => env('DIGIEXPRESS_SENDER_FIRST_NAME'),
        'sender_last_name' => env('DIGIEXPRESS_SENDER_LAST_NAME'),
        'sender_national_code' => env('DIGIEXPRESS_SENDER_NATIONAL_CODE'),
        'sender_mobile' => env('DIGIEXPRESS_SENDER_MOBILE'),
        'sender_phone' => env('DIGIEXPRESS_SENDER_PHONE'),
    ],

];
