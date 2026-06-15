<?php

return [
    'default' => env('SMS_DRIVER', 'smsir'),

    'smsir' => [
        'api_key' => env('SMSIR_API_KEY'),
        'line_number' => env('SMSIR_LINE_NUMBER'),
        'template_id' => env('SMSIR_OTP_TEMPLATE_ID'),
        'username' => env('SMSIR_USERNAME'),
        'password' => env('SMSIR_PASSWORD'),
    ],
];
