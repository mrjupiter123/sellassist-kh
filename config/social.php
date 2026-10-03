<?php

return [
    'meta' => [
        'app_secret' => env('META_APP_SECRET'),
        'verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
    ],
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],
];
