<?php

namespace App\Jobs;

use App\Services\FacebookAdsService;
use App\Services\FacebookAdFormatter;
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

    public function handle(FacebookAdsService $fb, FacebookAdFormatter $formatter)
    {
        Log::info("📝 Fetching Facebook campaign (manual): {$this->campaignName}");
        Cache::put('fb_ads_job_status', 'processing', now()->addMinutes(30));

        $output = [];
        $adSets = $fb->getAdSets($this->campaignId);

        foreach ($adSets as $adSet) {
            $ads = $fb->getAds($adSet['id'], $this->startDate, $this->endDate);
            foreach ($ads as $ad) {
                $formatted = $formatter->formatAd(
                    $this->campaignId, $this->campaignName, $adSet, $ad, $this->startDate, $this->endDate
                );
                if ($formatted) {
                    $output[] = $formatted;
                }
            }
        }

        $filename = 'fb_ads/' . $this->campaignId . '_' . $this->campaignName . '.json';
        Storage::disk('public')->put($filename, json_encode($output, JSON_PRETTY_PRINT));

        Log::info("✅ JSON saved to: {$filename}");
        Cache::put('fb_ads_job_status', 'completed', now()->addMinutes(30));

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

        $mergedPath = 'fb_ads_merged/fb_ads_all.json';
        Storage::disk('public')->put($mergedPath, json_encode($merged, JSON_PRETTY_PRINT));
        Log::info("📦 All JSON merged to: {$mergedPath}");
    }
}
