<?php

namespace App\Console\Commands;

use App\Models\FacebookAd;
use App\Models\FacebookAdStat;
use Illuminate\Console\Command;

class AggregateFacebookAdStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facebook:aggregate-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ads = FacebookAd::with(['metrics', 'shopifyOrders'])->get();
    
        foreach ($ads as $ad) {
            $grouped = $ad->metrics->groupBy(fn($m) => $m->interval . ':' . $m->date_key);
    
            foreach ($grouped as $key => $metrics) {
              
                [$interval, $dateKey] = explode(':', $key);

                if (strpos($dateKey, '_') !== false) {
                    [$start_date, $end_date] = explode('_', $dateKey, 2);
                } else {
                    $start_date = $dateKey;
                    $end_date = null;
                }
                
               
                $spend = $metrics->sum('spend');
                $clicks = $metrics->sum('clicks');
                $impressions = $metrics->sum('impressions');
                $reach = $metrics->sum('reach');
                $ctr = $metrics->pluck('ctr')->filter()->avg();
                $cpa = $metrics->pluck('cpa')->filter()->avg();
                $frequency = $metrics->pluck('frequency')->filter()->avg();
                $roasData = $metrics->map(function ($metric) {
                    $values = json_decode($metric->purchase_roas, true);
                    return [
                        'spend' => (float) $metric->spend,
                        'value' => $values[0]['value'] ?? null
                    ];
                })->filter(fn($r) => $r['value'] !== null);
    
                $weightedRoas = $roasData->sum(fn($r) => $r['value'] * $r['spend']);
                $roas = $spend > 0 ? round($weightedRoas / $spend, 2) : null;
        
                FacebookAdStat::updateOrCreate([
                    'ad_id' => $ad->ad_id,
                    'interval' => $interval,
                    'date_key' => $dateKey,
                ], [
                    'facebook_ad_id' => $ad->id,
                    'campaign_name' => $ad->campaign_name,
                    'adset_name' => $ad->adset_name,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'ad_name' => $ad->ad_name,
                    'ad_type' => $ad->ad_type,
                    'ad_account_name' => $ad->ad_account_name,
                    'ad_link' => $ad->ad_link,
                    'link_url' => $ad->link_url,
                    'thumbnail_url' => $ad->thumbnail_url,
                    'status' => $ad->status,
                    'updated_time' => $ad->updated_time,
                    'order_count' => $ad->shopifyOrders->count(),
                    'spend' => $spend,
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => $ctr ? round($ctr, 2) : null,
                    'cpa' => $cpa ? round($cpa, 2) : null,
                    'roas' => $roas,
                    'reach' => $reach,
                    'frequency' => $frequency ? round($frequency, 3) : null,
                ]);
            }
        }
    }

    
    
}
