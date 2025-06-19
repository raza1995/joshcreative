<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ConversionController extends Controller
{
    public function landingSiteConversions(Request $request)
    {
        /* ───── 1. Date-range handling ───── */
        $start = Carbon::parse(
            $request->input('start_date', now()->subDays(7)->toDateString())
        )->startOfDay();

        $end   = Carbon::parse(
            $request->input('end_date',   now()->toDateString())
        )->endOfDay();

        if ($start->gt($end)) {
            return back()->withErrors(['date' => 'Start date must be before end date.']);
        }

        $from = $start->toDateString();     // Mixpanel expects YYYY-MM-DD
        $to   = $end  ->toDateString();

        /* ───── 2. Mixpanel calls ───── */

        // A. Unique *Product views* per initial referrer  → VISITS
        $viewsRes = Http::withBasicAuth(env('SECRET_MIXPANEL'), '')
            ->get('https://mixpanel.com/api/query/segmentation', [
                'event'     => 'Product viewed',
                'from_date' => $from,
                'to_date'   => $to,
                'on'        => 'properties["$initial_referring_domain"]',
                'type'      => 'unique',                          // unique devices
                'where'     => 'properties["$device_id"] != ""',
            ]);

        // B. *Checkout completed* per same referrer        → CONVERSIONS
        $convRes = Http::withBasicAuth(env('SECRET_MIXPANEL'), '')
            ->get('https://mixpanel.com/api/query/segmentation', [
                'event'     => 'checkout_completed',
                'from_date' => $from,
                'to_date'   => $to,
                'on'        => 'properties["$initial_referring_domain"]',
                'where'     => 'properties["$device_id"] != ""',
            ]);

        if (!$viewsRes->ok() || !$convRes->ok()) {
            return response()->json([
                'error'   => 'Failed to fetch Mixpanel data',
                'details' => [
                    'views' => $viewsRes->json(),
                    'conv'  => $convRes->json(),
                ],
            ], 500);
        }

        /* ───── 3. Flatten, merge & compute rates ───── */
        $views = $this->flattenSegmentation($viewsRes->json());   // [domain => visits]
        $conv  = $this->flattenSegmentation($convRes->json());    // [domain => conversions]

        $stats = collect($views)
            ->merge($conv)                     // ensure union of keys
            ->map(function ($_, $domain) use ($views, $conv) {
                $v = $views[$domain] ?? 0;
                $c = $conv [$domain] ?? 0;
                return [
                    'domain'          => $domain ?: 'unknown',
                    'visits'          => $v,
                    'conversions'     => $c,
                    'conversion_rate' => $v ? round(($c / $v) * 100, 2) : 0.00,
                ];
            })
            ->sortByDesc('conversions')
            ->values();

        /* ───── 4. Send to Blade view ───── */
        return view('analytics.landing_sites', [
            'conversions' => $stats,
        ]);
    }

    /**
     * Collapse Mixpanel Segmentation response into [ key => grandTotal ]
     */
    private function flattenSegmentation(array $resp): array
    {
        $out = [];
        foreach ($resp['data']['values'] ?? [] as $key => $series) {
            // $series is a small time-series map  { "2025-06-18": 12, ... }
            $out[$key ?: 'unknown'] = array_sum($series);
        }
        return $out;
    }
}
