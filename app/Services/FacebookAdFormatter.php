<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;

class FacebookAdFormatter
{
    protected $fb;

    public function __construct(FacebookAdsService $fb)
    {
        $this->fb = $fb;
    }

    public function formatAd($campaignId, $campaignName, $adSet, $ad, $startDate, $endDate, $adAccountName = null): ?array
    {
        try {
            $creative = $this->fb->getAdCreative($ad['creative']['id'] ?? '');
            $insights = $ad['insights']['data'][0] ?? [];
            $actions = $insights['actions'] ?? [];

            $conversionTypes = [
                'purchase', 'onsite_web_purchase', 'onsite_web_app_purchase',
                'offsite_conversion.fb_pixel_purchase', 'omni_purchase',
                'web_in_store_purchase', 'web_app_in_store_purchase',
            ];

            $conversionCount = collect($actions)
                ->filter(fn($a) => in_array($a['action_type'], $conversionTypes))
                ->sum('value');

            $spend = (float) ($insights['spend'] ?? 0);
            $cpa = ($conversionCount > 0 && $spend > 0) ? round($spend / $conversionCount, 2) : null;

            $videoId = $ad['creative']['object_story_spec']['video_data']['video_id'] ?? null;
            $adType = 'image';
            $videoData = [];

            if (!empty($videoId)) {
                $adType = 'video';
                $videoData = $this->fb->getVideoUrlFromId($videoId);
            }

            return [
                'ad_account_name' => $adAccountName ?? null,
                'campaign_id' => $campaignId,
                'campaign_name' => $campaignName,
                'adset_id' => $adSet['id'] ?? null,
                'adset_name' => $adSet['name'] ?? null,
                'ad_id' => $ad['id'] ?? null,
                'ad_name' => $ad['name'] ?? null,
                'ad_type' => $adType,
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
                'spend' => $spend,
                'status' => $ad['effective_status'] ?? null,
                'updated_time' => isset($ad['updated_time']) ? date('Y-m-d H:i:s', strtotime($ad['updated_time'])) : null,
              'purchase_roas' => isset($insights['purchase_roas']) ? json_encode($insights['purchase_roas']) : null,
                'conversions' => $conversionCount,
                'cpa' => $cpa,
                'video_url' => $videoData['video_url'] ?? null,
                'full_picture' => $videoData['thumbnail_url'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error("❌ Error formatting ad: {$ad['id']} - " . $e->getMessage());
            return null;
        }
    }
}
