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
    /** @var string */
    protected $signature = 'email:facebook-ad-insights
                            {--dry-run : Log everything but don’t send the email}';

    /** @var string */
    protected $description = 'Email the top‑spend Facebook ads — last 3 full days vs the 3 days prior.';

    public function handle(): void
    {
        try {
            //------------------------------------------------------------------
            // 1. Exact 3‑day windows (exclude today to avoid partial data)
            //------------------------------------------------------------------
            $today        = Carbon::now(config('app.timezone'))->startOfDay(); // e.g. 2025‑07‑28 00:00
            $currentEnd   = $today->copy()->subDay();          // 2025‑07‑27
            $currentStart = $currentEnd->copy()->subDays(2);   // 2025‑07‑25

            $previousEnd   = $currentStart->copy()->subDay();  // 2025‑07‑24
            $previousStart = $previousEnd->copy()->subDays(2); // 2025‑07‑22

            Log::info('FacebookAdInsights windows', [
                'previous' => [$previousStart->toDateString(), $previousEnd->toDateString()],
                'current'  => [$currentStart->toDateString(),  $currentEnd->toDateString()],
            ]);

            //------------------------------------------------------------------
            // 2. Pull the six days of stats in one query
            //------------------------------------------------------------------
            $ads = FacebookAdStat::query()
                ->where('status',  'active')
                ->where('interval','daily')
                ->whereBetween('start_date', [$previousStart, $currentEnd])
                ->get();

            Log::info('Fetched ad rows', ['count' => $ads->count()]);

            //------------------------------------------------------------------
            // 3. Aggregate per ad_id
            //------------------------------------------------------------------
            $grouped = $ads->groupBy('ad_id')->map(function ($rows) use (
                $previousStart, $previousEnd, $currentStart, $currentEnd
            ) {
                $current  = $rows->whereBetween('start_date', [$currentStart,  $currentEnd]);
                if ($current->isEmpty()) {
                    return null;                         // skip ads with no recent data
                }
                $previous = $rows->whereBetween('start_date', [$previousStart, $previousEnd]);

                $base = $rows->first();                 // meta data

                $out = (object) [
                    'ad_id'           => $base->ad_id,
                    'ad_name'         => $base->ad_name,
                    'campaign_name'   => $base->campaign_name,
                    'adset_name'      => $base->adset_name,
                    'ad_account_name' => $base->ad_account_name,
                    'ad_link'         => $base->ad_link,
                    'thumbnail_url'   => $base->thumbnail_url,

                    // current 3‑day window
                    'spend_current' => round($current->sum('spend'), 2),
                    'roas_current'  => round($current->avg('roas'), 2),
                    'cpa_current'   => round($current->avg('cpa'), 2),
                    'ctr_current'   => round($current->avg('ctr'), 2),

                    // previous 3‑day window
                    'spend_previous' => round($previous->sum('spend'), 2),
                    'roas_previous'  => round($previous->avg('roas'), 2),
                    'cpa_previous'   => round($previous->avg('cpa'), 2),
                    'ctr_previous'   => round($previous->avg('ctr'), 2),
                ];

                // % deltas (guard against ÷0)
                $out->spend_diff = $out->spend_previous > 0
                    ? round(($out->spend_current - $out->spend_previous) / $out->spend_previous * 100, 1)
                    : null;
                $out->roas_diff  = $out->roas_previous  > 0
                    ? round(($out->roas_current  - $out->roas_previous ) / $out->roas_previous  * 100, 1)
                    : null;
                $out->cpa_diff   = $out->cpa_previous   > 0
                    ? round(($out->cpa_current   - $out->cpa_previous  ) / $out->cpa_previous   * 100, 1)
                    : null;
                $out->ctr_diff   = $out->ctr_previous   > 0
                    ? round(($out->ctr_current   - $out->ctr_previous  ) / $out->ctr_previous   * 100, 1)
                    : null;

                // raw rows for optional drill‑down in the email blade
                $format = fn ($r) => [
                    'date'  => Carbon::parse($r->start_date)->toDateString(),
                    'spend' => round($r->spend, 2),
                    'roas'  => round($r->roas , 2),
                    'cpa'   => round($r->cpa  , 2),
                    'ctr'   => round($r->ctr  , 2),
                ];
                $out->current_rows  = $current ->values()->map($format);
                $out->previous_rows = $previous->values()->map($format);

                return $out;
            })->filter();  // drop nulls

            //------------------------------------------------------------------
            // 4. Pick the top 20 by spend
            //------------------------------------------------------------------
            $topAds = $grouped->sortByDesc('spend_current')->take(20)->values();

            Log::info('Top ads selected', [
                'count' => $topAds->count(),
                'ids'   => $topAds->pluck('ad_id'),
            ]);

            //------------------------------------------------------------------
            // 5. Send (or just log) the email
            //------------------------------------------------------------------
            if ($topAds->isEmpty()) {
                $this->warn('No data for the current 3‑day window; email not sent.');
                return;
            }

            if ($this->option('dry-run')) {
                $this->info('[DRY‑RUN] Email skipped; data logged.');
                Log::info('[DRY‑RUN] Email would have been sent.', ['ads' => $topAds]);
                return;
            }

            Mail::to(config('mail.insights_to', 'razakkhanafridi1995@gmail.com'))
                ->send(new FacebookAdInsightsEmail($topAds, $currentStart, $currentEnd));

            $this->info('Ad‑insights email sent successfully.');
            Log::info('EmailFacebookAdInsights completed.');

        } catch (\Throwable $e) {
            Log::error('EmailFacebookAdInsights failed', [
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('Failed: ' . $e->getMessage());
        }
    }
}
