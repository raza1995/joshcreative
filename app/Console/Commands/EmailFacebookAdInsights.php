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
    protected $description = 'Email Jakob top 10 highest spend ads insights over last 3 days vs previous 3 days with daily breakdown';

    public function handle()
    {
        try {
            $now = Carbon::now();
            $currentStart = $now->copy()->subDays(3)->startOfDay();
            $previousStart = $now->copy()->subDays(6)->startOfDay();
            $previousEnd = $now->copy()->subDays(4)->endOfDay();

            Log::info('Starting FacebookAdInsights email job.', [
                'currentStart' => $currentStart->toDateTimeString(),
                'previousStart' => $previousStart->toDateTimeString(),
                'previousEnd' => $previousEnd->toDateTimeString(),
            ]);

            $ads = FacebookAdStat::query()
                ->where('status', 'active')
                ->where('interval', 'daily')
                ->whereDate('start_date', '>=', $previousStart)
                ->whereDate('start_date', '<=', $now)
                ->get();

            Log::info('Fetched ads count: ' . $ads->count());

            $grouped = $ads->groupBy('ad_id')->map(function ($group) use ($previousStart, $previousEnd, $currentStart, $now) {
                $first = $group->first();

                $previous3 = $group->filter(function ($row) use ($previousStart, $previousEnd) {
                    return Carbon::parse($row->start_date)->between($previousStart, $previousEnd);
                });

                $current3 = $group->filter(function ($row) use ($currentStart, $now) {
                    return Carbon::parse($row->start_date)->between($currentStart, $now);
                });

                // Skip if either side is empty
                if ($previous3->isEmpty() || $current3->isEmpty()) {
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

                // Aggregate comparisons
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

                // % Change
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

            Mail::to('razakkhanafridi1995@gmail.com')->send(new FacebookAdInsightsEmail($topAds, $currentStart, $now));

            $this->info('Email sent to Jakob with ad insights.');
            Log::info('Email sent to Jakob with ad insights.');

        } catch (\Exception $e) {
            Log::error('Error in email:facebook-ad-insights job', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Failed to send ad insights email: ' . $e->getMessage());
        }
    }
}
