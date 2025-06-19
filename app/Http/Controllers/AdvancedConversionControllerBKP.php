<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Models\ShopifyOrder;

class AdvancedConversionControllerBKP extends Controller
{
    /* =========================================================
     * 1.  Referring-domain performance  (source → CVR)
     * ========================================================= */
    public function sourcePerformance(Request $request)
    {
        [$from, $to] = $this->parseDates($request);

        /* Mixpanel:  Product viewed  (unique devices) */
        $views = $this->mixpanelSegmentation(
            event: 'Product viewed',
            from:  $from,
            to:    $to,
            on:    'properties["$initial_referring_domain"]',
            type:  'unique'
        );

        /* Mixpanel:  checkout_completed (all purchases) */
        $conv  = $this->mixpanelSegmentation(
            event: 'checkout_completed',
            from:  $from,
            to:    $to,
            on:    'properties["$initial_referring_domain"]'
        );

        $stats = $this->mergeMetrics($views, $conv, 'domain');

        return view('analytics.source_performance', ['stats' => $stats]);
    }

    /* =========================================================
     * 2.  Top products *per* traffic source
     * ========================================================= */
    public function productBySource(Request $request)
{
    [$from, $to] = $this->parseDates($request);

    /* 1️⃣  Product views per  (initial_ref_domain | productTitle) */
    $viewStats = $this->mixpanelSegmentation(
        event: 'Product viewed',
        from:  $from,
        to:    $to,
        // key e.g.  "m.facebook.com|4-PACK BUNDLE $84.95"
        on:    'properties["$initial_referring_domain"] + "|" + properties["productTitle"]',
        type:  'unique'
    );                                // returns [key => unique_devices]

    /* 2️⃣  Orders per (ref_domain | variant_title) -------------- */
    $orders = ShopifyOrder::query()
        ->whereBetween('order_date', [$from, $to])
        ->pluck('raw_json')
        ->map(fn ($raw) => json_decode($raw, true))
        ->filter()                              // drop invalid JSON
        ->reduce(function (array $carry, array $o) {

            // 2-A  figure out the traffic source host
            $src = $o['referring_site'] ?? $o['landing_site'] ?? 'unknown';
            $src = parse_url($src, PHP_URL_HOST) ?: $src ?: 'unknown';

            // 2-B  one *line-item* => one order count
            foreach ($o['line_items'] ?? [] as $li) {
                $prod = $li['variant_title'] ?? $li['title'] ?? 'unknown';
                $key  = $src . '|' . $prod;
                $carry[$key] = ($carry[$key] ?? 0) + 1;   // increment
            }
            return $carry;
        }, []);                                        // [key => orders]

    /* 3️⃣  Merge & shape for the table -------------------------- */
    $stats = collect($viewStats)
        ->merge($orders)                     // union of keys
        ->map(function ($__, $key) use ($viewStats, $orders) {
            [$src, $prod] = explode('|', $key, 2);
            $views  = $viewStats[$key] ?? 0;
            $orders = $orders    [$key] ?? 0;
            return [
                'source'  => $src,
                'product' => $prod,
                'views'   => $views,
                'orders'  => $orders,
                'cvr'     => $views ? round($orders / $views * 100, 2) : 0.0,
            ];
        })
        ->sortByDesc('orders')
        ->values();

    return view('analytics.product_by_source', ['stats' => $stats]);
}


    /* =========================================================
     * 3.  Conversion-lag buckets  (0-5m, 5-30m, >30m)
     * ========================================================= */
    public function conversionLag(Request $request)
    {
        [$from, $to] = $this->parseDates($request);

        $jql = file_get_contents(storage_path('app/mixpanel/lag_buckets.jql')); // you’ll create this
   
        $resp = Http::withBasicAuth(env('SECRET_MIXPANEL'), '')
            ->post('https://mixpanel.com/api/2.0/jql', [
                'from_date' => $from,
                'to_date'   => $to,
                'script'    => $jql,
            ]);
dd( $resp = $resp->json());
        abort_unless($resp->ok(), 500, 'Mixpanel JQL failed');

        return view('analytics.conversion_lag', [
            'stats' => collect($resp->json())
        ]);
    }

