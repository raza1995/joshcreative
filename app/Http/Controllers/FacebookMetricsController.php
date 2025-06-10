<?php

namespace App\Http\Controllers;

use App\Models\FacebookAd;
use App\Models\FacebookAdMetric;
use App\Models\FacebookAdStat;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;

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
        ->addColumn('order_count', fn($row) => "<a href='".route('facebook.ad.orders', ['ad_id' => $row->ad_id])."' target='_blank'>{$row->order_count} Orders</a>")
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
}
