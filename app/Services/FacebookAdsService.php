<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookAdsService
{
    protected $accessToken;
    protected $adAccountId;
    protected $apiUrl = 'https://graph.facebook.com/v20.0';

    public function __construct()
    {
        $this->accessToken = env('FB_TOKEN');
        $this->adAccountId = env('AD_ACCOUNT_ID'); // Format: act_xxx
        Log::info("FacebookAdsService initialized with adAccountId: {$this->adAccountId}");
    }

    /**
     * Fetch all campaigns
     */
    public function getCampaigns()
    {
        Log::info("Fetching campaigns for adAccountId: {$this->adAccountId}");

        $response = Http::get("{$this->apiUrl}/{$this->adAccountId}/campaigns", [
            'access_token' => $this->accessToken,
            'effective_status' => '["ACTIVE","PAUSED"]',
            'fields' => 'id,name,status,adsets{id,name,daily_budget,start_time,end_time,status},insights{spend,impressions,clicks,ctr,cpc,cpm,purchase_roas,actions,conversions},total_count'
        ]);

        $campaigns = $response->json()['data'] ?? [];
        Log::info('Fetched ' . count($campaigns) . ' campaigns');
        return $campaigns;
    }

    /**
     * Fetch ad sets for a specific campaign
     */
    public function getAdSets($campaignId)
    {
        Log::info("Fetching ad sets for campaignId: {$campaignId}");

        $response = Http::get("{$this->apiUrl}/{$campaignId}/adsets", [
            'access_token' => $this->accessToken,
            'fields' => 'id,name,daily_budget,start_time,end_time,status,insights{spend,impressions,clicks,ctr,cpc,cpm,purchase_roas,actions,conversions},total_count'
        ]);

        $adSets = $response->json()['data'] ?? [];
        Log::info('Fetched ' . count($adSets) . ' ad sets');
        return $adSets;
    }

    /**
     * Fetch ads under an ad set
     */
    public function getAds($adSetId, $startDate = null, $endDate = null)
    {
        Log::info("Fetching ads for adSetId: {$adSetId}");

        $timeRange = [
            'since' => $startDate ?? now()->subDays(7)->toDateString(),
            'until' => $endDate ?? now()->toDateString()
        ];
     
        $response = Http::get("{$this->apiUrl}/{$adSetId}/ads", [
            'access_token' => $this->accessToken,
            'fields' => 'id,name,insights{spend,impressions,clicks,ctr,cpc,cpm,purchase_roas,actions,conversions},creative{id,name,object_story_spec,effective_instagram_story_id,effective_instagram_media_id,instagram_permalink_url},status,effective_status,ad_review_feedback,created_time,updated_time',
            'time_range' => json_encode($timeRange)

        ]);
   
        $ads = $response->json()['data'] ?? [];
        Log::info('Fetched ads data: ' . json_encode($ads) . ' | Total ads: ' . count($ads));

      
        return $ads;
    }

    /**
     * Fetch a detailed ad creative object
     */
    public function getAdCreative($creativeId)
    {
        Log::info("Fetching ad creative for creativeId: {$creativeId}");
    
        $fields = [
            'id','account_id','actor_id','adlabels',
            'authorization_category','effective_authorization_category',
            'name','object_story_id','effective_object_story_id','object_story_spec',
            'status','body','title', 'thumbnail_url', 'thumbnail_id',
            'image_url','image_hash','video_id','call_to_action_type','call_to_action',
            'link_url','link_destination_display_url','link_og_id',
            'object_url','object_type','instagram_permalink_url',
            'instagram_user_id','source_facebook_post_id','source_instagram_media_id',
            'asset_feed_spec','template_url','template_url_spec',
            'url_tags','page_welcome_message','platform_customizations','portrait_customizations'
        ];
    
        $response = Http::get("{$this->apiUrl}/{$creativeId}", [
            'access_token' => $this->accessToken,
            'fields' => implode(',', $fields),
            'thumbnail_width' => 150,
            'thumbnail_height' => 120
        ]);
    
        $creative = $response->json();
    
        // Handle dynamic creative via asset_feed_spec
        $asset = $creative['asset_feed_spec'] ?? [];
    
        $compiled = [
            'id' => $creative['id'] ?? null,
            'name' => $creative['name'] ?? null,
            'status' => $creative['status'] ?? null,
            'authorization_category' => $creative['authorization_category'] ?? null,
            'effective_authorization_category' => $creative['effective_authorization_category'] ?? null,
            'call_to_action_type' => $creative['call_to_action_type'] ?? ($asset['call_to_action_types'][0] ?? null),
            'title' => $creative['title'] ?? ($asset['titles'][0]['text'] ?? null),
            'body' => $creative['body'] ?? ($asset['bodies'][0]['text'] ?? null),
            'description' => $asset['descriptions'][0]['text'] ?? null,
            'link_url' => $creative['link_url'] ?? ($asset['link_urls'][0]['website_url'] ?? null),
            'display_url' => $asset['link_urls'][0]['display_url'] ?? null,
            'image_url' => $this->getImageUrlFromCreative($creative, $asset),
            'video_id' => $creative['video_id'] ?? null,
            'thumbnail_url' => $creative['thumbnail_url'] ?? null,
            'ad_post_link' => $this->getAdPostLink($creative),
        ];
    
        Log::info('Normalized creative data', $compiled);
   
        return $compiled;
    }
    

    /**
     * Fetch campaign insights
     */
    public function getCampaignInsights($campaignId)
    {
        return $this->getInsights("{$campaignId}", 'campaign');
    }

    /**
     * Fetch ad set insights
     */
    public function getAdSetInsights($adSetId)
    {
        return $this->getInsights("{$adSetId}", 'adset');
    }

    /**
     * Fetch ad insights
     */
    public function getAdInsights($adId)
    {
        return $this->getInsights("{$adId}", 'ad');
    }

    /**
     * Unified insights handler
     */
    protected function getInsights($id, $type)
    {
        Log::info("Fetching {$type} insights for ID: {$id}");

        $fields = [
            'impressions', 'clicks', 'ctr', 'cpc',
            'spend', 'cpm', 'purchase_roas', 'actions', 'conversions', 'effective_status', 'ad_id', 'adset_id', 'campaign_id', 'updated_time'
        ];

        $timeRange = [
            'since' => now()->subDays(7)->toDateString(),
            'until' => now()->toDateString()
        ];

        $response = Http::get("{$this->apiUrl}/{$id}/insights", [
            'access_token' => $this->accessToken,
            'fields' => implode(',', $fields),
            'time_range' => json_encode($timeRange)
        ]);

        $data = $response->json()['data'][0] ?? [];
        Log::info("Fetched {$type} insights: " . json_encode($data));
        return $data;
    }

    /**
     * Extract image URL from creative
     */
    public function getImageUrlFromCreative($creative, $assetFeed = [])
    {
        // Priority 1: flat creative field
        if (!empty($creative['image_url'])) {
            return $creative['image_url'];
        }
    
        // Priority 2: object_story_spec field
        if (!empty($creative['object_story_spec']['link_data']['image_url'])) {
            return $creative['object_story_spec']['link_data']['image_url'];
        }
    
        // Priority 3: from asset_feed_spec
        if (!empty($assetFeed['images'][0]['hash'])) {
            $imageHash = $assetFeed['images'][0]['hash'];
    
            $response = Http::get("{$this->apiUrl}/{$imageHash}", [
                'access_token' => $this->accessToken,
                'fields' => 'url'
            ]);
    
            return $response->json()['url'] ?? null;
        }
    
        return null;
    }
    

    /**
     * Generate Facebook or Instagram post link from creative
     */
    public function getAdPostLink($creative)
    {
        $storyId = $creative['object_story_id'] ?? $creative['effective_object_story_id'] ?? null;

        if ($storyId && str_contains($storyId, '_')) {
            [$pageId, $postId] = explode('_', $storyId);
            return "https://www.facebook.com/{$pageId}/posts/{$postId}";
        }

        return $creative['instagram_permalink_url'] ?? null;
    }

    public function getVideoUrlFromId($videoId): array
    {
        Log::info("🎥 Fetching video details for videoId: {$videoId}");
    
        try {
            $response = Http::get("{$this->apiUrl}/{$videoId}", [
                'access_token' => $this->accessToken,
                'fields' => 'source,picture'
            ]);
    
            Log::info("📦 Response data received", ['data' => $response->json()]);
            $data = $response->json();
    
            if (isset($data['source']) || isset($data['picture'])) {
                Log::info("✅ Video details fetched successfully", [
                    'video_id' => $videoId,
                    'source' => $data['source'] ?? null,
                    'picture' => $data['picture'] ?? null,
                ]);
    
                return [
                    'video_url' => $data['source'] ?? null,
                    'thumbnail_url' => $data['picture'] ?? null,
                    'video_id' => $videoId,
                ];
            }
    
            Log::info("⚠️ No video source or picture found for videoId: {$videoId}");
        } catch (\Exception $e) {
            Log::error("❌ Exception while fetching video for {$videoId}: " . $e->getMessage());
        }
    
        return [
            'video_url' => null,
            'thumbnail_url' => null,
            'video_id' => $videoId,
        ];
    }
    

}
