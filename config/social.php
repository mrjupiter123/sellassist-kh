<?php

return [
    'meta' => [
        'app_secret' => env('META_APP_SECRET'),
        'verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
        'graph_version' => env('META_GRAPH_VERSION', 'v26.0'),
    ],
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],
    'ai' => [
        'enabled' => env('SOCIAL_AI_EXTRACTION_ENABLED', false),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_ORDER_EXTRACTION_MODEL', 'gpt-5.4-mini'),
        'base_url' => env('OPENAI_API_BASE_URL', 'https://api.openai.com/v1'),
        'low_confidence_threshold' => env('SOCIAL_AI_LOW_CONFIDENCE_THRESHOLD', 0.65),
        'prompt_version' => env('SOCIAL_AI_PROMPT_VERSION', 'builtin-v1'),
        'evaluation_pass_threshold' => env('SOCIAL_AI_EVALUATION_PASS_THRESHOLD', 0.85),
        'evaluation_approval_threshold' => env('SOCIAL_AI_EVALUATION_APPROVAL_THRESHOLD', 0.85),
        'evaluation_regression_tolerance' => env('SOCIAL_AI_EVALUATION_REGRESSION_TOLERANCE', 0.02),
        'release_monitoring' => [
            'window_days' => env('SOCIAL_AI_RELEASE_MONITORING_WINDOW_DAYS', 14),
            'minimum_samples' => env('SOCIAL_AI_RELEASE_MONITORING_MINIMUM_SAMPLES', 10),
            'minimum_reviews' => env('SOCIAL_AI_RELEASE_MONITORING_MINIMUM_REVIEWS', 5),
            'success_rate_drop' => env('SOCIAL_AI_RELEASE_SUCCESS_RATE_DROP', 0.10),
            'confidence_drop' => env('SOCIAL_AI_RELEASE_CONFIDENCE_DROP', 0.10),
            'useful_rate_drop' => env('SOCIAL_AI_RELEASE_USEFUL_RATE_DROP', 0.15),
        ],
    ],
];
