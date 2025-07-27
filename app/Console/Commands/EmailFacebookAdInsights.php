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
            ->whereDate('start_date', '>=', $now->copy()->subDays(6)->startOfDay())
            ->whereDate('start_date', '<=', $now)
            ->get();
        
        $grouped = $ads->groupBy('ad_id')->map(function ($group) use ($now) {
            $first = $group->first();
            $ad = new \stdClass();
            $ad->ad_id = $first->ad_id;
            $ad->ad_name = $first->ad_name;
            $ad->campaign_name = $first->campaign_name;
        
            // Sort by date
            $sorted = $group->sortBy('start_date')->values();
        
            // Slice the last 3 days and the 3 before that
            $previous3 = $sorted->slice(-6, 3);
            $current3 = $sorted->slice(-3, 3);
        
            $ad->previous = $previous3->map(function ($row) {
                return [
                    'date' => $row->start_date->toDateString(),
                    'spend' => $row->spend,
                    'roas' => round($row->roas, 2),
                ];
            });
        
            $ad->current = $current3->map(function ($row) {
                return [
                    'date' => $row->start_date->toDateString(),
                    'spend' => $row->spend,
                    'roas' => round($row->roas, 2),
                ];
            });
        
            $ad->spend_current_total = $current3->sum('spend');
            $ad->spend_previous_total = $previous3->sum('spend');
            $ad->roas_current_avg = round($current3->avg('roas'), 2);
            $ad->roas_previous_avg = round($previous3->avg('roas'), 2);
        
            $ad->spend_diff = $ad->spend_previous_total > 0
                ? round(($ad->spend_current_total - $ad->spend_previous_total) / $ad->spend_previous_total * 100, 1)
                : null;
        
            $ad->roas_diff = $ad->roas_previous_avg > 0
                ? round(($ad->roas_current_avg - $ad->roas_previous_avg) / $ad->roas_previous_avg * 100, 1)
                : null;
        
            return $ad;
        });
        $ads = FacebookAdStat::query()
        ->where('status', 'active')
        ->where('interval', 'daily')
        ->whereDate('start_date', '>=', $now->copy()->subDays(6)->startOfDay())
        ->whereDate('start_date', '<=', $now)
        ->get();
    
    $grouped = $ads->groupBy('ad_id')->map(function ($group) use ($now) {
        $first = $group->first();
        $ad = new \stdClass();
        $ad->ad_id = $first->ad_id;
        $ad->ad_name = $first->ad_name;
        $ad->campaign_name = $first->campaign_name;
    
        // Sort by date
        $sorted = $group->sortBy('start_date')->values();
    
        // Slice the last 3 days and the 3 before that
        $previous3 = $sorted->slice(-6, 3);
        $current3 = $sorted->slice(-3, 3);
    
        $ad->previous = $previous3->map(function ($row) {
            return [
                'date' => $row->start_date->toDateString(),
                'spend' => $row->spend,
                'roas' => round($row->roas, 2),
            ];
        });
    
        $ad->current = $current3->map(function ($row) {
            return [
                'date' => $row->start_date->toDateString(),
                'spend' => $row->spend,
                'roas' => round($row->roas, 2),
            ];
        });
    
        $ad->spend_current_total = $current3->sum('spend');
        $ad->spend_previous_total = $previous3->sum('spend');
        $ad->roas_current_avg = round($current3->avg('roas'), 2);
        $ad->roas_previous_avg = round($previous3->avg('roas'), 2);
    
        $ad->spend_diff = $ad->spend_previous_total > 0
            ? round(($ad->spend_current_total - $ad->spend_previous_total) / $ad->spend_previous_total * 100, 1)
            : null;
    
        $ad->roas_diff = $ad->roas_previous_avg > 0
            ? round(($ad->roas_current_avg - $ad->roas_previous_avg) / $ad->roas_previous_avg * 100, 1)
            : null;
    
        return $ad;
    });
            
            $topAds = $grouped->sortByDesc('spend_current')->take(10)->values();

            Log::info('Top ads selected for email:', $topAds->toArray());

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
