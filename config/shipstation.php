<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ShipStation API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for ShipStation API V1 integration.
    | Base URL: https://ssapi.shipstation.com/
    | Authentication: Basic Auth (API Key + Secret)
    |
    */

    'api_key' => env('SHIPSTATION_API_KEY', ''),
    'api_secret' => env('SHIPSTATION_API_SECRET', ''),
    'base_url' => env('SHIPSTATION_BASE_URL', 'https://ssapi.shipstation.com'),

    /*
    |--------------------------------------------------------------------------
    | Auto-Push Settings
    |--------------------------------------------------------------------------
    |
    | Controls whether orders are automatically pushed to ShipStation
    | after consolidation. Set to false for manual testing.
    |
    */

    'auto_push' => env('SHIPSTATION_AUTO_PUSH', false),
    
    /*
    |--------------------------------------------------------------------------
    | SKU Consolidation
    |--------------------------------------------------------------------------
    |
    | Enable/disable automatic consolidation of duplicate SKUs.
    | When enabled, line items with the same SKU will be merged.
    |
    */

    'consolidate_skus' => env('SHIPSTATION_CONSOLIDATE_SKUS', true),

    /*
    |--------------------------------------------------------------------------
    | Price Handling Strategy
    |--------------------------------------------------------------------------
    |
    | How to handle prices when consolidating duplicate SKUs:
    | - 'weighted_average': Calculate weighted average based on quantity
    | - 'lowest': Use the lowest price
    | - 'highest': Use the highest price
    | - 'first': Use the first occurrence price
    |
    */

    'price_strategy' => env('SHIPSTATION_PRICE_STRATEGY', 'weighted_average'),

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    |
    | ShipStation allows 40 requests per minute.
    | Configure rate limiting to prevent API throttling.
    |
    */

    'rate_limit' => [
        'requests_per_minute' => env('SHIPSTATION_RATE_LIMIT', 40),
        'delay_between_requests_ms' => env('SHIPSTATION_REQUEST_DELAY_MS', 1500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue settings for async job processing.
    |
    */

    'queue' => [
        'connection' => env('SHIPSTATION_QUEUE_CONNECTION', 'default'),
        'name' => env('SHIPSTATION_QUEUE_NAME', 'shipstation'),
        'retry_after' => env('SHIPSTATION_QUEUE_RETRY_AFTER', 180), // seconds
        'max_tries' => env('SHIPSTATION_QUEUE_MAX_TRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Shipping Carrier
    |--------------------------------------------------------------------------
    |
    | Default carrier code for ShipStation orders if not specified.
    | Common codes: usps, ups, fedex, dhl_express
    |
    */

    'default_carrier_code' => env('SHIPSTATION_DEFAULT_CARRIER', 'usps'),
    'default_service_code' => env('SHIPSTATION_DEFAULT_SERVICE', 'usps_first_class_mail'),

    /*
    |--------------------------------------------------------------------------
    | Order Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for ShipStation order keys to identify source.
    | Example: SHOPIFY-12345
    |
    */

    'order_key_prefix' => env('SHIPSTATION_ORDER_KEY_PREFIX', 'SHOPIFY'),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable detailed logging for debugging.
    |
    */

    'logging' => [
        'enabled' => env('SHIPSTATION_LOGGING_ENABLED', true),
        'log_api_requests' => env('SHIPSTATION_LOG_API_REQUESTS', true),
        'log_api_responses' => env('SHIPSTATION_LOG_API_RESPONSES', false), // Can be verbose
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Skip orders that don't meet these criteria.
    |
    */

    'validation' => [
        'require_shipping_address' => false,  // Set to false for orders without shipping (digital, pickup, etc)
        'require_line_items' => true,
        'minimum_order_total' => 0, // Don't push orders below this amount
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, orders are consolidated but NOT pushed to ShipStation.
    | Useful for testing consolidation logic without affecting live data.
    |
    */

    'test_mode' => env('SHIPSTATION_TEST_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Sync Strategy
    |--------------------------------------------------------------------------
    |
    | How to handle orders that already exist in ShipStation:
    | - 'create_only': Only create new orders, skip if exists
    | - 'update_if_exists': Check if exists, update if found, create if not
    | - 'always_create': Always create (may cause duplicates)
    |
    */

    'sync_strategy' => env('SHIPSTATION_SYNC_STRATEGY', 'update_if_exists'),

    /*
    |--------------------------------------------------------------------------
    | Update Existing Orders
    |--------------------------------------------------------------------------
    |
    | When enabled, orders that already exist in ShipStation will be updated.
    | When disabled, existing orders will be skipped.
    |
    */

    'update_existing' => env('SHIPSTATION_UPDATE_EXISTING', true),

    /*
    |--------------------------------------------------------------------------
    | Webhook Delay
    |--------------------------------------------------------------------------
    |
    | Delay (in minutes) before processing order from webhook.
    | Useful when ShipStation auto-syncs orders and you want to ensure
    | ShipStation creates the order first, then we update with consolidated SKUs.
    | 
    | Set to 0 for immediate processing (if ShipStation auto-sync is disabled)
    | Set to 65+ if ShipStation syncs every hour (let it sync first)
    |
    */

    'webhook_delay_minutes' => env('SHIPSTATION_WEBHOOK_DELAY_MINUTES', 0),

    /*
    |--------------------------------------------------------------------------
    | Only Update Existing
    |--------------------------------------------------------------------------
    |
    | If true, only update orders that already exist in ShipStation.
    | Never create new orders. Useful when ShipStation auto-sync is primary.
    |
    */

    'only_update_existing' => env('SHIPSTATION_ONLY_UPDATE_EXISTING', false),

];

