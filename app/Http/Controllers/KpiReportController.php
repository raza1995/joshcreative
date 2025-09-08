<?php

namespace App\Http\Controllers;

use App\Models\ShopifyOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KpiReportController extends Controller
{
    public function index(Request $request)
    {
        $from = (string) $request->get('from', now()->subMonth()->startOfMonth()->toDateString());
        $to = (string) $request->get('to', now()->subMonth()->endOfMonth()->toDateString());
        $ui = (bool) $request->boolean('ui', false);
        $channel = (string) $request->get('channel', '');
        $source = (string) $request->get('utm_source', '');
        $medium = (string) $request->get('utm_medium', '');
        $campaign = (string) $request->get('utm_campaign', '');

        $summary = null;
        $channels = collect();
        $sources = collect();
        $mediums = collect();
        $campaigns = collect();

        [$summary, $channels, $sources, $mediums, $campaigns] = $this->compute($from, $to, $ui, $channel, $source, $medium, $campaign);

        return view('analytics.kpi', compact('from','to','ui','channel','source','medium','campaign','summary','channels','sources','mediums','campaigns'));
    }

    public function data(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date',
        ]);

        [$summary, $channels, $sources, $mediums, $campaigns] = $this->compute(
            $request->from,
            $request->to,
            $request->boolean('ui', false),
            (string) $request->get('channel', ''),
            (string) $request->get('utm_source', ''),
            (string) $request->get('utm_medium', ''),
            (string) $request->get('utm_campaign', ''),
        );

        return response()->json([
            'from' => $request->from,
            'to' => $request->to,
            'ui' => (bool) $request->boolean('ui', false),
            'filters' => [
                'channel' => (string) $request->get('channel', ''),
                'utm_source' => (string) $request->get('utm_source', ''),
                'utm_medium' => (string) $request->get('utm_medium', ''),
                'utm_campaign' => (string) $request->get('utm_campaign', ''),
            ],
            'summary' => $summary,
            'channels' => $channels,
            'sources' => $sources,
            'mediums' => $mediums,
            'campaigns' => $campaigns,
        ]);
    }

    private function compute(
        string $from,
        string $to,
        bool $uiFilters = false,
        string $channel = '',
        string $source = '',
        string $medium = '',
        string $campaign = ''
    ): array
    {
        $fromDt = Carbon::parse($from)->startOfDay();
        $toDt = Carbon::parse($to)->endOfDay();

        $base = ShopifyOrder::query()->whereBetween('order_date', [$fromDt, $toDt]);
        if ($uiFilters) {
            $base->whereRaw("JSON_EXTRACT(raw_json, '$.cancelled_at') IS NULL")
                 ->whereRaw("(JSON_EXTRACT(raw_json, '$.test') IS NULL OR JSON_EXTRACT(raw_json, '$.test') = false)")
                 ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.financial_status')) = 'paid'");
        }

        // Optional precise filters
        if ($channel !== '')   $base->where('channel', $channel);
        if ($source !== '')    $base->where('utm_source', $source);
        if ($medium !== '')    $base->where('utm_medium', $medium);
        if ($campaign !== '')  $base->where('utm_campaign', $campaign);

        // Distinct orders with paid_amount for aggregation
        $ordersSub = (clone $base)
            ->selectRaw('order_number, channel, utm_source, utm_medium, utm_campaign, email_address, MAX(paid_amount) as paid_amount')
            ->groupBy('order_number','channel','utm_source','utm_medium','utm_campaign','email_address');

        // Summary across all channels
        $summary = DB::query()->fromSub($ordersSub, 'o')
            ->selectRaw('COUNT(*) as orders, COUNT(DISTINCT email_address) as customers, SUM(paid_amount) as revenue, AVG(paid_amount) as aov')
            ->first();

        // New vs Returning customers within filtered set
        $emailsInSet = DB::query()->fromSub($ordersSub, 'o')
            ->select('email_address')
            ->distinct()
            ->pluck('email_address');

        $firsts = collect();
        $newCustomers = 0; $returningCustomers = 0;
        if ($emailsInSet->count() > 0) {
            $firsts = ShopifyOrder::query()
                ->selectRaw('email_address, MIN(order_date) as first_date')
                ->whereIn('email_address', $emailsInSet)
                ->groupBy('email_address')
                ->get()
                ->keyBy('email_address');

            foreach ($emailsInSet as $email) {
                $first = optional($firsts->get($email))->first_date;
                if ($first && $first >= $fromDt && $first <= $toDt) {
                    $newCustomers++;
                } else {
                    $returningCustomers++;
                }
            }
        }

        // By channel
        $channels = DB::query()->fromSub($ordersSub, 'o')
            ->selectRaw('COALESCE(channel, "(unknown)") as channel, COUNT(*) as orders, COUNT(DISTINCT email_address) as customers, SUM(paid_amount) as revenue, AVG(paid_amount) as aov')
            ->groupBy('channel')
            ->orderByDesc('revenue')
            ->get();

        // Enhance channels with conversion_rate (orders/customer) and new customer rate
        $channelEmails = DB::query()->fromSub($ordersSub, 'o')
            ->select('channel','email_address')
            ->distinct()
            ->get();

        $emailsByChannel = [];
        foreach ($channelEmails as $row) {
            $ch = $row->channel ?? '(unknown)';
            if (!isset($emailsByChannel[$ch])) $emailsByChannel[$ch] = [];
            $emailsByChannel[$ch][$row->email_address] = true;
        }

        $bestChannel = null; $bestRate = 0.0;
        foreach ($channels as $ch) {
            $customers = max(1, (int) $ch->customers);
            $ch->conversion_rate = round(((int) $ch->orders) / $customers, 4); // orders per customer
            // compute new customers within this channel using firsts map
            $newInChannel = 0; $totalEmails = 0;
            $list = $emailsByChannel[$ch->channel] ?? [];
            foreach ($list as $email => $_) {
                $totalEmails++;
                $first = optional($firsts->get($email))->first_date;
                if ($first && $first >= $fromDt && $first <= $toDt) {
                    $newInChannel++;
                }
            }
            $ch->new_customers = $newInChannel;
            $ch->new_rate = $totalEmails > 0 ? round(($newInChannel / $totalEmails) * 100, 2) : 0.0;
            $ch->returning_customers = max(0, $totalEmails - $newInChannel);
            $ch->returning_rate = $totalEmails > 0 ? round((($totalEmails - $newInChannel) / $totalEmails) * 100, 2) : 0.0;

            if ($ch->conversion_rate > $bestRate) {
                $bestRate = $ch->conversion_rate;
                $bestChannel = $ch->channel;
            }
        }

        // Determine worst (min) conversion rate and label channels as Best/Good/Worst
        $minRate = null;
        foreach ($channels as $ch) {
            if ($minRate === null || $ch->conversion_rate < $minRate) {
                $minRate = $ch->conversion_rate;
            }
        }
        foreach ($channels as $ch) {
            if ($ch->conversion_rate === $bestRate) {
                $ch->rank_label = 'Best';
            } elseif ($ch->conversion_rate === $minRate) {
                $ch->rank_label = 'Worst';
            } else {
                $ch->rank_label = 'Good';
            }
        }

        // By source
        $sources = DB::query()->fromSub($ordersSub, 'o')
            ->selectRaw('COALESCE(utm_source, "(unknown)") as utm_source, COUNT(*) as orders, COUNT(DISTINCT email_address) as customers, SUM(paid_amount) as revenue, AVG(paid_amount) as aov')
            ->groupBy('utm_source')
            ->orderByDesc('revenue')
            ->get();

        // By medium
        $mediums = DB::query()->fromSub($ordersSub, 'o')
            ->selectRaw('COALESCE(utm_medium, "(unknown)") as utm_medium, COUNT(*) as orders, COUNT(DISTINCT email_address) as customers, SUM(paid_amount) as revenue, AVG(paid_amount) as aov')
            ->groupBy('utm_medium')
            ->orderByDesc('revenue')
            ->get();

        // By campaign
        $campaigns = DB::query()->fromSub($ordersSub, 'o')
            ->selectRaw('COALESCE(utm_campaign, "(unknown)") as utm_campaign, COUNT(*) as orders, COUNT(DISTINCT email_address) as customers, SUM(paid_amount) as revenue, AVG(paid_amount) as aov')
            ->groupBy('utm_campaign')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get();

        return [
            [
                'orders' => (int) ($summary->orders ?? 0),
                'customers' => (int) ($summary->customers ?? 0),
                'revenue' => (float) ($summary->revenue ?? 0),
                'aov' => (float) ($summary->aov ?? 0),
                'new_customers' => (int) $newCustomers,
                'returning_customers' => (int) $returningCustomers,
                'new_pct' => ($summary->customers ?? 0) > 0 ? round(($newCustomers / $summary->customers) * 100, 2) : 0.0,
                'returning_pct' => ($summary->customers ?? 0) > 0 ? round(($returningCustomers / $summary->customers) * 100, 2) : 0.0,
                'best_conversion_channel' => $bestChannel,
                'best_conversion_rate' => $bestRate,
            ],
            $channels,
            $sources,
            $mediums,
            $campaigns,
        ];
    }
}
