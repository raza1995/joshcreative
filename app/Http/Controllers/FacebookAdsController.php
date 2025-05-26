<?php
namespace App\Http\Controllers;

use App\Services\FacebookAdsService;
use Illuminate\Support\Facades\Log;
class FacebookAdsController extends Controller
{
    public function index(FacebookAdsService $fb)
    {
        $compiledAds = [];
    
        $campaigns = $fb->getCampaigns();
    
        foreach ($campaigns as $campaign) {
            $adSets = $fb->getAdSets($campaign['id']);
    
            foreach ($adSets as $adSet) {
                $ads = $fb->getAds($adSet['id']);
    
                foreach ($ads as $ad) {
                    $creative = $fb->getAdCreative($ad['creative']['id'] ?? null);
                    $insights = $fb->getAdInsights($ad['id']);
    
                    $compiledAds[] = [
                        'campaign_id' => $campaign['id'],
                        'campaign_name' => $campaign['name'],
                        'adset_id' => $adSet['id'],
                        'adset_name' => $adSet['name'],
                        'ad_id' => $ad['id'],
                        'ad_name' => $ad['name'],
                        'ad_status' => $ad['status'] ?? null,
                        'ad_created' => $ad['created_time'] ?? null,
                        'creative_title' => $creative['title'] ?? null,
                        'creative_body' => $creative['body'] ?? null,
                        'creative_description' => $creative['description'] ?? null,
                        'call_to_action' => $creative['call_to_action_type'] ?? null,
                        'ad_post_link' => $creative['ad_post_link'] ?? null,
                        'image_url' => $creative['image_url'] ?? null,
                        'link_url' => $creative['link_url'] ?? null,
                        'display_url' => $creative['display_url'] ?? null,
                        'thumbnail_url' => $creative['thumbnail_url'] ?? null,
                        'video_id' => $creative['video_id'] ?? null,
                        'impressions' => $insights['impressions'] ?? null,
                        'clicks' => $insights['clicks'] ?? null,
                        'ctr' => $insights['ctr'] ?? null,
                        'cpc' => $insights['cpc'] ?? null,
                        'cpm' => $insights['cpm'] ?? null,
                        'spend' => $insights['spend'] ?? null,
                        'purchase_roas' => json_encode($insights['purchase_roas'] ?? []),
                        'actions' => json_encode($insights['actions'] ?? []),
                        'conversions' => json_encode($insights['conversions'] ?? []),
                    ];
                }
            }
        }
    
        // You can return JSON, or store in DB, or send to Google Sheets
        return response()->json($compiledAds);
    }
    
}
