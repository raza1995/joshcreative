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
    protected $description = 'Email top 20 ad insights comparing last 3 days vs previous 3 days';

    public function handle()
    {
        try {
            $now = Carbon::now()->startOfDay();
            $currentStart = $now->copy()->subDays(3);
            $previousStart = $now->copy()->subDays(6);
            $previousEnd = $now->copy()->subDays(4);

            Log::info('FacebookAdInsights START', [
                'previous' => [$previousStart->toDateString(), $previousEnd->toDateString()],
                'current' => [$currentStart->toDateString(), $now->toDateString()],
            ]);

            $ads = FacebookAdStat::query()
                ->where('status', 'active')
                ->where('interval', 'daily')
                ->whereBetween('start_date', [$previousStart, $now])
                ->get();

            Log::info('Fetched ad rows: ' . $ads->count());

            $grouped = $ads->groupBy('ad_id')->map(function ($group) use ($previousStart, $previousEnd, $currentStart, $now) {
                $current = $group->whereBetween('start_date', [$currentStart, $now]);
                $previous = $group->whereBetween('start_date', [$previousStart, $previousEnd]);

                if ($current->isEmpty()) return null;

                $first = $group->first();
                $ad = new \stdClass();
                $ad->ad_id = $first->ad_id;
                $ad->ad_name = $first->ad_name;
                $ad->campaign_name = $first->campaign_name;
                $ad->adset_name = $first->adset_name ?? null;
                $ad->ad_account_name = $first->ad_account_name ?? null;
                $ad->ad_link = $first->ad_link ?? null;
                $ad->thumbnail_url = $first->thumbnail_url ?? null;

                $ad->spend_current = round($current->sum('spend'), 2);
                $ad->roas_current = round($current->avg('roas'), 2);
                $ad->cpa_current = round($current->avg('cpa'), 2);
                $ad->ctr_current = round($current->avg('ctr'), 2);

                $ad->spend_previous = round($previous->sum('spend'), 2);
                $ad->roas_previous = round($previous->avg('roas'), 2);
                $ad->cpa_previous = round($previous->avg('cpa'), 2);
                $ad->ctr_previous = round($previous->avg('ctr'), 2);

                $ad->spend_diff = $ad->spend_previous > 0
                    ? round(($ad->spend_current - $ad->spend_previous) / $ad->spend_previous * 100, 1)
                    : null;

                $ad->roas_diff = $ad->roas_previous > 0
                    ? round(($ad->roas_current - $ad->roas_previous) / $ad->roas_previous * 100, 1)
                    : null;

                $ad->cpa_diff = $ad->cpa_previous > 0
                    ? round(($ad->cpa_current - $ad->cpa_previous) / $ad->cpa_previous * 100, 1)
                    : null;

                $ad->ctr_diff = $ad->ctr_previous > 0
                    ? round(($ad->ctr_current - $ad->ctr_previous) / $ad->ctr_previous * 100, 1)
                    : null;

                $ad->current_dates = $current->pluck('start_date')->sort()->values()->toArray();
                $ad->previous_dates = $previous->pluck('start_date')->sort()->values()->toArray();

                return $ad;
            })->filter();

            $topAds = $grouped->sortByDesc('spend_current')->take(20)->values();

            Log::info('Selected Top 20 Ads', [
                'count' => $topAds->count(),
                'ids' => $topAds->pluck('ad_id')->toArray(),
            ]);

            Mail::to('razakkhanafridi1995@gmail.com')->send(
                new FacebookAdInsightsEmail($topAds, $currentStart, $now)
            );

            $this->info('Email sent with top ad insights.');
            Log::info('Email successfully sent.');

        } catch (\Exception $e) {
            Log::error('Error in email:facebook-ad-insights job', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Failed to send ad insights email: ' . $e->getMessage());
        }
    }
}
