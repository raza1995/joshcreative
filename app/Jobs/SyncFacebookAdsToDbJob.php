<?php

namespace App\Jobs;

use App\Models\FacebookAd;
use App\Models\FacebookAdMetric;
use App\Services\FacebookAdsService;
use App\Services\FacebookAdFormatter;
use App\Services\FacebookMetricsSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncFacebookAdsToDbJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaignId;
    protected $campaignName;
    protected $startDate;
    protected $endDate;
    protected $adAccountName;

    public function __construct($campaignId, $campaignName, $startDate, $endDate, $adAccountName = null)
    {
        $this->campaignId = $campaignId;
        $this->campaignName = $campaignName;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->adAccountName = $adAccountName;
    }

    public function handle(FacebookAdsService $fb, FacebookAdFormatter $formatter)
    {
        // Log::info("🔄 Syncing to DB: {$this->campaignName}");

        // $adSets = $fb->getAdSets($this->campaignId);

        // foreach ($adSets as $adSet) {
        //     $ads = $fb->getAds($adSet['id'], $this->startDate, $this->endDate);

        //     foreach ($ads as $ad) {
        //         $formatted = $formatter->formatAd(
        //             $this->campaignId, $this->campaignName, $adSet, $ad,
        //             $this->startDate, $this->endDate, $this->adAccountName
        //         );

        //         if ($formatted) {
        //             $adModel = FacebookAd::updateOrCreate(
        //                 ['ad_id' => $formatted['ad_id']],
        //                 $formatted
        //             );

        //             // Fetch and store time-based metrics
        //             $this->storeTimeBasedMetrics($fb, $adModel->id, $ad['id']);
        //         }
        //     }
        // }

        // Log::info("✅ DB sync complete for: {$this->campaignName}");

        app(FacebookMetricsSyncService::class)->syncCampaign(
            $this->campaignId,
            $this->campaignName,
            $this->startDate,
            $this->endDate,
            $this->adAccountName
        );
    }

    protected function storeTimeBasedMetrics(FacebookAdsService $fb, $facebookAdId, $adId)
    {
        $intervals = [
            'daily' => [
                'start' => now()->subDay()->toDateString(),
                'end' => now()->subDay()->toDateString(),
                'date_key' => now()->subDay()->toDateString(),
            ],
            'weekly' => [
                'start' => now()->subDays(7)->toDateString(),
                'end' => now()->toDateString(),
                'date_key' => now()->startOfWeek()->toDateString(),
            ],
            'monthly' => [
                'start' => now()->startOfMonth()->toDateString(),
                'end' => now()->toDateString(),
                'date_key' => now()->startOfMonth()->toDateString(),
            ],
        ];
    
        foreach ($intervals as $interval => $range) {
            $insights = $fb->getInsights($adId, $range['start'], $range['end'], 'ad');
 
            if (empty($insights)) {
                Log::info("No insights for {$interval} - {$adId}");
                continue;
            }
    
            $metrics = $insights; // assuming API returns an array of metrics
            $actions = $metrics['actions'] ?? [];
            $conversions = $this->extractActionValue($actions, 'offsite_conversion.fb_pixel_purchase');
            $spend = $metrics['spend'] ?? 0;
            $cpa = $conversions > 0 ? round($spend / $conversions, 2) : null;
            $addToCart = $this->extractActionValue($actions, 'offsite_conversion.fb_pixel_add_to_cart');
            $initiateCheckout = $this->extractActionValue($actions, 'offsite_conversion.fb_pixel_initiate_checkout');
            $viewContent = $this->extractActionValue($actions, 'offsite_conversion.fb_pixel_view_content');

        
            FacebookAdMetric::updateOrCreate([
                'ad_id' => $adId,
                'interval' => $interval,
                'date_key' => $range['date_key'],
            ], [
                'facebook_ad_id' => $facebookAdId,
                'impressions' => $metrics['impressions'] ?? 0,
                'clicks' => $metrics['clicks'] ?? 0,
                'ctr' => $metrics['ctr'] ?? null,
                'cpc' => $metrics['cpc'] ?? null,
                'cpm' => $metrics['cpm'] ?? null,
                'spend' => $metrics['spend'] ?? null,
                'conversions' => $conversions ?? 0,
                'cpa' => $cpa ?? null,
                'purchase_roas' => json_encode($metrics['purchase_roas'] ?? []),
                'add_to_cart' => $addToCart,
                'initiate_checkout' => $initiateCheckout,
                'view_content' => $viewContent,
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
