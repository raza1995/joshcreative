<?php

return [
    // Comma-separated list of allowed frontend origins that may call the Mycolean API
    // Example: https://mycolean.com,https://88d2e6-0c.myshopify.com,http://88d2e6-0c.myshopify.com
    'allowed_origins' => (function () {
        $raw = env('MYCOLEAN_ALLOWED_ORIGINS', 'https://mycolean.com,https://88d2e6-0c.myshopify.com,http://88d2e6-0c.myshopify.com,http://mycotracker.test,https://mycotracker.test, 
127.0.0.1');
        return collect(explode(',', $raw))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->all();
    })(),
];