    /* =========================================================
     * Helpers
     * ========================================================= */

    private function parseDates(Request $r): array
    {
        $s = Carbon::parse($r->input('start_date', now()->subDays(7)))->startOfDay();
        $e = Carbon::parse($r->input('end_date',   now()))            ->endOfDay();
        if ($s->gt($e)) abort(422, 'Start date after end date');
        return [$s->toDateString(), $e->toDateString()];
    }

    private function mixpanelSegmentation(
        string $event,
        string $from,
        string $to,
        string $on,
        string $type = 'general'
    ): array {
        $res = Http::withBasicAuth(env('SECRET_MIXPANEL'), '')
            ->get('https://mixpanel.com/api/query/segmentation', [
                'event'     => $event,
                'from_date' => $from,
                'to_date'   => $to,
                'on'        => $on,
                'type'      => $type,
                'where'     => 'properties["$device_id"] != ""',
            ]);

        abort_unless($res->ok(), 500, 'Segmentation API failed: '.$event);

        return $this->flattenSegmentation($res->json());
    }

    private function mergeMetrics(array $visits, array $conv, string $label): Collection
    {
        return collect($visits)
            ->merge($conv)
            ->map(function ($_,$k) use ($visits,$conv,$label) {
                $v = $visits[$k] ?? 0;
                $c = $conv  [$k] ?? 0;
                return [
                    $label            => $k ?: 'unknown',
                    'visits'          => $v,
                    'conversions'     => $c,
                    'conversion_rate' => $v ? round($c / $v * 100, 2) : 0.0,
                ];
            })
            ->sortByDesc('conversions')
            ->values();
    }

    private function flattenSegmentation(array $resp): array
    {
        $out=[];
        foreach ($resp['data']['values'] ?? [] as $k=>$series) {
            $out[$k ?: 'unknown'] = array_sum($series);
        }
        return $out;
    }

    public function funnelFallout(Request $request)
    {
        /* 1️⃣  Dates ------------------------------------------------------- */
        [$from, $to] = $this->parseDates($request);
    
        /* 2️⃣  Run JQL ----------------------------------------------------- */
        $jql  = file_get_contents(storage_path('app/mixpanel/funnel_fallout.jql'));
    
        $resp = Http::withBasicAuth(env('SECRET_MIXPANEL'), '')
            ->post('https://mixpanel.com/api/2.0/jql', [
                'script' => $jql,
                'params' => json_encode([
                    'from_date' => $from,
                    'to_date'   => $to,
                ]),
            ]);

    
        /* 3️⃣  Extract row (or default zeros) ----------------------------- */
        $row   = collect($resp->json())->first() ?? [];
        $funnel = [
            'views'            => $row['views']            ?? 0,
            'add_to_cart'      => $row['add_to_cart']      ?? 0,
            'checkout_started' => $row['checkout_started'] ?? 0,
            'purchases'        => $row['purchases']        ?? 0,
            'pct_atc'          => $row['pct_atc']          ?? 0,
            'pct_cko'          => $row['pct_cko']          ?? 0,
            'pct_buy'          => $row['pct_buy']          ?? 0,
        ];
    
        /* 4️⃣  Prepare chart payload -------------------------------------- */
        $chart = [
            'labels' => ['Viewed', 'Add To Cart', 'Checkout', 'Purchase'],
            'values' => [
                $funnel['views'],
                $funnel['add_to_cart'],
                $funnel['checkout_started'],
                $funnel['purchases'],
            ],
        ];
    
        /* 5️⃣  Send to Blade ---------------------------------------------- */
        return view('analytics.funnel_fallout', [
            'funnel' => $funnel,
            'chart'  => $chart,
        ]);
    }
    

}
