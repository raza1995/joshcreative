<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FacebookAdsService;
use App\Services\FacebookMetricsSyncService;
use Carbon\Carbon;

class SyncFacebookAdsDaily extends Command
{
    protected $signature = 'facebook:sync-daily';
    protected $description = '📅 Sync the last 7 days of Facebook Ads data (daily interval)';

    public function handle(FacebookAdsService $fb, FacebookMetricsSyncService $sync)
    {
        $dates = collect(range(1, 7))
            ->map(fn($i) => Carbon::now()->subDays($i)->toDateString());

        $campaignGroups = $fb->getAllAdAccountCampaigns();
        $total = 0;

        foreach ($campaignGroups as $group) {
            foreach ($dates as $date) {
                $this->info("📅 Syncing: {$group['campaign']['name']} ({$group['account']}) — {$date}");

                try {
                    $sync->syncCampaign(
                        $group['campaign']['id'],
                        $group['campaign']['name'],
                        $date,
                        $date,
                        $group['account']
                    );
                    $total++;
                } catch (\Exception $e) {
                    $this->error("❌ Failed on {$group['campaign']['name']} for {$date}: " . $e->getMessage());
                    \Log::error("Facebook Sync Error", [
                        'campaign_id' => $group['campaign']['id'],
                        'account' => $group['account'],
                        'date' => $date,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("✅ Finished syncing {$total} campaign-day rows across last 7 days.");
    }
}
