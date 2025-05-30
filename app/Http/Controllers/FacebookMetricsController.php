<?php

namespace App\Http\Controllers;

use App\Models\FacebookAd;
use App\Models\FacebookAdMetric;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
class FacebookMetricsController extends Controller
{


    public function index()
{
    return view('facebook.index'); // make sure this blade exists
}
    public function filter(Request $request)
    {
        $interval = $request->get('interval', 'daily');
    
        $validIntervals = ['daily', 'weekly', 'monthly'];
        if (!in_array($interval, $validIntervals)) {
            return response()->json(['error' => 'Invalid interval'], 400);
        }
    
        $dateKey = match ($interval) {
            'daily' => now()->subDay()->toDateString(),
            'weekly' => now()->startOfWeek()->toDateString(),
            'monthly' => now()->startOfMonth()->toDateString(),
        };
    
        // Fetch ads with attached metrics for the interval and date
        $ads = FacebookAd::with(['metrics' => function ($query) use ($interval, $dateKey) {
            $query->where('interval', $interval)
                  ->where('date_key', $dateKey);
        }])
        ->whereHas('metrics', function ($query) use ($interval, $dateKey) {
            $query->where('interval', $interval)
                  ->where('date_key', $dateKey);
        })
        ->orderByDesc(FacebookAdMetric::select('spend')
            ->whereColumn('facebook_ads.id', 'facebook_ad_metrics.facebook_ad_id')
            ->where('interval', $interval)
            ->where('date_key', $dateKey)
            ->limit(1)
        )
        ->get();
    
        return response()->json([
            'interval' => $interval,
            'date_key' => $dateKey,
            'ads' => $ads,
        ]);
    }



    public function getData(Request $request)
    {
        $interval = $request->get('interval', 'daily');
        $dateKey = match ($interval) {
            'daily' => now()->subDay()->toDateString(),
            'weekly' => now()->startOfWeek()->toDateString(),
            'monthly' => now()->startOfMonth()->toDateString(),
        };
    
        $ads = FacebookAd::with(['metrics' => function ($q) use ($interval, $dateKey) {
            $q->where('interval', $interval)->where('date_key', $dateKey);
        }]);
    
        return DataTables::of($ads)
            ->addColumn('ad_id', fn($ad) => $ad->ad_id)
            ->addColumn('ad_account_name', fn($ad) => $ad->ad_account_name)
            ->addColumn('campaign_name', fn($ad) => $ad->campaign_name)
            ->addColumn('adset_name', fn($ad) => $ad->adset_name)
            ->addColumn('ad_name', fn($ad) => $ad->ad_name)
            ->addColumn('ad_type', fn($ad) => $ad->ad_type)
            ->addColumn('ad_link', fn($ad) => $ad->ad_link)
            ->addColumn('link_url', fn($ad) => $ad->link_url)
            ->addColumn('thumbnail_url', fn($ad) => $ad->thumbnail_url)
            ->addColumn('status', fn($ad) => $ad->status)
            ->addColumn('updated_time', fn($ad) => $ad->updated_time)
            ->addColumn('spend', fn($ad) => $ad->metrics->first()->spend ?? 0)
            ->addColumn('clicks', fn($ad) => $ad->metrics->first()->clicks ?? 0)
            ->addColumn('ctr', fn($ad) => $ad->metrics->first()->ctr ?? 0)
            ->addColumn('cpa', fn($ad) => $ad->metrics->first()->cpa ?? null)
            ->addColumn('roas', function ($ad) {
                $roas = json_decode($ad->metrics->first()->purchase_roas ?? '[]', true);
                return collect($roas)->pluck('value')->implode(', ');
            })
            ->addColumn('interval', fn() => $interval)
    
            // ORDER SUPPORT
            ->orderColumn('spend', function ($query, $order) use ($interval, $dateKey) {
                $query->join('facebook_ad_metrics as fam1', 'facebook_ads.ad_id', '=', 'fam1.ad_id')
                    ->where('fam1.interval', $interval)
                    ->where('fam1.date_key', $dateKey)
                    ->orderBy('fam1.spend', $order);
            })
            ->orderColumn('clicks', function ($query, $order) use ($interval, $dateKey) {
                $query->join('facebook_ad_metrics as fam2', 'facebook_ads.ad_id', '=', 'fam2.ad_id')
                    ->where('fam2.interval', $interval)
                    ->where('fam2.date_key', $dateKey)
                    ->orderBy('fam2.clicks', $order);
            })
            ->orderColumn('ctr', function ($query, $order) use ($interval, $dateKey) {
                $query->join('facebook_ad_metrics as fam3', 'facebook_ads.ad_id', '=', 'fam3.ad_id')
                    ->where('fam3.interval', $interval)
                    ->where('fam3.date_key', $dateKey)
                    ->orderBy('fam3.ctr', $order);
            })
            ->orderColumn('cpa', function ($query, $order) use ($interval, $dateKey) {
                $query->join('facebook_ad_metrics as fam4', 'facebook_ads.ad_id', '=', 'fam4.ad_id')
                    ->where('fam4.interval', $interval)
                    ->where('fam4.date_key', $dateKey)
                    ->orderBy('fam4.cpa', $order);
            })
            ->orderColumn('conversions', function ($query, $order) use ($interval, $dateKey) {
                $query->join('facebook_ad_metrics as fam5', 'facebook_ads.ad_id', '=', 'fam5.ad_id')
                    ->where('fam5.interval', $interval)
                    ->where('fam5.date_key', $dateKey)
                    ->orderBy('fam5.conversions', $order);
            })
            ->orderColumn('roas', function ($query, $order) use ($interval, $dateKey) {
                $query->join('facebook_ad_metrics as fam6', 'facebook_ads.ad_id', '=', 'fam6.ad_id')
                    ->where('fam6.interval', $interval)
                    ->where('fam6.date_key', $dateKey)
                    ->orderBy(DB::raw("JSON_EXTRACT(fam6.purchase_roas, '$[0].value')"), $order);
            })
            
    
            // Optional: order other native fields if needed
            ->orderColumn('ad_account_name', 'ad_account_name $1')
            ->orderColumn('ad_id', 'ad_id $1')
            ->orderColumn('adset_name', 'adset_name $1')
            ->orderColumn('campaign_name', 'campaign_name $1')
            ->orderColumn('updated_time', 'updated_time $1')
    
            ->rawColumns(['thumbnail_url', 'ad_link'])
            ->make(true);
    }
    
}
