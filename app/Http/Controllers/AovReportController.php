<?php

namespace App\Http\Controllers;

use App\Models\ShopifyOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AovReportController extends Controller
{
    public function index(Request $request)
    {
        $campaign = (string) $request->get('utm_campaign', '');
        $from = (string) $request->get('from', now()->subMonth()->startOfMonth()->toDateString());
        $to = (string) $request->get('to', now()->subMonth()->endOfMonth()->toDateString());

        $summary = null;
        $rows = collect();

        if ($campaign !== '') {
            [$summary, $rows] = $this->compute($campaign, $from, $to);
        }

        return view('analytics.aov', compact('campaign', 'from', 'to', 'summary', 'rows'));
    }

    public function data(Request $request)
    {
        $request->validate([
            'utm_campaign' => 'required|string',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        [$summary, $rows] = $this->compute($request->utm_campaign, $request->from, $request->to);

        return response()->json(array_merge([
            'campaign' => $request->utm_campaign,
            'from' => $request->from,
            'to' => $request->to,
        ], $summary));
    }

    private function compute(string $campaign, string $from, string $to): array
    {
        $fromDt = Carbon::parse($from)->startOfDay();
        $toDt = Carbon::parse($to)->endOfDay();

        // Build distinct orders subquery to avoid duplicate rows skewing AOV
        $distinctOrdersSub = ShopifyOrder::query()
            ->whereBetween('order_date', [$fromDt, $toDt])
            ->whereNotNull('email_address')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.landing_site')) LIKE ?", ['%utm_campaign=' . $campaign . '%'])
            ->selectRaw('email_address, order_number, MAX(paid_amount) as paid_amount')
            ->groupBy('email_address', 'order_number');

        $rows = DB::query()
            ->fromSub($distinctOrdersSub, 'o')
            ->selectRaw('email_address, COUNT(*) as orders_count, AVG(paid_amount) as aov, SUM(paid_amount) as total_spend')
            ->groupBy('email_address')
            ->get();

        $customers70Plus = $rows->where('aov', '>=', 70)->count();
        $customers69OrLess = $rows->where('aov', '<=', 69)->count();

        // Orders count for the selected campaign within date range (distinct orders)
        $campaignOrders = ShopifyOrder::query()
            ->whereBetween('order_date', [$fromDt, $toDt])
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.landing_site')) LIKE ?", ['%utm_campaign=' . $campaign . '%'])
            ->distinct('order_number')
            ->count('order_number');

        // Total orders in the selected date range (regardless of campaign) - distinct orders
        $totalOrders = ShopifyOrder::query()
            ->whereBetween('order_date', [$fromDt, $toDt])
            ->distinct('order_number')
            ->count('order_number');

        $summary = [
            'customers_70_plus' => $customers70Plus,
            'customers_69_or_less' => $customers69OrLess,
            'total_customers' => $rows->count(),
            'campaign_orders' => $campaignOrders,
            'total_orders' => $totalOrders,
        ];

        return [$summary, $rows];
    }
}
