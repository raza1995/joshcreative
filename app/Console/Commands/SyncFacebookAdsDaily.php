<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FacebookAdsService;
use App\Services\FacebookMetricsSyncService;

class SyncFacebookAdsDaily extends Command
{
    protected $signature = 'facebook:sync-daily';
    protected $description = '📅 Directly sync daily Facebook Ads to DB for all campaigns';

    public function handle(FacebookAdsService $fb, FacebookMetricsSyncService $sync)
    {
        // Get previous day in YYYY-MM-DD format (server timezone)
        // $yesterday = now()->subDay()->toDateString(); // e.g. '2025-07-26'
        $yesterday = 2025-07-25;
        $campaignGroups = $fb->getAllAdAccountCampaigns();
        $count = 0;
    
        foreach ($campaignGroups as $group) {
            $this->info("🔄 Syncing: {$group['campaign']['name']} ({$group['account']})");
    
            $sync->syncCampaign(
                $group['campaign']['id'],
                $group['campaign']['name'],
                $yesterday,
                $yesterday,
                $group['account']
            );
    
            $count++;
        }
    
        $this->info("✅ Synced {$count} campaigns successfully.");
    }
}
