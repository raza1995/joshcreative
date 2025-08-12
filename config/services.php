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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],
    'fb_accounts' => json_decode(env('FB_ACCOUNTS', '{}'), true),

    'klaviyo' => [
    'private_key'     => env('KLAVIYO_PRIVATE_KEY'),
    'revision_stable' => env('KLAVIYO_REVISION_STABLE', '2025-07-15'),
    'revision_beta'   => env('KLAVIYO_REVISION_BETA', '2024-07-15.pre'),
],


];
