<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyMycoleanOrigin
{
    public function handle(Request $request, Closure $next)
    {
        // Allow server-side internal calls (no CORS) and OPTIONS preflight handled by CORS package
        $origin = $request->headers->get('Origin');
        $referer = $request->headers->get('Referer');

        // Normalize allowed origins from config
        $allowed = collect(config('mycolean.allowed_origins', []))
            ->filter()
            ->map(function ($o) { return rtrim(strtolower($o), '/'); })
            ->unique()
            ->values()
            ->all();

        if (!$origin && !$referer) {
            // If no origin/referer (e.g., server-to-server), allow
            return $next($request);
        }

        $candidateOrigins = [];
        if ($origin) $candidateOrigins[] = rtrim(strtolower($origin), '/');
        if ($referer) {
            // Extract origin from referer URL
            try {
                $url = parse_url($referer);
                if (!empty($url['scheme']) && !empty($url['host'])) {
                    $port = isset($url['port']) ? ':' . $url['port'] : '';
                    $refOrigin = strtolower($url['scheme'] . '://' . $url['host'] . $port);
                    $candidateOrigins[] = $refOrigin;
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        foreach (array_unique($candidateOrigins) as $cand) {
            if (in_array($cand, $allowed, true)) {
                return $next($request);
            }
        }

        return response()->json(['error' => 'Origin not allowed'], 403);
    }
}

