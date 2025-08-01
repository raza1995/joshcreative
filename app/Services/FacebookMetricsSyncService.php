<?php
namespace App\Services;
use App\Models\FacebookAd;
use App\Models\FacebookAdMetric;
use App\Services\FacebookAdFormatter;
use App\Services\FacebookAdsService;

class FacebookMetricsSyncService
{
    public function syncCampaign($campaignId, $campaignName, $startDate, $endDate, $adAccountName = null)
    {
        $fb = app(FacebookAdsService::class);
        $formatter = app(FacebookAdFormatter::class);

        $adSets = $fb->getAdSets($campaignId);

        foreach ($adSets as $adSet) {
            $ads = $fb->getAds($adSet['id'], $startDate, $endDate);

            foreach ($ads as $ad) {
                $formatted = $formatter->formatAd(
                    $campaignId, $campaignName, $adSet, $ad, $startDate, $endDate, $adAccountName
                );

                if (!$formatted) continue;

                $adModel = FacebookAd::updateOrCreate(
                    ['ad_id' => $formatted['ad_id']],
                    $formatted
                );

                $this->storeTimeBasedMetrics($fb, $adModel->id, $ad['id']);
            }
        }
    }

    protected function storeTimeBasedMetrics(FacebookAdsService $fb, $facebookAdId, $adId)
    {
        $intervals = [
            'daily' => [
                'start' => now()->subDay()->toDateString(),
                'end' => now()->subDay()->toDateString(),
            ],
            'weekly' => [
                'start' => now()->startOfWeek()->toDateString(),
                'end' => now()->endOfWeek()->toDateString(),
            ],
            'monthly' => [
                'start' => now()->startOfMonth()->toDateString(),
                'end' => now()->toDateString(),
            ],
        ];

        foreach ($intervals as $interval => $range) {
            $dateKey = "{$range['start']}_{$range['end']}";
            $insights = $fb->getInsights($adId, $range['start'], $range['end'], 'ad');
            if (empty($insights)) continue;

            $metrics = $insights;
            $actions = $metrics['actions'] ?? [];
            $conversions = $this->extractActionValue($actions, 'offsite_conversion.fb_pixel_purchase');
            $spend = $metrics['spend'] ?? 0;
            $cpa = $conversions > 0 ? round($spend / $conversions, 2) : null;

            FacebookAdMetric::updateOrCreate([
                'ad_id' => $adId,
                'interval' => $interval,
                'date_key' => $dateKey,
            ], [
                'facebook_ad_id' => $facebookAdId,
                'start_date' => $range['start'],
                'end_date' => $range['end'],
                'impressions' => $metrics['impressions'] ?? 0,
                'reach' => $metrics['reach'] ?? 0,
                'frequency' => $metrics['frequency'] ?? null,
                'clicks' => $metrics['clicks'] ?? 0,
                'ctr' => $metrics['ctr'] ?? null,
                'cpc' => $metrics['cpc'] ?? null,
                'cpm' => $metrics['cpm'] ?? null,
                'spend' => $spend,
                'conversions' => $conversions,
                'cpa' => $cpa,
                'purchase_roas' => json_encode($metrics['purchase_roas'] ?? []),
                'add_to_cart' => $this->extractActionValue($actions, 'offsite_conversion.fb_pixel_add_to_cart'),
                'initiate_checkout' => $this->extractActionValue($actions, 'offsite_conversion.fb_pixel_initiate_checkout'),
                'view_content' => $this->extractActionValue($actions, 'offsite_conversion.fb_pixel_view_content'),
            ]);
        }
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
