<?php

namespace App\Http\Controllers;

use App\Models\FacebookAd;
use App\Models\FacebookAdMetric;
use App\Models\FacebookAdStat;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use App\Services\OpenAIService;
class FacebookMetricsController extends Controller
{
    public function index()
    {
        return view('facebook.index');
    }

    protected function resolveDateInterval(Request $request): array
    {
        $interval = $request->get('interval', 'daily');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
    
        if ($interval === 'custom' && $startDate && $endDate) {
            return [$interval, $startDate, $endDate];
        }
    
        if (str_starts_with($interval, 'custom_')) {
            $days = (int) str_replace('custom_', '', $interval);
            $startDate = now()->subDays($days)->toDateString();
            $endDate = now()->toDateString();
            return [$interval, $startDate, $endDate];
        }
    
        if (str_starts_with($interval, 'month_')) {
            $month = (int) str_replace('month_', '', $interval);
            $startDate = Carbon::create(null, $month)->startOfMonth()->toDateString();
            $endDate = Carbon::create(null, $month)->endOfMonth()->toDateString();
            return [$interval, $startDate, $endDate];
        }
    
        return match ($interval) {
            'daily' => [$interval, now()->subDay()->toDateString(), now()->subDay()->toDateString()],
            'weekly' => [$interval, now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'monthly' => [$interval, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            default => [$interval, now()->toDateString(), now()->toDateString()],
        };
    }
    

    // public function getData(Request $request)
    // {
    //     [$interval, $dateKey, $startDate, $endDate] = $this->resolveDateKeyAndInterval($request);

    //     $ads = FacebookAd::with(['metrics' => function ($q) use ($interval, $dateKey, $startDate, $endDate) {
    //         $q->where(function ($query) use ($interval, $dateKey, $startDate, $endDate) {
    //             $query->where(function ($sub) use ($interval, $dateKey) {
    //                 $sub->where('interval', $interval)
    //                     ->where('date_key', $dateKey);
    //             });

    //             if ($interval === 'custom' && $startDate && $endDate) {
    //                 $query->orWhere(function ($fallback) use ($startDate, $endDate) {
    //                     $fallback->where('interval', 'daily')
    //                         ->whereBetween('date_key', [$startDate, $endDate]);
    //                 });
    //             }
    //         });
    //     }, 'shopifyOrders'])
    //     ->withCount('shopifyOrders')
    //     ->get()
    //     ->sortByDesc(function ($ad) {
    //         return $ad->shopify_orders_count;
    //     })
    //     ->values();

    //     return DataTables::of($ads)
    //         ->editColumn('ad_id', fn($ad) => $ad->ad_id)
    //         ->editColumn('ad_account_name', fn($ad) => $ad->ad_account_name)
    //         ->editColumn('campaign_name', fn($ad) => $ad->campaign_name)
    //         ->editColumn('adset_name', fn($ad) => $ad->adset_name)
    //         ->editColumn('ad_name', fn($ad) => $ad->ad_name)
    //         ->editColumn('ad_type', fn($ad) => $ad->ad_type)
    //         ->editColumn('ad_link', fn($ad) => $ad->ad_link)
    //         ->editColumn('link_url', fn($ad) => $ad->link_url)
    //         ->editColumn('thumbnail_url', fn($ad) => $ad->thumbnail_url)
    //         ->editColumn('status', fn($ad) => ucfirst($ad->status))
    //         ->editColumn('updated_time', fn($ad) => $ad->updated_time)
    //         ->addColumn('interval', fn() => $interval)
    //         ->addColumn('order_count', function ($ad) {
    //             $url = route('facebook.ad.orders', ['ad_id' => $ad->ad_id]);
    //             return "<a href='{$url}' target='_blank'>{$ad->shopifyOrders->count()} Orders</a>";
    //         })
    //         ->addColumn('spend', fn($ad) => $this->sumMetric($ad, 'spend'))
    //         ->addColumn('clicks', fn($ad) => $this->sumMetric($ad, 'clicks'))
    //         ->addColumn('impressions', fn($ad) => $this->sumMetric($ad, 'impressions'))
    //         ->addColumn('ctr', fn($ad) => $this->avgMetric($ad, 'ctr'))
    //         ->addColumn('cpa', fn($ad) => $this->avgMetric($ad, 'cpa'))
    //         ->addColumn('roas', function ($ad) {
    //             $spend = $this->sumMetric($ad, 'spend');
    //             $roasValues = $ad->metrics->map(function ($metric) {
    //                 $values = json_decode($metric->purchase_roas, true);
    //                 return [
    //                     'spend' => (float) $metric->spend,
    //                     'value' => $values[0]['value'] ?? null
    //                 ];
    //             })->filter(fn($r) => $r['value'] !== null);

    //             $weighted = $roasValues->sum(fn($r) => $r['value'] * $r['spend']);
    //             return $spend > 0 ? round($weighted / $spend, 2) : null;
    //         })
    //         ->rawColumns(['order_count', 'ad_link', 'thumbnail_url'])
    //         ->make(true);
    // }

    public function getData(Request $request)
{
    [$interval, $startDate, $endDate] = $this->resolveDateInterval($request);


    $query = FacebookAdStat::query();

// Apply interval and date range filtering
            $query->where('interval', $interval);

            if ($startDate && $endDate) {
                $query->whereDate('start_date', '>=', $startDate)
                    ->whereDate('end_date', '<=', $endDate);
            }

            if ($interval === 'custom' && $startDate && $endDate && $query->count() === 0) {
                $query = FacebookAdStat::where('interval', 'daily')
                    ->whereDate('start_date', '>=', $startDate)
                    ->whereDate('end_date', '<=', $endDate);
            }
    if ($request->has('campaign')) {
        $query->where('campaign_name', $request->get('campaign'));
    }

    return DataTables::of($query)
        ->editColumn('ad_id', fn($row) => $row->ad_id)
        ->editColumn('ad_account_name', fn($row) => $row->ad_account_name)
        ->editColumn('campaign_name', fn($row) => $row->campaign_name)
        ->editColumn('adset_name', fn($row) => $row->adset_name)
        ->editColumn('ad_name', fn($row) => $row->ad_name)
        ->editColumn('ad_type', fn($row) => $row->ad_type)
        ->editColumn('ad_link', fn($row) => "<a href='{$row->ad_link}' target='_blank'>Ad Link</a>")
        ->editColumn('link_url', fn($row) => $row->link_url)
        ->editColumn('thumbnail_url', fn($row) => $row->thumbnail_url)
        ->editColumn('status', fn($row) => ucfirst($row->status))
        ->editColumn('updated_time', fn($row) => $row->updated_time?->format('Y-m-d H:i'))
        ->editColumn('order_count', fn($row) => 
            (function() use ($row) {
                return "<a href='".route('facebook.ad.orders', ['ad_id' => $row->ad_id])."' target='_blank'>{$row->order_count}</a>";
            })()
        )
        ->addColumn('interval', fn() => $interval)
        ->rawColumns(['ad_link', 'link_url', 'thumbnail_url', 'order_count'])
        ->make(true);
}


    protected function sumMetric($ad, $field)
    {
        return $ad->metrics->sum(fn($m) => (float) $m->$field);
    }

    protected function avgMetric($ad, $field)
    {
        $valid = $ad->metrics->pluck($field)->filter();
        return $valid->count() > 0 ? round($valid->avg(), 2) : null;
    }

    public function showOrders($ad_id)
    {
        $ad = FacebookAd::with('shopifyOrders')->where('ad_id', $ad_id)->firstOrFail();

        return view('facebook.orders', [
            'ad' => $ad,
            'orders' => $ad->shopifyOrders
        ]);
    }

    public function multiIntervalView()
{
    $campaigns = FacebookAdStat::select('campaign_name')->distinct()->pluck('campaign_name');

    return view('facebook.multi-interval', [
        'campaigns' => $campaigns
    ]);
}

public function getMultiIntervalData(Request $request)
{
    $hasDateRange = $request->filled('start_date') && $request->filled('end_date');
    $interval = $request->get('intervals', 'weekly');


    $query = FacebookAdStat::query()->where('status', 'active');

    if ($hasDateRange) {
        $query->where('interval', 'daily')
        ->whereDate('start_date', '>=', $request->get('start_date'))
        ->whereDate('start_date', '<=', $request->get('end_date'));
    } else {
        $query->where('interval', $interval);
    }
    

    if ($request->filled('campaign')) {
        $query->where('campaign_name', $request->get('campaign'));
    }

    if ($request->filled('ad_type')) {
        $query->where('ad_type', $request->get('ad_type'));
    }
    $stats = $query->get();
    // Fetch all and group by ad_id + interval (avoid duplicates)
    if ($hasDateRange) {
        $grouped = $stats->groupBy('ad_id')->map(function ($group) {
            $totalSpend = $group->sum('spend');
            $totalOrders = $group->sum('order_count');
            $avgRoas = $group->avg('roas');
            $first = $group->first();

            $ad = new \stdClass();
            $ad->ad_id = $first->ad_id;
            $ad->ad_name = $first->ad_name;
            $ad->campaign_name = $first->campaign_name;
            $ad->interval = 'custom';
            $ad->start_date = $group->min('start_date');
            $ad->end_date = $group->max('start_date');
            $ad->spend = number_format($totalSpend, 2, '.', '');
            $ad->order_count = $totalOrders;
            $ad->roas = round($avgRoas, 2);
            $ad->cpa = $first->cpa;
            $ad->ad_link = $first->ad_link;
            $ad->thumbnail_url = $first->thumbnail_url;
            $ad->updated_time = $first->updated_time;
            $ad->adset_name = $first->adset_name;

            return $ad;
        });
    } else {
        // Default behavior: avoid interval duplication
        $grouped = $stats->groupBy(fn($item) => $item->ad_id . '_' . $item->interval)
                         ->map(fn($group) => $group->first());
    }

    // Optional: attach computed fields
    $processed = $grouped->map(function ($ad) use ($grouped, $hasDateRange) {
        if ($hasDateRange) {
            // Compare against monthly if available
            $monthly = $grouped->firstWhere(fn($item) => $item->ad_id === $ad->ad_id && $item->interval === 'monthly');
    
            $ad->roas_trend = '-';
            if ($monthly && $monthly->roas > 0) {
                $diff = round(($ad->roas - $monthly->roas) / $monthly->roas * 100, 1);
                $ad->roas_trend = $diff >= 0 ? "↑ {$diff}%" : "↓ " . abs($diff) . "%";
            }
        } else {
            $weekly = $grouped->firstWhere(fn($item) => $item->ad_id === $ad->ad_id && $item->interval === 'weekly');
            $monthly = $grouped->firstWhere(fn($item) => $item->ad_id === $ad->ad_id && $item->interval === 'monthly');
    
            $ad->roas_trend = '-';
            if ($weekly && $monthly && $weekly->roas && $monthly->roas > 0) {
                $diff = round(($weekly->roas - $monthly->roas) / $monthly->roas * 100, 1);
                $ad->roas_trend = $diff >= 0 ? "↑ {$diff}%" : "↓ " . abs($diff) . "%";
            }
        }
    
        // Performance Flag (unchanged)
        $roas = (float) $ad->roas;
        $spend = (float) $ad->spend;
        $ad->performance_flag = match (true) {
            $roas >= 3 => 'excellent',
            $roas >= 1.5 => 'moderate',
            $spend >= 1000 && $roas < 2 => 'risky',
            default => 'poor'
        };
    
        return $ad;
    });
    

    // Filter performance
    if ($request->filled('performance_flag')) {
        $processed = $processed->filter(fn($ad) => $ad->performance_flag === $request->get('performance_flag'));
    }

    return DataTables::of($processed->values())
        ->editColumn('roas_trend', fn($row) =>
            str_starts_with($row->roas_trend, '↑') ? "<span style='color:green;'>{$row->roas_trend}</span>" :
            (str_starts_with($row->roas_trend, '↓') ? "<span style='color:red;'>{$row->roas_trend}</span>" : $row->roas_trend)
        )
        ->editColumn('order_count', fn($row) => "<a href='" . route('facebook.ad.orders', ['ad_id' => $row->ad_id]) . "' target='_blank'>{$row->order_count} Orders</a>")
        ->editColumn('ad_link', fn($row) => "<a href='{$row->ad_link}' target='_blank'>Ad Link</a>")
        ->editColumn('thumbnail_url', fn($row) => "<img src='{$row->thumbnail_url}' width='60'/>")
        ->editColumn('updated_time', fn($row) => optional($row->updated_time)->format('Y-m-d H:i'))
        ->addColumn('performance_flag', fn($row) => ucfirst($row->performance_flag))
        ->addColumn('trend_metrics', fn($row) =>
            "<a href='" . route('facebook.ad.trend_metrics', ['ad_id' => $row->ad_id]) . "' class='btn btn-sm btn-outline-info'>Trend</a>"
        )
        ->addColumn('charts', fn($row) =>
            "<a href='" . route('facebook.ad.charts', ['ad_id' => $row->ad_id]) . "' class='btn btn-sm btn-outline-dark'>📈</a>"
        )
        ->addColumn('date_range', fn($row) =>
            Carbon::parse($row->start_date)->format('M j') . ' – ' . Carbon::parse($row->end_date)->format('M j, Y')
        )
        ->with([
            'total_spend' => number_format($processed->sum('spend'), 2),
            'avg_roas' => round($processed->avg('roas'), 2),
            'total_orders' => $processed->sum('order_count'),
        ])
        ->rawColumns(['roas_trend', 'order_count', 'ad_link', 'thumbnail_url', 'trend_metrics', 'charts'])
        ->make(true);
}


public function showTrendMetrics(Request $request)
{
    $primary = $request->get('primary', 'weekly');
    $comparison = $request->get('comparison', 'monthly');
    $campaign = $request->get('campaign');
    $adId = $request->get('ad_id');
    $adName = $request->get('ad_name');
    $startDate = $request->get('start_date');
    $endDate = $request->get('end_date');

    $query = FacebookAdStat::where('status', 'active')
        ->whereIn('interval', [$primary, $comparison]);

    if (!empty($campaign)) {
        $query->where('campaign_name', $campaign);
    }

    if (!empty($adId)) {
        $query->where('ad_id', $adId);
    }

    if (!empty($adName)) {
        $query->where('ad_name', 'like', '%' . $adName . '%');
    }

    if ($startDate && $endDate) {
        $query->whereBetween('date_key', [$startDate, $endDate]);
    }

    $ads = $query->get()
        ->groupBy('ad_id')
        ->map(function ($group) use ($primary, $comparison) {
            $primaryRow = $group->firstWhere('interval', $primary);
            $comparisonRow = $group->firstWhere('interval', $comparison);

            $compute = function ($new, $old, $positiveIsGood = true) {
                if (!is_numeric($new) || !is_numeric($old) || $old == 0) return '-';
                $diff = round(($new - $old) / $old * 100, 1);
                $symbol = $diff >= 0 ? '↑' : '↓';
                $color = ($diff >= 0 && $positiveIsGood) || ($diff < 0 && !$positiveIsGood) ? 'green' : 'red';
                return "<span style='color:$color;'>$symbol " . abs($diff) . "%</span>";
            };
            $formatDate = function ($key) {
                if (!$key) return '-';
                if (str_contains($key, '_')) {
                    [$start, $end] = explode('_', $key);
                    return \Carbon\Carbon::parse($start)->format('M j, Y') . ' → ' . \Carbon\Carbon::parse($end)->format('M j, Y');
                }
                return \Carbon\Carbon::parse($key)->format('M j, Y');
            };
            return [
                'ad_id' => $primaryRow?->ad_id ?? $comparisonRow?->ad_id,
                'ad_name' => $primaryRow?->ad_name ?? $comparisonRow?->ad_name,
                'campaign_name' => $primaryRow?->campaign_name ?? $comparisonRow?->campaign_name,
                'thumbnail_url' => $primaryRow?->thumbnail_url ?? $comparisonRow?->thumbnail_url,
                'ad_link' => $primaryRow?->ad_link ?? $comparisonRow?->ad_link,
                'primary_dates' => $formatDate($primaryRow?->date_key),
                'comparison_dates' => $formatDate($comparisonRow?->date_key),

                'roas_trend' => $this->computeTrend($primaryRow?->roas, $comparisonRow?->roas),
                'cpa_trend' => $this->computeTrend($primaryRow?->cpa, $comparisonRow?->cpa, false),
                'spend_trend' => $this->computeTrend($primaryRow?->spend, $comparisonRow?->spend),
                'ctr_trend' => $this->computeTrend($primaryRow?->ctr, $comparisonRow?->ctr),
                'impressions_trend' => $this->computeTrend($primaryRow?->impressions, $comparisonRow?->impressions),
                'clicks_trend' => $this->computeTrend($primaryRow?->clicks, $comparisonRow?->clicks),
            ];
        });

    $campaigns = FacebookAdStat::select('campaign_name')->distinct()->pluck('campaign_name');

    return view('facebook.trend_metrics', compact(
        'ads', 'primary', 'comparison', 'campaigns', 'campaign', 'adId', 'adName', 'startDate', 'endDate'
    ));
}


public function showAdTrendMetrics(Request $request, $ad_id)
{
    $primary = $request->get('primary', 'monthly');
    $comparison = $request->get('comparison', 'weekly');
    $startDate = $request->get('start_date');
    $endDate = $request->get('end_date');
    $compareDate1 = $request->get('compare_date_1');
    $compareDate2 = $request->get('compare_date_2');

    // Trend computation helper
    $compute = fn($new, $old, $positive = true) =>
        (!is_numeric($new) || !is_numeric($old) || $old == 0)
            ? '-' : sprintf(
                "<span style='color:%s;'>%s %.1f%%</span>",
                (($new - $old) >= 0) === $positive ? 'green' : 'red',
                ($new - $old) >= 0 ? '↑' : '↓',
                abs(($new - $old) / $old * 100)
            );

    // Interval-based comparison fetch
    $records = FacebookAdStat::where('ad_id', $ad_id)
        ->where('status', 'active')
        ->whereIn('interval', [$primary, $comparison])
        ->when($startDate && $endDate, fn($q) =>
            $q->whereDate('start_date', '>=', $startDate)
              ->whereDate('end_date', '<=', $endDate)
        )->get();

    $ad = collect($records)->groupBy('ad_id')->map(function ($group) use ($primary, $comparison, $compute) {
        $primaryRow = $group->firstWhere('interval', $primary);
        $comparisonRow = $group->firstWhere('interval', $comparison);

        $formatRange = fn($row) => $row
            ? Carbon::parse($row->start_date)->format('M j, Y') . ' → ' . Carbon::parse($row->end_date)->format('M j, Y')
            : '-';

        return [
            'ad_id' => $primaryRow?->ad_id ?? $comparisonRow?->ad_id,
            'ad_name' => $primaryRow?->ad_name ?? $comparisonRow?->ad_name,
            'campaign_name' => $primaryRow?->campaign_name ?? $comparisonRow?->campaign_name,
            'thumbnail_url' => $primaryRow?->thumbnail_url ?? $comparisonRow?->thumbnail_url,
            'ad_link' => $primaryRow?->ad_link ?? $comparisonRow?->ad_link,
            'primary_dates' => $formatRange($primaryRow),
            'comparison_dates' => $formatRange($comparisonRow),

            'roas_trend' => $compute($primaryRow?->roas, $comparisonRow?->roas),
            'cpa_trend' => $compute($primaryRow?->cpa, $comparisonRow?->cpa, false),
            'spend_trend' => $compute($primaryRow?->spend, $comparisonRow?->spend),
            'ctr_trend' => $compute($primaryRow?->ctr, $comparisonRow?->ctr),
            'impressions_trend' => $compute($primaryRow?->impressions, $comparisonRow?->impressions),
            'clicks_trend' => $compute($primaryRow?->clicks, $comparisonRow?->clicks),
        ];
    })->first();

    if (!$ad) {
        return back()->with('error', 'No active data found for this ad in the selected interval or date range.');
    }

    // Direct date-to-date comparison (optional: refactor later)
    $ad['custom_trends'] = [
        'ROAS' => '-', 'CPA' => '-', 'Spend' => '-', 'CTR' => '-', 'Impressions' => '-', 'Clicks' => '-',
    ];

    if ($compareDate1 && $compareDate2) {
        $dailyStats = FacebookAdStat::where('ad_id', $ad_id)
            ->where('interval', 'daily')
            ->whereDate('start_date', $compareDate1)
            ->orWhereDate('start_date', $compareDate2)
            ->get()
            ->keyBy(fn($row) => $row->start_date->toDateString());

        $row1 = $dailyStats[$compareDate1] ?? null;
        $row2 = $dailyStats[$compareDate2] ?? null;

        $format = fn($d) => Carbon::parse($d)->format('M j, Y');
        $ad['compare_date_1'] = $row1 ? $format($row1->start_date) : '-';
        $ad['compare_date_2'] = $row2 ? $format($row2->start_date) : '-';

        if ($row1 && $row2) {
            $ad['custom_trends'] = [
                'ROAS' => $compute($row2->roas, $row1->roas),
                'CPA' => $compute($row2->cpa, $row1->cpa, false),
                'Spend' => $compute($row2->spend, $row1->spend),
                'CTR' => $compute($row2->ctr, $row1->ctr),
                'Impressions' => $compute($row2->impressions, $row1->impressions),
                'Clicks' => $compute($row2->clicks, $row1->clicks),
            ];
        }
    }

    // Chart Data
    $chartData = FacebookAdStat::where('ad_id', $ad_id)
        ->where('status', 'active')
        ->when($primary !== 'daily', fn($q) => $q->where('interval', $primary))
        ->when($compareDate1 && $compareDate2, fn($q) =>
            $q->whereDate('start_date', '>=', $compareDate1)
              ->whereDate('end_date', '<=', $compareDate2),
            fn($q) =>
                $startDate && $endDate
                    ? $q->whereDate('start_date', '>=', $startDate)->whereDate('end_date', '<=', $endDate)
                    : $q
        )
        ->orderBy('start_date')
        ->get();

    $chartLabels = $chartData->map(fn($d) => Carbon::parse($d->start_date)->format('M j'));
    $chartRoas = $chartData->pluck('roas')->map(fn($v) => round($v, 2));
    $chartCpa  = $chartData->pluck('cpa')->map(fn($v) => round($v, 2));
    $chartCtr  = $chartData->pluck('ctr')->map(fn($v) => round($v, 2));

    // AI Insights
    $openAiService = app(OpenAIService::class);
    $ad['ai_insights'] = $openAiService->generateAdInsights($ad, $ad['ad_id'] ?? 'N/A', $primary, $comparison);

    return view('facebook.ad_trend_metrics', compact(
        'ad',
        'primary',
        'comparison',
        'startDate',
        'endDate',
        'compareDate1',
        'compareDate2',
        'chartLabels',
        'chartRoas',
        'chartCpa',
        'chartCtr'
    ));
}



public function showAdCharts(Request $request, $ad_id)
{
    $query = FacebookAdStat::where('ad_id', $ad_id)
        ->where('status', 'active');

    // Handle predefined range
    if ($request->has('range')) {
        $now = now();
        $days = match ($request->get('range')) {
            'last_7' => 7,
            'last_30' => 30,
            'last_90' => 90,
            default => null,
        };

        if ($days) {
            $query->whereDate('start_date', '>=', $now->copy()->subDays($days));
        }
    }

    // Handle manual range
    if ($request->filled('start_date') && $request->filled('end_date')) {
        $query->whereDate('start_date', '>=', $request->start_date)
              ->whereDate('end_date', '<=', $request->end_date);
    }

    $stats = $query->orderBy('start_date')->get()->map(function ($item) {
        return [
            'date' => Carbon::parse($item->start_date)->format('M j, Y'),
            'roas' => round($item->roas ?? 0, 2),
            'spend' => round($item->spend ?? 0, 2),
            'cpa' => round($item->cpa ?? 0, 2),
            'ctr' => round($item->ctr ?? 0, 2),
            'clicks' => (int) $item->clicks,
            'impressions' => (int) $item->impressions,
            'orders' => (int) $item->order_count,
        ];
    });

    return view('facebook.ad_charts', [
        'ad_id' => $ad_id,
        'stats' => $stats,
        'range' => $request->get('range'),
        'start_date' => $request->start_date,
        'end_date' => $request->end_date,
    ]);
}

function computeTrend($new, $old, $positiveIsGood = true)
{
    if (!is_numeric($new) || !is_numeric($old) || $old == 0) return '-';
    $diff = round(($new - $old) / $old * 100, 1);
    $symbol = $diff >= 0 ? '↑' : '↓';
    $color = ($diff >= 0 && $positiveIsGood) || ($diff < 0 && !$positiveIsGood) ? 'green' : 'red';
    return "<span style='color:$color;'>$symbol " . abs($diff) . "%</span>";
}




}
