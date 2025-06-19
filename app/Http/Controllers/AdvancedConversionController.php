<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Models\FactEvent;
use App\Models\RefDomainDim;
use App\Models\ProductDim;

class AdvancedConversionController extends Controller
{
    /* ───────────────────────────────────────────────────────────
     * 1. Source-level CVR
     * ─────────────────────────────────────────────────────────── */
    public function sourcePerformance(Request $request)
    {
        [$from,$to] = $this->parseDates($request);

        $rows = FactEvent::selectRaw("
                    ref_domain_id,
                    SUM(CASE WHEN event_type='view' THEN 1 END) AS views,
                    SUM(CASE WHEN event_type='buy'  THEN 1 END) AS purchases
                ")
                ->whereBetween('event_ts', [$from,$to])
                ->distinct('device_id','event_type')   // dedupe per device
                ->groupBy('ref_domain_id')
                ->get();

        $stats = $rows->map(function ($r) {
            $domain = RefDomainDim::find($r->ref_domain_id)->domain ?? 'unknown';
            return [
                'domain'          => $domain,
                'visits'          => (int) $r->views,
                'conversions'     => (int) $r->purchases,
                'conversion_rate' => $r->views ? round($r->purchases / $r->views * 100, 2) : 0.0,
            ];
        })->sortByDesc('conversions')
          ->values();

        return view('analytics.source_performance', ['stats'=>$stats]);
    }

    /* ───────────────────────────────────────────────────────────
     * 2. Product × Source
     * ─────────────────────────────────────────────────────────── */
    public function productBySource(Request $request)
    {
        [$from, $to] = $this->parseDates($request);

    // Step 1: Get all sources from view events
    $sources = FactEvent::whereBetween('event_ts', [$from, $to])
        ->where(function ($q) {
            $q->where('event_type', 'view')
              ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_props, '$.productTitle')) IS NOT NULL");
        })
        ->pluck('utm_source')
        ->filter()
        ->unique();

    $funnels = [];

