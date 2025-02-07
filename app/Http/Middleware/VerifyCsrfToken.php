<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'shopify/webhook/orders', // Exclude Shopify Webhook from CSRF Protection
        'shopify/webhook/fulfillment',
        '/slack/events',
        '/gmail/webhook',
        '/gmail/callback',
        '/gmail/watch'
        // Exclude Shopify Webhook from CSRF Protection
    ];
    
}
