<?php

namespace App\Http\Controllers;

use App\Models\ShopifyOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\DB;

class AovReportController extends Controller
{
    public function index(Request $request)
    {
        $medium = (string) $request->get('utm_medium', '');
        $from = (string) $request->get('from', now()->subMonth()->startOfMonth()->toDateString());
        $to = (string) $request->get('to', now()->subMonth()->endOfMonth()->toDateString());
        $ui = (bool) $request->boolean('ui', false);

        $summary = null;
        $rows = collect();
        $topSkus = [];

        if ($medium !== '') {
            [$summary, $rows, $topSkus] = $this->compute($medium, $from, $to, $ui);
        }

        return view('analytics.aov', compact('medium', 'from', 'to', 'ui', 'summary', 'rows', 'topSkus'));
    }

    public function data(Request $request)
    {
        $request->validate([
            'utm_medium' => 'required|string',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);

        [$summary, $rows] = $this->compute($request->utm_medium, $request->from, $request->to, $request->boolean('ui', false));

        return response()->json(array_merge([
            'utm_medium' => $request->utm_medium,
            'from' => $request->from,
            'to' => $request->to,
        ], $summary));
    }

    private function compute(string $utmMedium, string $from, string $to, bool $uiFilters = false): array
    {
        $fromDt = Carbon::parse($from)->startOfDay();
        $toDt = Carbon::parse($to)->endOfDay();

        // Base filters (campaign + date range)
        $base = ShopifyOrder::query()
            ->whereBetween('order_date', [$fromDt, $toDt])
            ->whereNotNull('email_address')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.landing_site')) LIKE ?", ['%utm_medium=' . $utmMedium . '%']);

        // Optional Shopify UI filters: paid, not-cancelled, non-test
        if ($uiFilters) {
            $base->whereRaw("JSON_EXTRACT(raw_json, '$.cancelled_at') IS NULL")
                 ->whereRaw("(JSON_EXTRACT(raw_json, '$.test') IS NULL OR JSON_EXTRACT(raw_json, '$.test') = false)")
                 ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.financial_status')) = 'paid'");
        }

        // Distinct orders (per email + order_number) for AOV
        $distinctOrdersSub = (clone $base)
            ->selectRaw('email_address, order_number, MAX(paid_amount) as paid_amount')
            ->groupBy('email_address', 'order_number');

        $rows = DB::query()
            ->fromSub($distinctOrdersSub, 'o')
            ->selectRaw('email_address, COUNT(*) as orders_count, AVG(paid_amount) as aov, SUM(paid_amount) as total_spend')
            ->groupBy('email_address')
            ->get();

        $customers70Plus = $rows->where('aov', '>=', 70)->count();
        $customers69OrLess = $rows->where('aov', '<=', 69)->count();

        // Orders count for the selected utm_medium within date range (distinct orders)
        $campaignOrders = (clone $base)
            ->distinct('order_number')
            ->count('order_number');

        // Total orders in the selected date range (regardless of campaign) - distinct orders, reuse UI filters if any
        $baseAll = ShopifyOrder::query()->whereBetween('order_date', [$fromDt, $toDt]);
        if ($uiFilters) {
            $baseAll->whereRaw("JSON_EXTRACT(raw_json, '$.cancelled_at') IS NULL")
                    ->whereRaw("(JSON_EXTRACT(raw_json, '$.test') IS NULL OR JSON_EXTRACT(raw_json, '$.test') = false)")
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.financial_status')) = 'paid'");
        }
        $totalOrders = $baseAll
            ->distinct('order_number')
            ->count('order_number');

        // New vs Returning: emails in filtered set vs their first-ever order date in DB
        $emailsInSet = DB::query()->fromSub($distinctOrdersSub, 'o')->select('email_address')->distinct()->pluck('email_address');
        $firsts = ShopifyOrder::query()
            ->selectRaw('email_address, MIN(order_date) as first_date')
            ->whereIn('email_address', $emailsInSet)
            ->groupBy('email_address')
            ->get()
            ->keyBy('email_address');
        $newCustomers = 0; $returningCustomers = 0;
        foreach ($emailsInSet as $email) {
            $first = $firsts[$email]->first_date ?? null;
            if ($first && $first >= $fromDt && $first <= $toDt) {
                $newCustomers++;
            } else {
                $returningCustomers++;
            }
        }

        // Discount usage: count distinct orders with any discount
        $discountOrders = (clone $base)
            ->where(function ($q) {
                $q->whereRaw("JSON_LENGTH(JSON_EXTRACT(raw_json, '$.discount_codes')) > 0")
                  ->orWhereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.total_discounts')) AS DECIMAL(10,2)) > 0")
                  ->orWhereRaw("COALESCE(coupon, '') <> ''");
            })
            ->distinct('order_number')
            ->count('order_number');
        $discountRate = $campaignOrders > 0 ? round(($discountOrders / $campaignOrders) * 100, 2) : 0.0;

        // Top SKUs by campaign: aggregate from raw_json across distinct orders in set
        $orderNumbersInSet = DB::query()->fromSub($distinctOrdersSub, 'o')->select('order_number')->distinct()->pluck('order_number');
        $skuStats = [];
        if ($orderNumbersInSet->count() > 0) {
            ShopifyOrder::query()
                ->whereIn('order_number', $orderNumbersInSet)
                ->select('order_number', 'raw_json')
                ->orderBy('id')
                ->chunkById(1000, function ($chunk) use (&$skuStats) {
                    foreach ($chunk as $o) {
                        $raw = is_array($o->raw_json) ? $o->raw_json : (is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : []);
                        $seen = [];
                        foreach (($raw['line_items'] ?? []) as $li) {
                            $sku = trim((string) ($li['sku'] ?? ''));
                            if ($sku === '') $sku = '(no sku)';
                            $qty = (int) ($li['quantity'] ?? 0);
                            $price = (float) ($li['price'] ?? 0);
                            if (!isset($skuStats[$sku])) {
                                $skuStats[$sku] = ['orders' => 0, 'units' => 0, 'revenue' => 0.0];
                            }
                            // count order once per sku
                            if (!isset($seen[$sku])) {
                                $skuStats[$sku]['orders'] += 1;
                                $seen[$sku] = true;
                            }
                            $skuStats[$sku]['units'] += $qty;
                            $skuStats[$sku]['revenue'] += ($price * $qty);
                        }
                    }
                });
        }
        // sort by revenue desc and take top 20
        uasort($skuStats, function ($a, $b) { return $b['revenue'] <=> $a['revenue']; });
        $topSkus = array_slice(array_map(function ($sku, $v) {
            return ['sku' => $sku, 'orders' => $v['orders'], 'units' => $v['units'], 'revenue' => round($v['revenue'], 2)];
        }, array_keys($skuStats), $skuStats), 0, 20);

        $summary = [
            'customers_70_plus' => $customers70Plus,
            'customers_69_or_less' => $customers69OrLess,
            'total_customers' => $rows->count(),
            'campaign_orders' => $campaignOrders,
            'total_orders' => $totalOrders,
            'new_customers' => $newCustomers,
            'returning_customers' => $returningCustomers,
            'discount_orders' => $discountOrders,
            'discount_rate' => $discountRate,
        ];

        return [$summary, $rows, $topSkus];
    }
}
