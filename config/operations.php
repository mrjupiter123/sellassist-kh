<?php

return [
    'queue_stale_minutes' => (int) env('OPERATIONS_QUEUE_STALE_MINUTES', 15),
    'health_token' => env('OPERATIONS_HEALTH_TOKEN'),
    'heartbeat_max_age_minutes' => 15,
];
