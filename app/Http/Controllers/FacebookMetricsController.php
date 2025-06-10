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

    protected function resolveDateKeyAndInterval(Request $request): array
    {
        $interval = $request->get('interval', 'daily');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if ($interval === 'custom' && $startDate && $endDate) {
            $dateKey = "{$startDate}_{$endDate}";
        } elseif (str_starts_with($interval, 'custom_')) {
            $days = (int) str_replace('custom_', '', $interval);
            $startDate = now()->subDays($days)->toDateString();
            $endDate = now()->toDateString();
            $dateKey = "{$startDate}_{$endDate}";
        } elseif (str_starts_with($interval, 'month_')) {
            $month = (int) str_replace('month_', '', $interval);
            $year = now()->year;

            $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
            $endDate = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
            $dateKey = "{$startDate}_{$endDate}";
        } else {
            $dateKey = match ($interval) {
                'daily' => now()->subDay()->toDateString(),
                'weekly' => now()->startOfWeek()->toDateString(),
                'monthly' => now()->startOfMonth()->toDateString(),
                default => now()->toDateString(),
            };
        }

        return [$interval, $dateKey, $startDate ?? null, $endDate ?? null];
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
    [$interval, $dateKey, $startDate, $endDate] = $this->resolveDateKeyAndInterval($request);

    $query = FacebookAdStat::query();

    // Apply interval and date range filtering
    $query->where(function ($query) use ($interval, $dateKey, $startDate, $endDate) {
        $query->where(function ($sub) use ($interval, $dateKey) {
            $sub->where('interval', $interval)
                ->where('date_key', $dateKey);
        });

        // Fallback: use daily data between custom date range
        if ($interval === 'custom' && $startDate && $endDate) {
            $query->orWhere(function ($fallback) use ($startDate, $endDate) {
                $fallback->where('interval', 'daily')
                    ->whereBetween('date_key', [$startDate, $endDate]);
            });
        }
    });

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
    $intervals = (array) $request->get('intervals', ['weekly', 'monthly']);

    $query = FacebookAdStat::query()->whereIn('interval', $intervals);

    if ($request->filled('campaign')) {
        $query->where('campaign_name', $request->get('campaign'));
    }

    if ($request->filled('start_date') && $request->filled('end_date')) {
        $query->whereBetween('date_key', [
            $request->get('start_date'),
            $request->get('end_date')
        ]);
    }

    if ($request->filled('ad_type')) {
        $query->where('ad_type', $request->get('ad_type'));
    }

    if ($request->filled('status')) {
        $query->where('status', $request->get('status'));
    }

    // Group by ad_id and compute roas_trend
    $ads = $query->get()->groupBy('ad_id')->flatMap(function ($group) {
        $weekly = $group->firstWhere('interval', 'weekly');
        $monthly = $group->firstWhere('interval', 'monthly');
    
        return $group->map(function ($ad) use ($weekly, $monthly) {
            $ad->roas_trend = '-';
    
            if ($weekly && $monthly && $weekly->roas !== null && $monthly->roas > 0) {
                $diff = round(($weekly->roas - $monthly->roas) / $monthly->roas * 100, 1);
                $ad->roas_trend = $diff >= 0 ? "↑ {$diff}%" : "↓ " . abs($diff) . "%";
            }
    
            // Assign performance flag
            $roas = (float) $ad->roas;
            $spend = (float) $ad->spend;
            if ($roas >= 3) {
                $ad->performance_flag = 'excellent';
            } elseif ($roas >= 1.5) {
                $ad->performance_flag = 'moderate';
            } elseif ($spend >= 1000 && $roas < 2) {
                $ad->performance_flag = 'risky';
            } else {
                $ad->performance_flag = 'poor';
            }
    
            return $ad;
        });
    });
    if ($request->filled('performance_flag')) {
        $ads = $ads->filter(function ($ad) use ($request) {
            return $ad->performance_flag === $request->get('performance_flag');
        });
    }
    return DataTables::of($ads)
        ->editColumn('ad_id', fn($row) => $row->ad_id)
        ->editColumn('campaign_name', fn($row) => $row->campaign_name)
        ->editColumn('ad_name', fn($row) => $row->ad_name)
        ->editColumn('interval', fn($row) => ucfirst($row->interval))
        ->editColumn('spend', fn($row) => number_format($row->spend, 2))
        ->editColumn('cpa', fn($row) => number_format($row->cpa, 2))
        ->editColumn('roas', fn($row) => $row->roas ?? '-')
        ->addColumn('date_range', function ($row) {
            if (str_contains($row->date_key, '_')) {
                [$start, $end] = explode('_', $row->date_key);
                try {
                    return \Carbon\Carbon::parse($start)->format('M j, Y') . ' – ' . \Carbon\Carbon::parse($end)->format('M j, Y');
                } catch (\Exception $e) {
                    return $row->date_key;
                }
            }
        
            // fallback for single date_key values
            try {
                return \Carbon\Carbon::parse($row->date_key)->format('M j, Y');
            } catch (\Exception $e) {
                return $row->date_key;
            }
        })
        
                ->addColumn('roas_trend', function ($row) {
            if (str_starts_with($row->roas_trend, '↑')) {
                return "<span style='color:green;'>{$row->roas_trend}</span>";
            } elseif (str_starts_with($row->roas_trend, '↓')) {
                return "<span style='color:red;'>{$row->roas_trend}</span>";
            }
            return $row->roas_trend;
        })
        ->editColumn('order_count', fn($row) => "<a href='".route('facebook.ad.orders', ['ad_id' => $row->ad_id])."' target='_blank'>{$row->order_count} Orders</a>")
        ->editColumn('ad_link', fn($row) => "<a href='{$row->ad_link}' target='_blank'>Ad Link</a>")
        ->editColumn('thumbnail_url', fn($row) => "<img src='{$row->thumbnail_url}' width='60'/>")
        ->editColumn('updated_time', fn($row) => optional($row->updated_time)->format('Y-m-d H:i'))
        ->addColumn('performance_flag', fn($row) => ucfirst($row->performance_flag))
        ->addColumn('trend_metrics', function ($row) {
            return "<a href='" . route('facebook.ad.trend_metrics', ['ad_id' => $row->ad_id]) . "' class='btn btn-sm btn-outline-info'>Trend Metrics</a>";
        })
        ->addColumn('charts', function ($row) {
            return "<a href='" . route('facebook.ad.charts', ['ad_id' => $row->ad_id]) . "' class='btn btn-outline-dark btn-sm'>📈 Charts</a>";
        })
        
        ->with([
            'total_spend' => $ads->sum('spend'),
            'avg_roas' => round($ads->avg('roas'), 2),
            'total_orders' => $ads->sum('order_count'),
        ])
        ->rawColumns(['ad_link', 'thumbnail_url', 'order_count', 'roas_trend', 'trend_metrics', 'performance_flag', 'charts'])
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

    // Helper functions
    $compute = fn($new, $old, $positive = true) =>
        (!is_numeric($new) || !is_numeric($old) || $old == 0)
            ? '-' : sprintf("<span style='color:%s;'>%s %.1f%%</span>",
                (($new - $old) >= 0) === $positive ? 'green' : 'red',
                ($new - $old) >= 0 ? '↑' : '↓',
                abs(($new - $old) / $old * 100)
            );

    $formatDate = fn($key) =>
        !$key ? '-' :
        (str_contains($key, '_')
            ? Carbon::parse(explode('_', $key)[0])->format('M j, Y') . ' → ' . Carbon::parse(explode('_', $key)[1])->format('M j, Y')
            : Carbon::parse($key)->format('M j, Y'));

    // Fetch interval-based trend comparison
    $records = FacebookAdStat::where('ad_id', $ad_id)
        ->where('status', 'active')
        ->whereIn('interval', [$primary, $comparison])
        ->when($startDate && $endDate, fn($q) => $q->whereBetween('date_key', [$startDate, $endDate]))
        ->get();

    $ad = collect($records)->groupBy('ad_id')->map(function ($group) use ($primary, $comparison, $compute, $formatDate) {
        $primaryRow = $group->firstWhere('interval', $primary);
        $comparisonRow = $group->firstWhere('interval', $comparison);

        return [
            'ad_id' => $primaryRow?->ad_id ?? $comparisonRow?->ad_id,
            'ad_name' => $primaryRow?->ad_name ?? $comparisonRow?->ad_name,
            'campaign_name' => $primaryRow?->campaign_name ?? $comparisonRow?->campaign_name,
            'thumbnail_url' => $primaryRow?->thumbnail_url ?? $comparisonRow?->thumbnail_url,
            'ad_link' => $primaryRow?->ad_link ?? $comparisonRow?->ad_link,
            'primary_dates' => $formatDate($primaryRow?->date_key),
            'comparison_dates' => $formatDate($comparisonRow?->date_key),

            'roas_trend' => $compute($primaryRow?->roas, $comparisonRow?->roas),
            'cpa_trend' => $compute($primaryRow?->cpa, $comparisonRow?->cpa, false),
            'spend_trend' => $compute($primaryRow?->spend, $comparisonRow?->spend),
            'ctr_trend' => $compute($primaryRow?->ctr, $comparisonRow?->ctr),
            'impressions_trend' => $compute($primaryRow?->impressions, $comparisonRow?->impressions),
            'clicks_trend' => $compute($primaryRow?->clicks, $comparisonRow?->clicks),
        ];
    })->first();

    // Handle direct day-to-day comparison
    [$validCompareKey1, $validCompareKey2] = $this->findValidDateKeys($ad_id, $compareDate1, $compareDate2);
    $ad['custom_trends'] = [
        'ROAS' => '-', 'CPA' => '-', 'Spend' => '-', 'CTR' => '-', 'Impressions' => '-', 'Clicks' => '-',
    ];

    if ($validCompareKey1 && $validCompareKey2) {
        $dailyStats = FacebookAdStat::where('ad_id', $ad_id)
            ->whereIn('date_key', [$validCompareKey1, $validCompareKey2])
            ->get()
            ->keyBy('date_key');

        $row1 = $dailyStats[$validCompareKey1] ?? null;
        $row2 = $dailyStats[$validCompareKey2] ?? null;

        $ad['compare_date_1'] = $formatDate($validCompareKey1);
        $ad['compare_date_2'] = $formatDate($validCompareKey2);
        if ($row1 && $row2) {
            $ad['custom_trends'] = [
                'ROAS' => $compute($row2?->roas, $row1?->roas),
                'CPA' => $compute($row2?->cpa, $row1?->cpa, false),
                'Spend' => $compute($row2?->spend, $row1?->spend),
                'CTR' => $compute($row2?->ctr, $row1?->ctr),
                'Impressions' => $compute($row2?->impressions, $row1?->impressions),
                'Clicks' => $compute($row2?->clicks, $row1?->clicks),
            ];
        }
    }

    // Fetch data for charts
    $chartData = FacebookAdStat::where('ad_id', $ad_id)
        ->where('status', 'active')
        ->when($primary !== 'daily', fn($q) => $q->where('interval', $primary))
        ->when($compareDate1 && $compareDate2, fn($q) => $q->whereBetween('date_key', [$compareDate1, $compareDate2]),
            fn($q) => $startDate && $endDate ? $q->whereBetween('date_key', [$startDate, $endDate]) : $q)
        ->orderBy('date_key')
        ->get();

    $chartLabels = $chartData->pluck('date_key')->map(fn($d) =>
        str_contains($d, '_') ? Carbon::parse(explode('_', $d)[0])->format('M j') : Carbon::parse($d)->format('M j'));

    $chartRoas = $chartData->pluck('roas')->map(fn($v) => round($v, 2));
    $chartCpa = $chartData->pluck('cpa')->map(fn($v) => round($v, 2));
    $chartCtr = $chartData->pluck('ctr')->map(fn($v) => round($v, 2));

    if (!$ad) {
        return back()->with('error', 'No active data found for this ad in the selected interval or date range.');
    }
   

// Construct trend data from your stats (already stripped/cleaned)
$trendData = [
    'roas' => strip_tags($ad['roas_trend'] ?? '-'),
    'cpa' => strip_tags($ad['cpa_trend'] ?? '-'),
    'spend' => strip_tags($ad['spend_trend'] ?? '-'),
    'ctr' => strip_tags($ad['ctr_trend'] ?? '-'),
    'impressions' => strip_tags($ad['impressions_trend'] ?? '-'),
    'clicks' => strip_tags($ad['clicks_trend'] ?? '-'),
];

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
    $query = FacebookAdStat::where('ad_id', $ad_id)->where('status', 'active');

    // Handle predefined range
    if ($request->has('range')) {
        $now = now();
        switch ($request->get('range')) {
            case 'last_7':
                $query->whereDate('date_key', '>=', $now->copy()->subDays(7));
                break;
            case 'last_30':
                $query->whereDate('date_key', '>=', $now->copy()->subDays(30));
                break;
            case 'last_90':
                $query->whereDate('date_key', '>=', $now->copy()->subDays(90));
                break;
        }
    }

    // Handle manual range
    if ($request->filled('start_date') && $request->filled('end_date')) {
        $query->whereBetween('date_key', [$request->start_date, $request->end_date]);
    }

    $stats = $query->orderBy('date_key')->get()->map(function ($item) {
        return [
            'date' => $item->date_key,
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

public function findValidDateKeys($ad_id, $date1, $date2, $maxTries = 7)
{
    $validDate1 = null;
    $validDate2 = null;

    // Step 1: Try exact key format first
    $exactKeys = FacebookAdStat::where('ad_id', $ad_id)
        ->whereIn('date_key', [
            "{$date1}_{$date1}",
            "{$date2}_{$date2}"
        ])
        ->pluck('date_key')
        ->toArray();

    if (in_array("{$date1}_{$date1}", $exactKeys)) {
        $validDate1 = "{$date1}_{$date1}";
       
    }

    if (in_array("{$date2}_{$date2}", $exactKeys)) {
        $validDate2 = "{$date2}_{$date2}";
        
    }

    // Step 2: Forward search if still not found
    if (!$validDate1 || !$validDate2) {
        for ($direction = 1; $direction >= -1; $direction -= 2) { // +1 (forward), then -1 (backward)
            for ($attempt = 0; $attempt <= $maxTries; $attempt++) {
                $tryDate1 = Carbon::parse($date1)->addDays($attempt * $direction)->toDateString();
                $tryDate2 = Carbon::parse($date2)->addDays($attempt * $direction)->toDateString();

                $candidateKeys = FacebookAdStat::where('ad_id', $ad_id)
                    ->where(function ($q) use ($tryDate1, $tryDate2) {
                        $q->where('date_key', 'like', "%{$tryDate1}%")
                          ->orWhere('date_key', 'like', "%{$tryDate2}%");
                    })
                    ->pluck('date_key')
                    ->unique()
                    ->toArray();

          

                foreach ($candidateKeys as $key) {
                    if (!str_contains($key, '_')) continue;

                    [$start, $end] = explode('_', $key);

                    if (!$validDate1 && Carbon::parse($tryDate1)->between($start, $end)) {
                        $validDate1 = $key;
                     
                    }

                    if (!$validDate2 && Carbon::parse($tryDate2)->between($start, $end)) {
                        $validDate2 = $key;
                      
                    }

                    if ($validDate1 && $validDate2) break 2;
                }
            }
        }
    }



    return [$validDate1, $validDate2];
}


}
