<?php

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use App\Mail\FacebookAdInsightsEmail;
use App\Models\FacebookAdStat;

class EmailFacebookAdInsights extends Command
{
    protected $signature = 'email:facebook-ad-insights';
    protected $description = 'Email Top 10 Ads: Current 3 days vs Previous 3 days comparison';

    public function handle()
    {
        try {
            $interval = 'daily';

            // Step 1: Get last 6 unique dates (ascending)
            $last6Dates = FacebookAdStat::where('interval', $interval)
                ->where('status', 'active')
                ->orderByDesc('start_date')
                ->pluck('start_date')
                ->unique()
                ->take(6)
                ->sort()
                ->values();

            if ($last6Dates->count() < 6) {
                Log::warning('Insufficient data for comparison. Found dates:', $last6Dates->toArray());
                return;
            }

            $previousDates = $last6Dates->slice(0, 3)->values(); // first 3
            $currentDates = $last6Dates->slice(3, 3)->values();  // last 3

            Log::info('Email Insights - Using these dates:', [
                'previous' => $previousDates->toArray(),
                'current' => $currentDates->toArray(),
            ]);

            // Step 2: Load only those 6 days
            $ads = FacebookAdStat::where('interval', $interval)
                ->where('status', 'active')
                ->whereIn('start_date', $last6Dates)
                ->get();

            Log::info('Loaded ad stats:', ['rows' => $ads->count()]);

            // Step 3: Group + process
            $grouped = $ads->groupBy('ad_id')->map(function ($group) use ($previousDates, $currentDates) {
                $first = $group->first();

                $previous3 = $group->filter(fn($row) => $previousDates->contains($row->start_date));
                $current3 = $group->filter(fn($row) => $currentDates->contains($row->start_date));

                if ($previous3->isEmpty() || $current3->isEmpty()) {
                    Log::debug("Skipping ad: {$first->ad_name} ({$first->ad_id})", [
                        'previous_count' => $previous3->count(),
                        'current_count' => $current3->count(),
                    ]);
                    return null;
                }

                $ad = new \stdClass();
                $ad->ad_id = $first->ad_id;
                $ad->ad_name = $first->ad_name;
                $ad->campaign_name = $first->campaign_name;
                $ad->adset_name = $first->adset_name;
                $ad->ad_account_name = $first->ad_account_name;

                // Daily breakdown
                $ad->previous = $previous3->map(fn($row) => [
                    'date' => $row->start_date->format('Y-m-d'),
                    'spend' => round($row->spend, 2),
                    'roas' => round($row->roas, 2),
                    'cpa' => round($row->cpa, 2),
                    'ctr' => round($row->ctr, 2),
                    'clicks' => $row->clicks,
                    'order_count' => $row->order_count,
                    'impressions' => $row->impressions,
                ])->values();

                $ad->current = $current3->map(fn($row) => [
                    'date' => $row->start_date->format('Y-m-d'),
                    'spend' => round($row->spend, 2),
                    'roas' => round($row->roas, 2),
                    'cpa' => round($row->cpa, 2),
                    'ctr' => round($row->ctr, 2),
                    'clicks' => $row->clicks,
                    'order_count' => $row->order_count,
                    'impressions' => $row->impressions,
                ])->values();

                // Totals & % differences
                $ad->spend_current_total = round($current3->sum('spend'), 2);
                $ad->spend_previous_total = round($previous3->sum('spend'), 2);
                $ad->roas_current_avg = round($current3->avg('roas'), 2);
                $ad->roas_previous_avg = round($previous3->avg('roas'), 2);
                $ad->cpa_current_avg = round($current3->avg('cpa'), 2);
                $ad->cpa_previous_avg = round($previous3->avg('cpa'), 2);

                $ad->spend_diff = $ad->spend_previous_total > 0
                    ? round(($ad->spend_current_total - $ad->spend_previous_total) / $ad->spend_previous_total * 100, 1)
                    : null;
                $ad->roas_diff = $ad->roas_previous_avg > 0
                    ? round(($ad->roas_current_avg - $ad->roas_previous_avg) / $ad->roas_previous_avg * 100, 1)
                    : null;
                $ad->cpa_diff = $ad->cpa_previous_avg > 0
                    ? round(($ad->cpa_current_avg - $ad->cpa_previous_avg) / $ad->cpa_previous_avg * 100, 1)
                    : null;

                return $ad;
            })->filter();

            // Step 4: Rank by spend & send
            $topAds = $grouped->sortByDesc('spend_current_total')->take(10)->values();

            Log::info('Top Ads selected:', [
                'count' => $topAds->count(),
                'ads' => $topAds->pluck('ad_name')->toArray(),
            ]);

            if ($topAds->isEmpty()) {
                $this->warn('No valid ads with complete data to send.');
                return;
            }

            Mail::to('razakkhanafridi1995@gmail.com')->send(new FacebookAdInsightsEmail(
                $topAds,
                $currentDates->first(),
                $currentDates->last()
            ));

            $this->info('Email sent with ad insights.');

        } catch (\Throwable $e) {
            Log::error('Failed to send ad insights', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Email job failed.');
        }
    }
}
