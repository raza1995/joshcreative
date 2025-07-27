<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use App\Mail\FacebookAdInsightsEmail;
use App\Models\FacebookAdStat;

class EmailFacebookAdInsights extends Command
{
    protected $signature = 'email:facebook-ad-insights';
    protected $description = 'Email Jakob top 10 highest spend ads insights using last 3 valid days vs previous 3 with daily breakdown';

    public function handle()
    {
        try {
            // STEP 1: Get last 6 real days with interval=daily
            $validDates = FacebookAdStat::query()
                ->where('status', 'active')
                ->where('interval', 'daily')
                ->orderByDesc('start_date')
                ->distinct()
                ->pluck('start_date')
                ->unique()
                ->take(6)
                ->sort()
                ->values();

            if ($validDates->count() < 6) {
                Log::warning('Not enough valid days (need 6), got:', $validDates->toArray());
                $this->warn('Not enough data to run insights.');
                return;
            }

            $previousDates = $validDates->slice(0, 3)->values();
            $currentDates = $validDates->slice(3, 3)->values();

            Log::info('Running insights using real data dates:', [
                'previousDates' => $previousDates->toArray(),
                'currentDates' => $currentDates->toArray(),
            ]);

            // STEP 2: Load all ad data from those 6 days
            $ads = FacebookAdStat::query()
                ->where('status', 'active')
                ->where('interval', 'daily')
                ->whereIn('start_date', $validDates)
                ->get();

            Log::info('Fetched ads count: ' . $ads->count());

            // STEP 3: Group by ad_id and calculate metrics
            $grouped = $ads->groupBy('ad_id')->map(function ($group) use ($previousDates, $currentDates) {
                $first = $group->first();

                $previous3 = $group->whereIn('start_date', $previousDates);
                $current3 = $group->whereIn('start_date', $currentDates);

                if ($previous3->isEmpty() || $current3->isEmpty()) {
                    Log::debug('Skipping ad due to missing data:', [
                        'ad_id' => $first->ad_id,
                        'ad_name' => $first->ad_name,
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

                $ad->previous = $previous3->map(function ($row) {
                    return [
                        'date' => Carbon::parse($row->start_date)->toDateString(),
                        'spend' => round($row->spend, 2),
                        'roas' => round($row->roas, 2),
                        'cpa' => round($row->cpa, 2),
                        'ctr' => round($row->ctr, 2),
                        'clicks' => $row->clicks,
                        'order_count' => $row->order_count,
                        'impressions' => $row->impressions,
                    ];
                })->values();

                $ad->current = $current3->map(function ($row) {
                    return [
                        'date' => Carbon::parse($row->start_date)->toDateString(),
                        'spend' => round($row->spend, 2),
                        'roas' => round($row->roas, 2),
                        'cpa' => round($row->cpa, 2),
                        'ctr' => round($row->ctr, 2),
                        'clicks' => $row->clicks,
                        'order_count' => $row->order_count,
                        'impressions' => $row->impressions,
                    ];
                })->values();

                $ad->spend_previous_total = round($previous3->sum('spend'), 2);
                $ad->spend_current_total = round($current3->sum('spend'), 2);
                $ad->roas_previous_avg = round($previous3->avg('roas'), 2);
                $ad->roas_current_avg = round($current3->avg('roas'), 2);
                $ad->cpa_previous_avg = round($previous3->avg('cpa'), 2);
                $ad->cpa_current_avg = round($current3->avg('cpa'), 2);
                $ad->ctr_previous_avg = round($previous3->avg('ctr'), 2);
                $ad->ctr_current_avg = round($current3->avg('ctr'), 2);
                $ad->clicks_previous_total = $previous3->sum('clicks');
                $ad->clicks_current_total = $current3->sum('clicks');
                $ad->orders_previous_total = $previous3->sum('order_count');
                $ad->orders_current_total = $current3->sum('order_count');
                $ad->impressions_previous_total = $previous3->sum('impressions');
                $ad->impressions_current_total = $current3->sum('impressions');

                // % Change calculations
                $ad->spend_diff = $ad->spend_previous_total > 0
                    ? round(($ad->spend_current_total - $ad->spend_previous_total) / $ad->spend_previous_total * 100, 1)
                    : null;

                $ad->roas_diff = $ad->roas_previous_avg > 0
                    ? round(($ad->roas_current_avg - $ad->roas_previous_avg) / $ad->roas_previous_avg * 100, 1)
                    : null;

                $ad->cpa_diff = $ad->cpa_previous_avg > 0
                    ? round(($ad->cpa_current_avg - $ad->cpa_previous_avg) / $ad->cpa_previous_avg * 100, 1)
                    : null;

                $ad->ctr_diff = $ad->ctr_previous_avg > 0
                    ? round(($ad->ctr_current_avg - $ad->ctr_previous_avg) / $ad->ctr_previous_avg * 100, 1)
                    : null;

                $ad->clicks_diff = $ad->clicks_previous_total > 0
                    ? round(($ad->clicks_current_total - $ad->clicks_previous_total) / $ad->clicks_previous_total * 100, 1)
                    : null;

                $ad->orders_diff = $ad->orders_previous_total > 0
                    ? round(($ad->orders_current_total - $ad->orders_previous_total) / $ad->orders_previous_total * 100, 1)
                    : null;

                return $ad;
            })->filter(); // remove nulls

            $topAds = $grouped->sortByDesc('spend_current_total')->take(10)->values();

            Log::info('Top ads selected for email.', [
                'count' => $topAds->count(),
                'top_spenders' => $topAds->pluck('ad_name')->toArray(),
            ]);

            Mail::to('razakkhanafridi1995@gmail.com')->send(new FacebookAdInsightsEmail(
                $topAds,
                $currentDates->first(),
                $currentDates->last()
            ));

            $this->info('Email sent with ad insights.');
            Log::info('Email sent with ad insights.');

        } catch (\Exception $e) {
            Log::error('Error in email:facebook-ad-insights job', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Failed to send ad insights email: ' . $e->getMessage());
        }
    }
}
