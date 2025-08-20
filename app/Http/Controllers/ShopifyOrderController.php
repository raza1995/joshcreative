<?php

namespace App\Http\Controllers;

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

public function index()
    {
        return view('shopify.index');
    }

    // Data for DataTables
    public function data(Request $request)
    {
        $product = (string) $request->get('product', 'WATERMELON WAVE');
    
        $query = ShopifyOrder::byProduct($product)
            ->select([
                'id','order_number','order_date','product_name','customer_name','email_address',
                'tracking_number','paid_amount','number_of_items','raw_json','coupon','discount'
            ]);
    
        return datatables()->of($query)
            ->addColumn('full_name', function (ShopifyOrder $order) {
                $fallback = trim(($order->raw_json['customer']['first_name'] ?? '') . ' ' . ($order->raw_json['customer']['last_name'] ?? ''));
                return e($order->customer_name ?: $fallback);
            })
            ->addColumn('email', fn (ShopifyOrder $o) => e($o->email_address ?: ($o->raw_json['email'] ?? '')))
            ->addColumn('shipping_method', fn (ShopifyOrder $o) => e($o->raw_json['shipping_lines'][0]['title'] ?? 'N/A'))
            ->addColumn('shipping_cost', function (ShopifyOrder $o) {
                $val = (float)($o->raw_json['shipping_lines'][0]['price'] ?? 0);
                return '$' . number_format($val, 2, '.', '');
            })
            ->addColumn('tracking', function (ShopifyOrder $o) {
                $num = $o->tracking_number ?: ($o->raw_json['fulfillments'][0]['tracking_number'] ?? 'Not shipped');
                $url = $o->raw_json['fulfillments'][0]['tracking_urls'][0] ?? '';
                if ($url) return '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e($num).'</a>';
                return e($num);
            })
            ->addColumn('order_total', function (ShopifyOrder $o) {
                $t = (float)($o->raw_json['total_price'] ?? $o->paid_amount ?? 0);
                return '$' . number_format($t, 2, '.', '');
            })
            ->addColumn('discount_codes', function (ShopifyOrder $o) {
                // Ensure we have an array even if raw_json is stored as a string
                $raw = is_array($o->raw_json)
                    ? $o->raw_json
                    : (is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : []);
            
                $codes = $raw['discount_codes'] ?? [];
            
                // If Shopify didn't include discount_codes, show the normalized coupon if present
                if (empty($codes)) {
                    return e($o->coupon ?? '—');
                }
            
                // Example output: "VIPWATERMELON (shipping) $15.04"
                $parts = [];
                foreach ($codes as $d) {
                    $code   = $d['code']  ?? '';
                    $type   = $d['type']  ?? '';
                    $amount = array_key_exists('amount', $d)
                        ? '$' . number_format((float)$d['amount'], 2, '.', '')
                        : '';
            
                    $label = trim($code);
                    if ($type !== '')   { $label .= ' (' . trim($type) . ')'; }
                    if ($amount !== '') { $label .= ' ' . $amount; }
                    $parts[] = e($label);
                }
            
                return implode(', ', $parts);
            })
            ->addColumn('total_discount', function (ShopifyOrder $o) {
                // Raw JSON first (authoritative), fallback to normalized column
                $total = $o->raw_json['total_discounts'] ?? $o->discount ?? 0;
                return '$' . number_format((float)$total, 2, '.', '');
            })
            ->addColumn('order_date_formatted', fn (ShopifyOrder $o) => optional($o->order_date)->format('M d, Y H:i'))
            ->rawColumns(['tracking']) // only 'tracking' contains HTML
            ->make(true);
    }
    

    public function exportCsv(Request $request)
    {
        $product = (string) $request->get('product', 'WATERMELON WAVE');
    
        return response()->streamDownload(function () use ($product) {
            $out = fopen('php://output', 'w');
    
            // Added "Discount Codes" and "Total Discount"
            fputcsv($out, [
                'Order #','Customer Name','Email','Product','Quantity','Paid Amount',
                'Shipping Method','Shipping Cost','Discount Codes','Total Discount',
                'Tracking Number','Order Date','Coupon'
            ]);
    
            ShopifyOrder::byProduct($product)
                ->orderBy('order_date', 'desc')
                ->chunk(200, function ($orders) use ($out) {
                    foreach ($orders as $o) {
                        // raw_json may be cast to array or stored as JSON string — handle both
                        $raw = is_array($o->raw_json)
                            ? $o->raw_json
                            : (is_string($o->raw_json) ? (json_decode($o->raw_json, true) ?: []) : []);
    
                        $customerName = $o->customer_name
                            ?: trim(($raw['customer']['first_name'] ?? '') . ' ' . ($raw['customer']['last_name'] ?? ''));
    
                        $email = $o->email_address ?: ($raw['email'] ?? '');
    
                        $shippingMethod = $raw['shipping_lines'][0]['title'] ?? '';
                        $shippingCost   = (float)($raw['shipping_lines'][0]['price'] ?? 0);
    
                        // Prefer first fulfillment; adjust if you want most recent
                        $trackingNumber = $o->tracking_number ?: ($raw['fulfillments'][0]['tracking_number'] ?? '');
    
                        // Build discount codes display: "CODE (type) $amount, ..."
                        $codesArr = $raw['discount_codes'] ?? [];
                        if (!empty($codesArr)) {
                            $parts = [];
                            foreach ($codesArr as $d) {
                                $code   = $d['code']  ?? '';
                                $type   = $d['type']  ?? '';
                                $amount = array_key_exists('amount', $d)
                                    ? '$' . number_format((float)$d['amount'], 2, '.', '')
                                    : '';
                                $label = trim($code);
                                if ($type !== '')   $label .= ' (' . $type . ')';
                                if ($amount !== '') $label .= ' ' . $amount;
                                $parts[] = $label;
                            }
                            $discountCodes = implode(', ', $parts);
                        } else {
                            // Fallback to normalized coupon column if Shopify array is empty
                            $discountCodes = $o->coupon ?? '—';
                        }
    
                        // Total discount: Shopify truth first, fallback to normalized column
                        $totalDiscount = (float)($raw['total_discounts'] ?? $o->discount ?? 0);
    
                        fputcsv($out, [
                            $o->order_number,
                            $customerName,
                            $email,
                            $o->product_name,
                            (int)($o->number_of_items ?? 0),
                            number_format((float)($o->paid_amount ?? 0), 2, '.', ''),
                            $shippingMethod,
                            number_format($shippingCost, 2, '.', ''),
                            $discountCodes,
                            '$' . number_format($totalDiscount, 2, '.', ''),
                            $trackingNumber,
                            optional($o->order_date)->format('Y-m-d H:i:s'),
                            // Keep old Coupon column for parity
                            $o->coupon ?: ($raw['discount_codes'][0]['code'] ?? ''),
                        ]);
                    }
                });
    
            fclose($out);
        }, sprintf('%s_orders_%s.csv', Str::slug($product, '_'), now()->format('Y-m-d')));
    }
}
