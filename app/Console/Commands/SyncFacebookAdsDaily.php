<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FacebookAdsService;
use App\Jobs\SyncFacebookAdsToDbJob;

class SyncFacebookAdsDaily extends Command
{
    protected $signature = 'facebook:sync-daily';
    protected $description = '📅 Dispatch daily Facebook Ads sync to DB for all campaigns';

    public function handle(FacebookAdsService $fb)
    {
        $startDate = now()->subDay()->toDateString();
        $endDate = now()->toDateString();
    
        $campaignGroups = $fb->getAllAdAccountCampaigns();
        $count = 0;
    
        foreach ($campaignGroups as $group) {
            SyncFacebookAdsToDbJob::dispatch(
                $group['campaign']['id'], 
                $group['campaign']['name'],
                $startDate,
                $endDate,
                $group['account'],  
            );
            $count++;
        }
    
        $this->info("✅ Dispatched {$count} campaign sync jobs across all ad accounts.");
    }
    
}
