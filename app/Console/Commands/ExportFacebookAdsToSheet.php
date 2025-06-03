<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ExportFacebookAdsToGoogleSheetJob;

class ExportFacebookAdsToSheet extends Command
{
    protected $signature = 'facebook:export {interval=daily : Interval type (daily|weekly|monthly)}';
    protected $description = 'Manually export Facebook Ads data to Google Sheets by interval.';

    public function handle()
    {
        $interval = $this->argument('interval');

        if (!in_array($interval, ['daily', 'weekly', 'monthly'])) {
            $this->error("Invalid interval. Use one of: daily, weekly, monthly.");
            return 1;
        }

        dispatch(new ExportFacebookAdsToGoogleSheetJob($interval));

        $this->info("📤 Export job for '{$interval}' dispatched successfully.");
        return 0;
    }
}
