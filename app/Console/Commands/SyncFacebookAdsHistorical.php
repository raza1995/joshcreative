<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FacebookAd;
use App\Models\FacebookAdMetric;
use App\Services\FacebookAdsService;

class SyncFacebookAdsHistorical extends Command
{
    protected $signature = 'facebook:sync-metrics {--days=90} {--month=}';
    protected $description = '📈 Sync Facebook Ad metrics for existing ads over the past N days';

    public function handle(FacebookAdsService $fb)
    {
        $days = (int) $this->option('days');
        $this->info("Option 'days' value received in SyncFacebookAdsHistorical: {$days}");
        $month = $this->option('month');
        if ($month) {
            $year = now()->year;
            $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
            $endDate = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
            $interval = "month_" . str_pad($month, 2, '0', STR_PAD_LEFT);
            $dateKey = "{$startDate}_{$endDate}";
        } else {
            $startDate = now()->subDays($days)->startOfDay()->toDateString();
            $endDate = now()->endOfDay()->toDateString();
            $interval = "custom_{$days}";
            $dateKey = "{$startDate}_{$endDate}";
        }

        $ads = FacebookAd::all();
        $bar = $this->output->createProgressBar($ads->count());
        $bar->start();

        foreach ($ads as $ad) {
            try {
                \Log::info("Fetching insights for ad_id: {$ad->ad_id} from {$startDate} to {$endDate}");
                $insights = $fb->getInsights($ad->ad_id, $startDate, $endDate, 'ad');
                \Log::info("Fetched insights for ad_id: {$ad->ad_id}", ['insights' => $insights]);

                if (empty($insights)) {
                    continue;
                }

                $actions = $insights['actions'] ?? [];
                $conversions = $this->extractActionValue($actions, 'offsite_conversion.fb_pixel_purchase');
                $cpa = $conversions > 0 ? round($insights['spend'] / $conversions, 2) : null;


                FacebookAdMetric::updateOrCreate([
                    'ad_id' => $ad->ad_id,
                    'interval' => $interval,
                    'date_key' => $dateKey,
                ], [
                    'facebook_ad_id' => $ad->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'impressions' => $insights['impressions'] ?? 0,
                    'clicks' => $insights['clicks'] ?? 0,
                    'ctr' => $insights['ctr'] ?? null,
                    'cpc' => $insights['cpc'] ?? null,
                    'cpm' => $insights['cpm'] ?? null,
                    'spend' => $insights['spend'] ?? null,
                    'conversions' => $conversions,
                    'cpa' => $cpa,
                    'purchase_roas' => json_encode($insights['purchase_roas'] ?? []),
                    'add_to_cart' => $this->extractActionValue($actions, 'offsite_conversion.fb_pixel_add_to_cart'),
                    'initiate_checkout' => $this->extractActionValue($actions, 'offsite_conversion.fb_pixel_initiate_checkout'),
                    'view_content' => $this->extractActionValue($actions, 'offsite_conversion.fb_pixel_view_content'),
                ]);
            } catch (\Throwable $e) {
                \Log::error("❌ Failed syncing ad {$ad->ad_id}: " . $e->getMessage());
            }

            $bar->advance();
            usleep(100 * 1000); // throttle
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Synced metrics for {$ads->count()} ads.");
    }

    protected function extractActionValue(array $actions, string $type)
    {
        foreach ($actions as $action) {
            if ($action['action_type'] === $type) {
                return (int) $action['value'];
            }
        }
        return 0;
    }
}