    foreach ($sources as $source) {
        $views = FactEvent::whereBetween('event_ts', [$from, $to])
            ->where('utm_source', $source)
            ->where(function ($q) {
                $q->where('event_type', 'view')
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_props, '$.productTitle')) IS NOT NULL");
            })
            ->pluck('device_id')
            ->unique();

        $atc = FactEvent::whereBetween('event_ts', [$from, $to])
            ->where('utm_source', $source)
            ->where('event_type', 'atc')
            ->whereIn('device_id', $views)
            ->pluck('device_id')
            ->unique();

        $cko = FactEvent::whereBetween('event_ts', [$from, $to])
            ->where('utm_source', $source)
            ->where('event_type', 'cko')
            ->whereIn('device_id', $atc)
            ->pluck('device_id')
            ->unique();

        $buy = FactEvent::whereBetween('event_ts', [$from, $to])
            ->where('utm_source', $source)
            ->where('event_type', 'buy')
            ->whereIn('device_id', $cko)
            ->pluck('device_id')
            ->unique();

        $funnels[] = [
            'source'           => $source ?? 'unknown',
            'views'            => $views->count(),
            'atc'              => $atc->count(),
            'cko'              => $cko->count(),
            'buy'              => $buy->count(),
            'drop_atc'         => $views->count() - $atc->count(),
            'drop_cko'         => $atc->count() - $cko->count(),
            'drop_buy'         => $cko->count() - $buy->count(),
            'pct_atc'          => $views->count() ? round($atc->count() / $views->count() * 100, 2) : 0,
            'pct_cko'          => $atc->count() ? round($cko->count() / $atc->count() * 100, 2) : 0,
            'pct_buy'          => $cko->count() ? round($buy->count() / $cko->count() * 100, 2) : 0,
        ];
    }

    return view('analytics.product_by_source', ['funnels' => $funnels]);
    }
    

    /* ───────────────────────────────────────────────────────────
     * 3. Conversion-lag buckets
     * ─────────────────────────────────────────────────────────── */
    public function conversionLag(Request $request)
    {
        [$from,$to] = $this->parseDates($request);

        // first view time and first purchase time per device
        $firstView = FactEvent::where('event_type','view')
            ->whereBetween('event_ts',[$from,$to])
            ->selectRaw('device_id, MIN(event_ts) as t0')
            ->groupBy('device_id');

        $firstBuy  = FactEvent::where('event_type','buy')
            ->whereBetween('event_ts',[$from,$to])
            ->selectRaw('device_id, MIN(event_ts) as t1')
            ->groupBy('device_id');

        $joined = \DB::table(\DB::raw("({$firstView->toSql()}) v"))
            ->mergeBindings($firstView->getQuery())
            ->joinSub($firstBuy, 'b', 'v.device_id','=','b.device_id')
            ->join('fact_events as fe','fe.device_id','=','v.device_id')   // get domain via any event row
            ->selectRaw('fe.ref_domain_id,
                         TIMESTAMPDIFF(MINUTE, v.t0, b.t1) as lag_min')
            ->get();

        $buckets = [];
        foreach ($joined as $row) {
            $domain = RefDomainDim::find($row->ref_domain_id)->domain ?? 'unknown';
            $bucket = $row->lag_min <=5  ? 'bucket_5'   :
                      ($row->lag_min<=30 ? 'bucket_30'  :
                      ($row->lag_min<=120? 'bucket_120' : 'bucket_big'));
            $buckets[$domain][$bucket] = ($buckets[$domain][$bucket] ?? 0) + 1;
        }

        $stats = collect($buckets)->map(function ($arr,$dom) {
            return array_merge(
                ['domain'=>$dom,'bucket_5'=>0,'bucket_30'=>0,'bucket_120'=>0,'bucket_big'=>0],
                $arr
            );
        })->values();

        return view('analytics.conversion_lag', ['stats'=>$stats]);
    }

    /* ───────────────────────────────────────────────────────────
     * 4. Funnel fallout (views → atc → cko → buy)
     * ─────────────────────────────────────────────────────────── */
    public function funnelFallout(Request $request)
    {
        [$from, $to] = $this->parseDates($request);
    
        // Stage 1: Views (either event_type = 'view' or raw_props.productTitle exists)
        $views = FactEvent::whereBetween('event_ts', [$from, $to])
            ->where(function ($q) {
                $q->where('event_type', 'view')
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_props, '$.productTitle')) IS NOT NULL");
            })
            ->pluck('device_id')
            ->unique();
    
        // Stage 2: Add to Cart - only from devices that had a view
        $addToCart = FactEvent::whereBetween('event_ts', [$from, $to])
            ->where('event_type', 'atc')
            ->whereIn('device_id', $views)
            ->pluck('device_id')
            ->unique();
    
        // Stage 3: Checkout Started - only from devices that added to cart
        $checkout = FactEvent::whereBetween('event_ts', [$from, $to])
            ->where('event_type', 'cko')
            ->whereIn('device_id', $addToCart)
            ->pluck('device_id')
            ->unique();
    
        // Stage 4: Purchase - only from devices that started checkout
        $purchases = FactEvent::whereBetween('event_ts', [$from, $to])
            ->where('event_type', 'buy')
            ->whereIn('device_id', $checkout)
            ->pluck('device_id')
            ->unique();
    
        // Funnel counts
        $funnel = [
            'views'            => $views->count(),
            'add_to_cart'      => $addToCart->count(),
            'checkout_started' => $checkout->count(),
            'purchases'        => $purchases->count(),
            'pct_atc'          => $views->count() ? round($addToCart->count() / $views->count() * 100, 2) : 0,
            'pct_cko'          => $addToCart->count() ? round($checkout->count() / $addToCart->count() * 100, 2) : 0,
            'pct_buy'          => $checkout->count() ? round($purchases->count() / $checkout->count() * 100, 2) : 0,
        ];
    
        $chart = [
            'labels' => ['Viewed', 'Add To Cart', 'Checkout', 'Purchase'],
            'values' => [
                $funnel['views'],
                $funnel['add_to_cart'],
                $funnel['checkout_started'],
                $funnel['purchases']
            ],
        ];
    
        return view('analytics.funnel_fallout', compact('funnel', 'chart'));
    }
    
    
    
    /* ───────────────────────────── helpers ─────────────────── */
    private function parseDates(Request $r): array
    {
        $s = Carbon::parse($r->input('start_date', now()->subDays(7)))->startOfDay();
        $e = Carbon::parse($r->input('end_date',   now()))            ->endOfDay();
        if ($s->gt($e)) abort(422,'Start date after end date');
        return [$s, $e];
    }
}
