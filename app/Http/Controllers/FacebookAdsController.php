<?php
namespace App\Http\Controllers;

use App\Services\FacebookAdsService;
use Illuminate\Support\Facades\Log;
class FacebookAdsController extends Controller
{
    public function index(FacebookAdsService $fb)
    {
        $output = [];

        $campaigns = $fb->getCampaigns();

        foreach ($campaigns as $campaign) {
            $adSets = $fb->getAdSets($campaign['id']);

            foreach ($adSets as $adSet) {
                $ads = $fb->getAds($adSet['id']);

                foreach ($ads as $ad) {
                    $creative = $fb->getAdCreative($ad['creative']['id'] ?? '');
                    Log::info('Fetching ads for ad set', ['adSetId' => $ad]);
                    $output[] = [
                        'campaign_id' => $campaign['id'],
                        'campaign_name' => $campaign['name'],
                        'adset_id' => $adSet['id'],
                        'adset_name' => $adSet['name'],
                        'ad_id' => $ad['id'],
                        'ad_name' => $ad['name'],
                        'ad_link' => $creative['object_story_spec']['link_data']['link'] ?? null,
                        'creative_image' => $creative['object_story_spec']['link_data']['image_hash'] ?? null,
                        'creative_title' => $creative['object_story_spec']['link_data']['name'] ?? $creative['title'] ?? null,
                    ];
                }
            }
        }

        return response()->json($output);
    }
}
