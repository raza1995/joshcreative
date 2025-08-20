<?php

namespace App\Http\Controllers;

use App\DataTables\ShopifyOrdersDataTable;
use App\Models\ExcludedIp;
use App\Models\Pages;
use App\Models\ShopifyOrder;
use App\Models\UserEvent;
use App\Services\ShopifyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use DB;
use Schema;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Response;
class ShopifyOrderController extends Controller
{
    public function fetchOrders(Request $request, ShopifyService $shopifyService): JsonResponse
{
    $message = $shopifyService->fetchOrders(
        $request->input('from_date'),
        $request->input('to_date')
    );

    return response()->json(['message' => $message]);
}

public function index(ShopifyOrdersDataTable $dataTable)
{
    return $dataTable->render('shopify.index');
}

    // Data for DataTables
public function data(Request $request)
{
    $product = (string) $request->get('product', 'WATERMELON WAVE 3');

    // Only load limited orders to avoid memory overload
    $orders = ShopifyOrder::orderByDesc('order_date')->limit(1000)->get();

    // Decode raw_json once and attach a helper field
    $orders = $orders->map(function ($o) {
        $o->raw_json_array = is_string($o->raw_json)
            ? json_decode($o->raw_json, true)
            : ($o->raw_json ?? []);
        return $o;
    });

    // Filter by product SKU if provided
    if ($product !== '') {
        $orders = $orders->filter(function ($o) use ($product) {
            foreach ($o->raw_json_array['line_items'] ?? [] as $li) {
                if (($li['sku'] ?? '') === $product) {
                    return true;
                }
            }
            return false;
        })->values(); // reset index
    }

    return datatables()->of($orders)
        ->addColumn('full_name', fn ($o) =>
            e($o->customer_name ?: trim(($o->raw_json_array['customer']['first_name'] ?? '') . ' ' . ($o->raw_json_array['customer']['last_name'] ?? '')))
        )
        ->addColumn('email', fn ($o) =>
            e($o->email_address ?: ($o->raw_json_array['email'] ?? ''))
        )
        ->addColumn('shipping_method', fn ($o) =>
            e($o->raw_json_array['shipping_lines'][0]['title'] ?? 'N/A')
        )
        ->addColumn('shipping_cost', fn ($o) =>
            '$' . number_format((float)($o->raw_json_array['shipping_lines'][0]['price'] ?? 0), 2)
        )
        ->addColumn('tracking', function ($o) {
            $num = $o->tracking_number ?: ($o->raw_json_array['fulfillments'][0]['tracking_number'] ?? 'Not shipped');
            $url = $o->raw_json_array['fulfillments'][0]['tracking_urls'][0] ?? '';
            return $url
                ? '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . e($num) . '</a>'
                : e($num);
        })
        ->addColumn('order_total', fn ($o) =>
            '$' . number_format((float)($o->raw_json_array['total_price'] ?? $o->paid_amount ?? 0), 2)
        )
        ->addColumn('discount_codes', function ($o) {
            $codes = $o->raw_json_array['discount_codes'] ?? [];
            if (empty($codes)) return e($o->coupon ?? '—');

            $parts = [];
            foreach ($codes as $d) {
                $label = trim($d['code'] ?? '');
                if (!empty($d['type']))   $label .= ' (' . trim($d['type']) . ')';
                if (isset($d['amount']))  $label .= ' $' . number_format((float)$d['amount'], 2);
                $parts[] = e($label);
            }
            return implode(', ', $parts);
        })
        ->addColumn('total_discount', fn ($o) =>
            '$' . number_format((float)($o->raw_json_array['total_discounts'] ?? $o->discount ?? 0), 2)
        )
        ->addColumn('order_date_formatted', fn ($o) =>
            optional($o->order_date)->format('M d, Y H:i')
        )
        ->rawColumns(['tracking'])
        ->toJson();
}

    

//    public function exportCsv(Request $request)
// {
//     $sku = (string) $request->get('product', '');

//     return response()->streamDownload(function () use ($sku) {
//         $out = fopen('php://output', 'w');

//         // Write CSV header
//         fputcsv($out, [
//             'Order #', 'Customer Name', 'Email', 'Product', 'Quantity',
//             'Paid Amount', 'Shipping Method', 'Shipping Cost',
//             'Discount Codes', 'Total Discount', 'Tracking Number',
//             'Order Date', 'Coupon'
//         ]);

//         // Process in chunks to avoid memory issues
//         ShopifyOrder::orderByDesc('order_date')
//             ->select(['id', 'order_number', 'order_date', 'product_name', 'customer_name', 'email_address',
//                       'tracking_number', 'paid_amount', 'number_of_items', 'raw_json', 'coupon', 'discount'])
//             ->chunk(500, function ($orders) use ($out, $sku) {
//                 foreach ($orders as $o) {
//                     $raw = is_array($o->raw_json)
//                         ? $o->raw_json
//                         : (json_decode($o->raw_json, true) ?: []);

//                     // Filter by SKU if needed
//                     if ($sku !== '') {
//                         $matched = false;
//                         foreach ($raw['line_items'] ?? [] as $li) {
//                             if (($li['sku'] ?? '') === $sku) {
//                                 $matched = true;
//                                 break;
//                             }
//                         }
//                         if (!$matched) continue;
//                     }

//                     $customerName   = $o->customer_name ?: trim(($raw['customer']['first_name'] ?? '') . ' ' . ($raw['customer']['last_name'] ?? ''));
//                     $email          = $o->email_address ?: ($raw['email'] ?? '');
//                     $shippingMethod = $raw['shipping_lines'][0]['title'] ?? '';
//                     $shippingCost   = (float)($raw['shipping_lines'][0]['price'] ?? 0);
//                     $trackingNumber = $o->tracking_number ?: ($raw['fulfillments'][0]['tracking_number'] ?? '');

//                     // Discount codes
//                     $codesArr = $raw['discount_codes'] ?? [];
//                     $discountCodes = '—';

//                     if (!empty($codesArr)) {
//                         $parts = [];
//                         foreach ($codesArr as $d) {
//                             $code   = $d['code'] ?? '';
//                             $type   = $d['type'] ?? '';
//                             $amount = isset($d['amount']) ? '$' . number_format((float)$d['amount'], 2, '.', '') : '';
//                             $label  = trim($code);
//                             if ($type !== '')   $label .= " ($type)";
//                             if ($amount !== '') $label .= " $amount";
//                             $parts[] = $label;
//                         }
//                         $discountCodes = implode(', ', $parts);
//                     } elseif (!empty($o->coupon)) {
//                         $discountCodes = $o->coupon;
//                     }

//                     $totalDiscount = (float)($raw['total_discounts'] ?? $o->discount ?? 0);

//                     fputcsv($out, [
//                         $o->order_number,
//                         $customerName,
//                         $email,
//                         $o->product_name,
//                         (int)($o->number_of_items ?? 0),
//                         number_format((float)($o->paid_amount ?? 0), 2, '.', ''),
//                         $shippingMethod,
//                         number_format($shippingCost, 2, '.', ''),
//                         $discountCodes,
//                         '$' . number_format($totalDiscount, 2, '.', ''),
//                         $trackingNumber,
//                         optional($o->order_date)->format('Y-m-d H:i:s'),
//                         $o->coupon ?: ($raw['discount_codes'][0]['code'] ?? ''),
//                     ]);
//                 }
//             });

//         fclose($out);
//     }, 'orders_' . now()->format('Y-m-d') . '.csv');
// }

public function exportCsv(Request $request)
{
    $sku = $request->get('product', '');

    $orders = ShopifyOrder::orderByDesc('order_date')
        ->limit(1000)
        ->get()
        ->filter(function ($o) use ($sku) {
            $data = is_string($o->raw_json) ? json_decode($o->raw_json, true) : ($o->raw_json ?? []);
            foreach ($data['line_items'] ?? [] as $li) {
                if (($li['sku'] ?? '') === $sku) {
                    $o->raw_json_array = $data;
                    return true;
                }
            }
            return false;
        });

    $filename = 'shopify_orders_' . now()->format('Ymd_His') . '.csv';

    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => "attachment; filename=\"$filename\"",
    ];

