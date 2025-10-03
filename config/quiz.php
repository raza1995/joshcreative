<?php

return [
    // Comma-separated list of allowed frontend origins that may call the Quiz API
    // Example: https://mycolean.com,https://88d2e6-0c.myshopify.com,http://88d2e6-0c.myshopify.com
    'allowed_origins' => (function () {
        $raw = env('QUIZ_ALLOWED_ORIGINS', 'https://mycolean.com,https://88d2e6-0c.myshopify.com,http://88d2e6-0c.myshopify.com,http://joshcreative.test,https://joshcreative.test,127.0.0.1,localhost');
        return collect(explode(',', $raw))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->all();
    })(),

    // Default quiz settings
    'default_quiz_type' => env('QUIZ_DEFAULT_TYPE', 'audit'),
    'default_quiz_version' => env('QUIZ_DEFAULT_VERSION', '1.0'),
    
    // Analytics settings
    'track_detailed_analytics' => env('QUIZ_TRACK_ANALYTICS', true),
    'store_user_agents' => env('QUIZ_STORE_USER_AGENTS', true),
    'store_ip_addresses' => env('QUIZ_STORE_IPS', true),
    
    // Session settings
    'session_timeout_minutes' => env('QUIZ_SESSION_TIMEOUT', 60), // 1 hour
    'allow_anonymous_sessions' => env('QUIZ_ALLOW_ANONYMOUS', true),
    
    // Rate limiting
    'rate_limit_per_ip_per_hour' => env('QUIZ_RATE_LIMIT', 10),
    
    // Data retention
    'data_retention_days' => env('QUIZ_DATA_RETENTION_DAYS', 365), // 1 year
];
