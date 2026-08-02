<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        env('APP_URL'),
        'https://sawiss.com',
        'https://pay.sawiss.com',
        'https://backend.sawiss.com',
        // stage
        'http://194.5.188.212:3001',
        'http://194.5.188.212:8001',
        // local
        'http://localhost:3000',
        'http://localhost:8000',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'Authorization',
        'Accept',
        'Origin',
        'Access-Control-Allow-Origin',
        'X-XSRF-TOKEN',
        'Cache-Control',
        'Pragma',
        'X-Platform',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,
];