    $callback = function () use ($orders) {
        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'Order #',
            'Full Name',
            'Email',
            'Paid',
            'Shipping',
            'Shipping Cost',
            'Discount Codes',
            'Total Discount',
            'Tracking',
            'Order Date',
        ]);

        foreach ($orders as $o) {
            $raw = $o->raw_json_array;

            $fullName = $o->customer_name ?: trim(($raw['customer']['first_name'] ?? '') . ' ' . ($raw['customer']['last_name'] ?? ''));
            $email = $o->email_address ?: ($raw['email'] ?? '');
            $shipping = $raw['shipping_lines'][0]['title'] ?? 'N/A';
            $shipCost = (float)($raw['shipping_lines'][0]['price'] ?? 0);
            $orderTotal = (float)($raw['total_price'] ?? $o->paid_amount ?? 0);
            $discountCodes = collect($raw['discount_codes'] ?? [])
                ->map(fn ($d) => ($d['code'] ?? '') . (isset($d['amount']) ? ' $' . $d['amount'] : ''))
                ->implode(', ');
            $discountTotal = (float)($raw['total_discounts'] ?? $o->discount ?? 0);
            $tracking = $o->tracking_number ?? ($raw['fulfillments'][0]['tracking_number'] ?? 'Not shipped');
            $orderDate = optional($o->order_date)->format('M d, Y H:i');

            fputcsv($output, [
                $o->order_number,
                $fullName,
                $email,
                '$' . number_format($orderTotal, 2),
                $shipping,
                '$' . number_format($shipCost, 2),
                $discountCodes ?: '—',
                '$' . number_format($discountTotal, 2),
                $tracking,
                $orderDate,
            ]);
        }

        fclose($output);
    };

    return Response::stream($callback, 200, $headers);
}
}
