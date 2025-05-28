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
    protected $startDate;
    protected $endDate;
    public function __construct($campaignId, $campaignName, $startDate, $endDate)
    {
        $this->campaignId = $campaignId;
        $this->campaignName = $campaignName;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function handle(FacebookAdsService $fb)
    {
        Log::info("Processing Facebook campaign: {$this->campaignName} ({$this->campaignId})");

        Cache::put('fb_ads_job_status', 'processing', now()->addMinutes(30));

    
            $output = [];
            $adSets = $fb->getAdSets($this->campaignId);
              
            foreach ($adSets as $adSet) {
           
                $ads = $fb->getAds($adSet['id'], $this->startDate, $this->endDate);

               
                foreach ($ads as $ad) {
                    try {
                    $creative = $fb->getAdCreative($ad['creative']['id'] ?? '');
                    $insights = $ad['insights']['data'][0] ?? [];
                    Log::info("Insights ads updated data retrieved: ", $insights);
                    $actions = $insights['actions'] ?? [];
                    Log::info("Actions data retrieved: ", $actions);
                    $conversionTypes = [
                        'purchase',
                        'onsite_web_purchase',
                        'onsite_web_app_purchase',
                        'offsite_conversion.fb_pixel_purchase',
                        'omni_purchase',
                        'web_in_store_purchase',
                        'web_app_in_store_purchase',
                    ];
                    
                    $conversionCount = 0;

                    $actions = $insights['actions'] ?? [];
                    foreach ($actions as $action) {
                        if (isset($action['action_type']) && in_array($action['action_type'], $conversionTypes)) {
                            $conversionCount += (int) $action['value'];
                        }
                    }

                        // Calculate CPA if we have conversions and spend
                        $spend = isset($insights['spend']) ? (float) $insights['spend'] : 0;
                        $cpa = ($conversionCount > 0 && $spend > 0) ? round($spend / $conversionCount, 2) : null;

                        $videoUrl = null;
                        $videoId = $ad['creative']['object_story_spec']['video_data']['video_id'] ?? null;
                        $adType = ''; 
                       
                        Log::info("video id from the ads", ['video_id' => $videoId]);
                        
                        $videoUrl = null;
                        
                        if (!empty($videoId)) {
                            $adType = 'video';
                            $videoUrl = $fb->getVideoUrlFromId($videoId);
                        }else{
                            $adType = 'image';
                      
                        }
                        

                        $output[] = [
                        'campaign_id' => $this->campaignId ?? null,
                        'campaign_name' => $this->campaignName ?? null,
                        'adset_id' => $adSet['id'] ?? null,
                        'ad_type' => $adType,
                        'adset_name' => $adSet['name'] ?? null,
                        'ad_id' => $ad['id'] ?? null,
                        'ad_name' => $ad['name'] ?? null,
                        'ad_link' => $creative['ad_post_link'] ?? null,
                        'title' => $creative['title'] ?? null,
                        'body' => $creative['body'] ?? null,
                        'description' => $creative['description'] ?? null,
                        'thumbnail_url' => $creative['thumbnail_url'] ?? null,
                        'video_id' => $creative['video_id'] ?? null, 
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
                        'status' => $ad['effective_status'] ?? null,
                        'updated_time' => $ad['updated_time'] ?? null,
                        'purchase_roas' => $insights['purchase_roas'] ?? null,
                        'conversions' => $conversionCount ?? null,
                        'cpa' => $cpa ?? null,
                        'video_url' => $videoUrl['video_url'] ?? null,
                        "full_picture" => $videoUrl['thumbnail_url'] ?? null ,
                    ];
                } catch (\Throwable $innerEx) {
                    Log::error("❌ Failed processing ad ID {$ad['id']}: " . $innerEx->getMessage(), [
                        'exception' => $innerEx instanceof \Throwable ? [
                            'file' => $innerEx->getFile(),
                            'line' => $innerEx->getLine(),
                            'trace' => $innerEx->getTraceAsString()
                        ] : []
                    ]);
                    continue;
                }
                }
           
            }
            $filename = 'fb_ads/' . $this->campaignId . '_' . $this->campaignName . '.json';
            Storage::disk('public')->put($filename, json_encode($output, JSON_PRETTY_PRINT));
            

            Cache::put('fb_ads_job_status', 'completed', now()->addMinutes(30));
            Log::info("✅ Facebook Ads data saved: {$filename}");

            $this->mergeAllJsonFiles();

        
    }

    protected function mergeAllJsonFiles()
{
    $allFiles = Storage::disk('public')->files('fb_ads');
    $merged = [];

    foreach ($allFiles as $file) {
        if (str_ends_with($file, '.json')) {
            $content = json_decode(Storage::disk('public')->get($file), true);
            if (is_array($content)) {
                $merged = array_merge($merged, $content);
            }
        }
    }

    // Clean and overwrite the merged file
    $mergedPath = 'fb_ads_merged/fb_ads_all.json';
    Storage::disk('public')->put($mergedPath, json_encode($merged, JSON_PRETTY_PRINT));

    Log::info("✅ Merged Facebook Ads saved: {$mergedPath}");
}

}