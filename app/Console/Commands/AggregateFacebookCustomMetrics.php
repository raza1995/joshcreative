<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FacebookAdMetric;
use Illuminate\Support\Facades\DB;

class AggregateFacebookCustomMetrics extends Command
{
    protected $signature = 'facebook:aggregate-custom-metrics {--days=90}';
    protected $description = 'Aggregate daily Facebook metrics into a custom_N row per ad';

    public function handle()
    {
        $days = (int) $this->option('days');
        $interval = "custom_{$days}";
        $startDate = now()->subDays($days)->toDateString();
        $endDate = now()->toDateString();
        $dateKey = "{$startDate}_{$endDate}";

        $this->info("🔄 Aggregating {$interval} metrics from {$startDate} to {$endDate}");

        $adIds = FacebookAdMetric::where('interval', 'daily')
            ->whereBetween('date_key', [$startDate, $endDate])
            ->distinct()
            ->pluck('ad_id');

        foreach ($adIds as $adId) {
            $daily = FacebookAdMetric::where('ad_id', $adId)
                ->where('interval', 'daily')
                ->whereBetween('date_key', [$startDate, $endDate])
                ->get();

            if ($daily->isEmpty()) continue;

            $aggregated = [
                'impressions' => $daily->sum('impressions'),
                'clicks' => $daily->sum('clicks'),
                'ctr' => round($daily->avg('ctr'), 2),
                'cpc' => round($daily->avg('cpc'), 2),
                'cpm' => round($daily->avg('cpm'), 2),
                'spend' => $daily->sum('spend'),
                'conversions' => $daily->sum('conversions'),
                'cpa' => null,
                'purchase_roas' => $daily->flatMap(function ($row) {
                    return json_decode($row->purchase_roas ?: '[]', true);
                })->values()->toJson(),
                'add_to_cart' => $daily->sum('add_to_cart'),
                'initiate_checkout' => $daily->sum('initiate_checkout'),
                'view_content' => $daily->sum('view_content'),
                'facebook_ad_id' => $daily->first()->facebook_ad_id,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];

            $aggregated['cpa'] = $aggregated['conversions'] > 0
                ? round($aggregated['spend'] / $aggregated['conversions'], 2)
                : null;

            FacebookAdMetric::updateOrCreate([
                'ad_id' => $adId,
                'interval' => $interval,
                'date_key' => $dateKey,
            ], $aggregated);

            $this->info("✅ Aggregated for ad_id: {$adId}");
        }

        $this->info("🎉 Done aggregating custom_{$days} metrics.");
    }
}
