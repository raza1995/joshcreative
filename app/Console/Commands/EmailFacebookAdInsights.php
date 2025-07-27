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
    protected $description = 'Email Jakob top 10 highest spend ads insights over last 3 days vs previous 3 days';

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

            $grouped = $ads->groupBy('ad_id')->map(function ($group) use ($currentStart, $now, $previousStart, $previousEnd) {
                $current = $group->whereBetween('start_date', [$currentStart, $now]);
                $previous = $group->whereBetween('start_date', [$previousStart, $previousEnd]);

                $first = $group->first();
                $ad = new \stdClass();
                $ad->ad_id = $first->ad_id;
                $ad->ad_name = $first->ad_name;
                $ad->campaign_name = $first->campaign_name;
                $ad->spend_current = $current->sum('spend');
                $ad->roas_current = round($current->avg('roas'), 2);
                $ad->spend_previous = $previous->sum('spend');
                $ad->roas_previous = round($previous->avg('roas'), 2);

                $ad->spend_diff = $ad->spend_previous > 0
                    ? round(($ad->spend_current - $ad->spend_previous) / $ad->spend_previous * 100, 1)
                    : null;

                $ad->roas_diff = $ad->roas_previous > 0
                    ? round(($ad->roas_current - $ad->roas_previous) / $ad->roas_previous * 100, 1)
                    : null;

                return $ad;
            });

            $topAds = $grouped->sortByDesc('spend_current')->take(10)->values();

            Log::info('Top ads selected for email:', $topAds->toArray());

            Mail::to('razakkhanafridi@gmail.com')->send(new FacebookAdInsightsEmail($topAds, $currentStart, $now));

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
