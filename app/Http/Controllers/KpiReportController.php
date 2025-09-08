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

        $summary = null;
        $channels = collect();

        [$summary, $channels] = $this->compute($from, $to, $ui);

        return view('analytics.kpi', compact('from','to','ui','summary','channels'));
    }

    public function data(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date',
        ]);

        [$summary, $channels] = $this->compute($request->from, $request->to, $request->boolean('ui', false));

        return response()->json([
            'from' => $request->from,
            'to' => $request->to,
            'ui' => (bool) $request->boolean('ui', false),
            'summary' => $summary,
            'channels' => $channels,
        ]);
    }

    private function compute(string $from, string $to, bool $uiFilters = false): array
    {
        $fromDt = Carbon::parse($from)->startOfDay();
        $toDt = Carbon::parse($to)->endOfDay();

        $base = ShopifyOrder::query()->whereBetween('order_date', [$fromDt, $toDt]);
        if ($uiFilters) {
            $base->whereRaw("JSON_EXTRACT(raw_json, '$.cancelled_at') IS NULL")
                 ->whereRaw("(JSON_EXTRACT(raw_json, '$.test') IS NULL OR JSON_EXTRACT(raw_json, '$.test') = false)")
                 ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.financial_status')) = 'paid'");
        }

        // Distinct orders with paid_amount for aggregation
        $ordersSub = (clone $base)
            ->selectRaw('order_number, channel, email_address, MAX(paid_amount) as paid_amount')
            ->groupBy('order_number','channel','email_address');

        // Summary across all channels
        $summary = DB::query()->fromSub($ordersSub, 'o')
            ->selectRaw('COUNT(*) as orders, COUNT(DISTINCT email_address) as customers, SUM(paid_amount) as revenue, AVG(paid_amount) as aov')
            ->first();

        // By channel
        $channels = DB::query()->fromSub($ordersSub, 'o')
            ->selectRaw('COALESCE(channel, "(unknown)") as channel, COUNT(*) as orders, COUNT(DISTINCT email_address) as customers, SUM(paid_amount) as revenue, AVG(paid_amount) as aov')
            ->groupBy('channel')
            ->orderByDesc('revenue')
            ->get();

        return [
            [
                'orders' => (int) ($summary->orders ?? 0),
                'customers' => (int) ($summary->customers ?? 0),
                'revenue' => (float) ($summary->revenue ?? 0),
                'aov' => (float) ($summary->aov ?? 0),
            ],
            $channels,
        ];
    }
}

