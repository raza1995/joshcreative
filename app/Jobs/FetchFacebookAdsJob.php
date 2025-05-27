<?php
namespace App\Jobs;

use App\Services\FacebookAdsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FetchFacebookAdsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaignId;
    protected $campaignName;

    public function __construct($campaignId, $campaignName)
    {
        $this->campaignId = $campaignId;
        $this->campaignName = $campaignName;
    }

    public function handle(FacebookAdsService $fb)
    {
        Log::info("Processing Facebook campaign: {$this->campaignName} ({$this->campaignId})");

        Cache::put('fb_ads_job_status', 'processing', now()->addMinutes(30));

        try {
            $output = [];
            $adSets = $fb->getAdSets($this->campaignId);

            foreach ($adSets as $adSet) {
                $ads = $fb->getAds($adSet['id']);

                foreach ($ads as $ad) {
                    $creative = $fb->getAdCreative($ad['creative']['id'] ?? '');
                    $insights = $fb->getAdInsights($ad['id']);

                    $output[] = [
                        'campaign_id' => $this->campaignId,
                        'campaign_name' => $this->campaignName,
                        'adset_id' => $adSet['id'],
                        'adset_name' => $adSet['name'],
                        'ad_id' => $ad['id'],
                        'ad_name' => $ad['name'],
                        'ad_link' => $creative['ad_post_link'] ?? null,
                        'title' => $creative['title'] ?? null,
                        'body' => $creative['body'] ?? null,
                        'image_url' => $creative['image_url'] ?? null,
                        'link_url' => $creative['link_url'] ?? null,
                        'display_url' => $creative['display_url'] ?? null,
                        'call_to_action' => $creative['call_to_action_type'] ?? null,
                        'impressions' => $insights['impressions'] ?? null,
                        'clicks' => $insights['clicks'] ?? null,
                        'ctr' => $insights['ctr'] ?? null,
                        'cpc' => $insights['cpc'] ?? null,
                        'cpm' => $insights['cpm'] ?? null,
                        'spend' => $insights['spend'] ?? null,
                        'purchase_roas' => $insights['purchase_roas'] ?? null,
                    ];
                }
            }

            $filename = 'fb_ads/' . now()->format('Ymd_His') . '_' . preg_replace('/\s+/', '_', $this->campaignName) . '.json';
            Storage::disk('public')->put($filename, json_encode($output, JSON_PRETTY_PRINT));

            Cache::put('fb_ads_job_status', 'completed', now()->addMinutes(30));
            Log::info("✅ Facebook Ads data saved: {$filename}");

        } catch (\Throwable $e) {
            Cache::put('fb_ads_job_status', 'failed', now()->addMinutes(30));
            Log::error('❌ Facebook Ads Job failed: ' . $e->getMessage());
        }
    }
}