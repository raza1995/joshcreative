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
            
                // ✅ Add this block to support Blade breakdown rows:
                $ad->previous = $previous->map(function ($row) {
                    return [
                        'date' => Carbon::parse($row->start_date)->toDateString(),
                        'spend' => round($row->spend, 2),
                        'roas' => round($row->roas, 2),
                        'cpa' => round($row->cpa, 2),
                        'ctr' => round($row->ctr, 2),
                    ];
                })->values();
            
                $ad->current = $current->map(function ($row) {
                    return [
                        'date' => Carbon::parse($row->start_date)->toDateString(),
                        'spend' => round($row->spend, 2),
                        'roas' => round($row->roas, 2),
                        'cpa' => round($row->cpa, 2),
                        'ctr' => round($row->ctr, 2),
                    ];
                })->values();
            
                return $ad;
            });
            

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
