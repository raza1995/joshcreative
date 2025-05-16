<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\ShopifyMycoleanOrder;
use App\Services\ShopifyOrderService;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class DashboardController extends Controller
{
    // public function index(ShopifyOrderService $shopifyOrderService)
    // {
    //     $start = now()->subMonth()->startOfMonth()->toIso8601String();
    //     $end = now()->subMonth()->endOfMonth()->toIso8601String();
    //     $orders = $shopifyOrderService->fetchMonthlyOrders($start, $end);
    
    //     $summary = [];
    
    //     foreach ($orders as $order) {
    //         foreach ($order['line_items'] as $item) {
    //             $id = $item['product_id'];
    //             $title = $item['title'];
    //             $quantity = $item['quantity'];
    //             $total = floatval($item['price']) * $quantity;
    
    //             if (!isset($summary[$id])) {
    //                 $summary[$id] = [
    //                     'title' => $title,
    //                     'quantity' => 0,
    //                     'sales' => 0.0,
    //                 ];
    //             }
    
    //             $summary[$id]['quantity'] += $quantity;
    //             $summary[$id]['sales'] += $total;
    //         }
    //     }
    
    //     return view('dashboard.sales', compact('summary'));
    // }

    public function index()
{
    return view('dashboard.sales');
}
public function filter(Request $request)
{
    $orders = ShopifyMycoleanOrder::query()
    ->when($request->start_date && $request->end_date, function ($query) use ($request) {
        $query->whereBetween('order_date', [$request->start_date, $request->end_date]);
    })
    ->get()
    ->filter(function ($order) {
        $raw = json_decode($order->raw_json, true);
        return !empty($raw['fulfillments']) && empty($raw['refunds']);
    });


    $summary = [];

    foreach ($orders as $order) {
        $raw = json_decode($order->raw_json, true);

        // Skip if line_items are missing
        if (!isset($raw['line_items'])) {
            continue;
        }

        foreach ($raw['line_items'] as $item) {
            $title = $item['title'];
            $quantity = $item['quantity'];
            $price = floatval($item['price']);
            $discount = 0;

            if (!empty($item['discount_allocations'])) {
                foreach ($item['discount_allocations'] as $allocation) {
                    $discount += floatval($allocation['amount']);
                }
            }

            $gross = $price * $quantity;
            $net = $gross - $discount;

            if (!isset($summary[$title])) {
                $summary[$title] = [
                    'product_title' => $title,
                    'total_items' => 0,
                    'gross_sales' => 0.0,
                    'net_sales' => 0.0,
                ];
            }

            $summary[$title]['total_items'] += $quantity;
            $summary[$title]['gross_sales'] += $gross;
            $summary[$title]['net_sales'] += $net;
        }
    }

    // Convert to array format expected by DataTables
    $data = array_values($summary);

    return DataTables::of($data)->make(true);
}


public function profit(Request $request)
{
    $startDate = $request->input('start_date') ?? now()->startOfMonth()->toDateString();
    $endDate = $request->input('end_date') ?? now()->endOfMonth()->toDateString();

    $adSpendRows = $request->input('ad_spends', []);
    $adSpend = ['Josh' => 0, 'Chandler' => 0];

    foreach ($adSpendRows as $row) {
        $company = $row['company'] ?? null;
        $amount = floatval($row['amount'] ?? 0);
        if ($company && isset($adSpend[$company])) {
            $adSpend[$company] += $amount;
        }
    }

    $productsMap = [
        'Josh' => [10004077281590, 9507341467958],
        'Chandler' => [10162053251382],
    ];

    $orders = ShopifyMycoleanOrder::whereBetween('order_date', [$startDate, $endDate])->get();

    $summary = [
        'Josh' => ['net_sales' => 0, 'cogs' => 0, 'ad_spend' => $adSpend['Josh']],
        'Chandler' => ['net_sales' => 0, 'cogs' => 0, 'ad_spend' => $adSpend['Chandler']],
    ];

    foreach ($orders as $order) {
        $raw = json_decode($order->raw_json, true);

        if (empty($raw['fulfillments']) || !empty($raw['refunds'])) {
            continue;
        }

        foreach ($raw['line_items'] ?? [] as $item) {
            $productId = $item['product_id'] ?? null;
            $variantId = $item['variant_id'] ?? null;
            $quantity = $item['quantity'] ?? 0;
            $price = floatval($item['price'] ?? 0);
            $discount = 0;

            foreach ($item['discount_allocations'] ?? [] as $alloc) {
                $discount += floatval($alloc['amount']);
            }

            $paidAmount = ($price * $quantity) - $discount;

            // Determine company by product_id
            $company = null;
            foreach ($productsMap as $key => $ids) {
                if (in_array($productId, $ids)) {
                    $company = $key;
                    break;
                }
            }

            if (!$company) continue;

            $summary[$company]['net_sales'] += $paidAmount;

            // Fetch cost
            $variant = ProductVariant::where('variant_id', $variantId)->first();
            $costPerItem = $variant?->cost ?? 0;

            $summary[$company]['cogs'] += $costPerItem * $quantity;
        }
    }

    foreach ($summary as $key => $data) {
        $summary[$key]['gross_profit'] = $data['net_sales'] - $data['cogs'] - $data['ad_spend'];
    }

    return view('dashboard.profit', compact('summary', 'adSpend', 'startDate', 'endDate'));
}



}
