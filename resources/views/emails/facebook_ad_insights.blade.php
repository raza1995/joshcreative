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
    /**  
     * --days=n  -> size of each window (default 30)  
     * --dry-run -> skip sending the e‑mail, just log  
     */
    protected $signature = 'email:facebook-ad-insights
                            {--days=30 : Window size in days}
                            {--dry-run  : Log only, don’t mail}';

    protected $description = 'Email top‑spend Facebook ads — current window vs previous window.';

    public function handle(): void
    {
        try {
            // ------------------------------------------------------------------
            // 1. Window boundaries (exclude today to avoid partial data)
            // ------------------------------------------------------------------
            $days         = max(1, (int) $this->option('days'));      // safety clamp
            $today        = Carbon::now(config('app.timezone'))->startOfDay();
            $currentEnd   = $today->copy()->subDay();                 // yesterday
            $currentStart = $currentEnd->copy()->subDays($days - 1);  // inclusive

            $previousEnd   = $currentStart->copy()->subDay();
            $previousStart = $previousEnd->copy()->subDays($days - 1);

            Log::info('FacebookAdInsights windows', [
                'days'     => $days,
                'previous' => [$previousStart->toDateString(), $previousEnd->toDateString()],
                'current'  => [$currentStart->toDateString(),  $currentEnd->toDateString()],
            ]);

            // ------------------------------------------------------------------
            // 2. Pull stats covering BOTH windows
            // ------------------------------------------------------------------
            $ads = FacebookAdStat::query()
                ->where('status',  'active')
                ->where('interval','daily')
                ->whereBetween('start_date', [$previousStart, $currentEnd])
                ->get();

            // ------------------------------------------------------------------
            // 3. Aggregate per ad_id
            // ------------------------------------------------------------------
            $grouped = $ads->groupBy('ad_id')->map(function ($rows) use (
                $previousStart, $previousEnd, $currentStart, $currentEnd
            ) {
                $current  = $rows->whereBetween('start_date', [$currentStart,  $currentEnd]);
                if ($current->isEmpty()) {
                    return null;                   // skip ads with no data in latest window
                }
                $previous = $rows->whereBetween('start_date', [$previousStart, $previousEnd]);

                $base = $rows->first();

                $out = (object) [
                    // ---------- meta ----------
                    'ad_id'           => $base->ad_id,
                    'ad_name'         => $base->ad_name,
                    'campaign_name'   => $base->campaign_name,
                    'adset_name'      => $base->adset_name,
                    'ad_account_name' => $base->ad_account_name,
                    'ad_link'         => $base->ad_link,
                    'thumbnail_url'   => $base->thumbnail_url,

                    // ---------- aggregates ----------
                    'spend_current' => round($current->sum('spend'), 2),
                    'roas_current'  => round($current->avg('roas'), 2),
                    'cpa_current'   => round($current->avg('cpa'), 2),
                    'ctr_current'   => round($current->avg('ctr'), 2),

                    'spend_previous' => round($previous->sum('spend'), 2),
                    'roas_previous'  => round($previous->avg('roas'), 2),
                    'cpa_previous'   => round($previous->avg('cpa'), 2),
                    'ctr_previous'   => round($previous->avg('ctr'), 2),
                ];

                // ---------- percentage deltas ----------
                foreach (['spend','roas','cpa','ctr'] as $metric) {
                    $prev = $out->{$metric.'_previous'};
                    $curr = $out->{$metric.'_current'};
                    $out->{$metric.'_diff'} = $prev > 0
                        ? round(($curr - $prev) / $prev * 100, 1)
                        : null;
                }

                // ---------- raw rows for drill‑down ----------
                $fmt = fn ($r) => [
                    'date'  => Carbon::parse($r->start_date)->toDateString(),
                    'spend' => round($r->spend, 2),
                    'roas'  => round($r->roas , 2),
                    'cpa'   => round($r->cpa  , 2),
                    'ctr'   => round($r->ctr  , 2),
                ];
                $out->current_rows  = $current ->values()->map($fmt);
                $out->previous_rows = $previous->values()->map($fmt);

                return $out;
            })->filter();

            // ------------------------------------------------------------------
            // 4. Top 20 by spend
            // ------------------------------------------------------------------
            $topAds = $grouped->sortByDesc('spend_current')->take(20)->values();

            if ($topAds->isEmpty()) {
                $this->warn('No data for current window; email not sent.');
                return;
            }

            // ------------------------------------------------------------------
            // 5. Send (or skip) the e‑mail
            // ------------------------------------------------------------------
            if ($this->option('dry-run')) {
                $this->info('[DRY‑RUN] Skipped sending email, logged data instead.');
                Log::info('[DRY‑RUN] Email payload', ['ads' => $topAds]);
                return;
            }

            Mail::to(config('mail.insights_to', 'razakkhanafridi1995@gmail.com'))
                ->send(new FacebookAdInsightsEmail($topAds, $currentStart, $currentEnd, $days));

            $this->info("Ad‑insights email sent (window: {$days} days).");

        } catch (\Throwable $e) {
            Log::error('EmailFacebookAdInsights failed', [
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Failed: '.$e->getMessage());
        }
    }
}
