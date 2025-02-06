<?php
// app/Http/Middleware/PublicUrlMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PublicUrlMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // List of public URLs (no authentication required)
        $publicUrls = [
            '/slack/oauth/callback',
            '/slack/webhook',
            '/public-api/data',
        ];

        // Check if the current request matches a public URL
        if (in_array($request->path(), $publicUrls)) {
            return $next($request); // Allow public access
        }

        // Apply authentication for all other routes
        if (auth()->check()) {
            return $next($request);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
