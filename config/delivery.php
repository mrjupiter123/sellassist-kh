<?php

return [
    'adapters' => [
        'l192' => [
            'base_url' => env('L192_API_BASE_URL', 'https://developer-stage.l192.com'),
            'token' => env('L192_API_TOKEN'),
            'auth_header' => env('L192_AUTH_HEADER', 'Authorization'),
            'auth_prefix' => env('L192_AUTH_PREFIX', 'Bearer'),
            'sender' => [
                'name' => env('L192_SENDER_NAME'),
                'phone_number' => env('L192_SENDER_PHONE'),
                'address_name' => env('L192_SENDER_ADDRESS'),
                'lat' => env('L192_SENDER_LAT'),
                'lng' => env('L192_SENDER_LNG'),
            ],
        ],
    ],
    'webhooks' => [
        'secrets' => [
            'l192' => env('L192_WEBHOOK_SECRET'),
        ],
    ],
];
