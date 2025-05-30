<?php


namespace App\Jobs;

use App\Models\FacebookAd;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\GoogleSheetService;
use Illuminate\Support\Facades\Log;

class ExportFacebookAdsToGoogleSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $interval;

    public function __construct(string $interval = 'daily')
    {
        $this->interval = $interval;
    }

    public function handle(GoogleSheetService $sheetService)
    {
        $dateKey = match ($this->interval) {
            'daily' => now()->subDay()->toDateString(),
            'weekly' => now()->startOfWeek()->toDateString(),
            'monthly' => now()->startOfMonth()->toDateString(),
        };

        $ads = FacebookAd::with(['metrics' => function ($q) {
            $q->where('interval', $this->interval)
              ->where('date_key', now()->subDay()->toDateString()); // or relevant interval
        }])->get();

        $rows = [];

        foreach ($ads as $ad) {
            $metric = $ad->metrics->first();

            $rows[] = [
                'ad_account_name' => $ad->ad_account_name,
                'ad_id' => $ad->ad_id,
                'campaign_id' => $ad->campaign_id,
                'adset_id' => $ad->adset_id,
                'ad_link' => $ad->ad_link,
                'link_url' => $ad->link_url,
                'thumbnail_url' => $ad->thumbnail_url,
                'video_url' => $ad->video_url,
                'full_picture' => $ad->full_picture,
                'title' => $ad->title,
                'body' => $ad->body,
                'description' => $ad->description,
                'call_to_action' => $ad->call_to_action,
                'account_name' => $ad->ad_account_name,
                'campaign_name' => $ad->campaign_name,
                'adset_name' => $ad->adset_name,
                'ad_name' => $ad->ad_name,
                'ad_type' => $ad->ad_type,
                'impressions' => $metric->impressions ?? 0,
                'cpc' => $metric->cpc ?? null,
                'cpm' => $metric->cpm ?? null,
                'spend' => $metric->spend ?? 0,
                'clicks' => $metric->clicks ?? 0,
                'ctr' => $metric->ctr ?? 0,
                'cpa' => $metric->cpa ?? null,
                'conversions' => $metric->conversions ?? 0,
                'add_to_cart' => $metric->add_to_cart ?? 0,
                'initiate_checkout' => $metric->initiate_checkout ?? 0,
                'view_content' => $metric->view_content ?? 0,
                'roas' => $metric->purchase_roas ?? null,
                'status' => $ad->status,
                'updated_time' => $ad->updated_time,
                'data_interval' => $metric->interval,
                'interval' => $this->interval,
            ];
        }

        $sheetTitle = "FB Ads - {$this->interval} - " . now()->toDateString();
        $sheetName = now()->format('Y_m_d');

       
        if ($this->interval === 'daily') {
            $spreadsheetId = config('sheets.daily_spreadsheet_id');
            $sheetService->appendDataToNewSheet($spreadsheetId, $rows, $sheetName);
        } elseif ($this->interval === 'weekly') {
            $spreadsheetTitle = "FB Weekly " . now()->startOfWeek()->format('Y-m-d');
            $spreadsheetUrl = $sheetService->createSheetFromArray($spreadsheetTitle, $rows);
            Log::info("✅ Weekly Sheet Created: $spreadsheetUrl");
        } else {
            $spreadsheetTitle = "FB Monthly " . now()->startOfMonth()->format('F Y');
            $spreadsheetUrl = $sheetService->createSheetFromArray($spreadsheetTitle, $rows);
            Log::info("✅ Monthly Sheet Created: $spreadsheetUrl");
        }
    }
}
