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
                'tracking_number','paid_amount','number_of_items','raw_json','coupon'
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
            ->addColumn('order_date_formatted', fn (ShopifyOrder $o) => optional($o->order_date)->format('M d, Y H:i'))
            ->rawColumns(['tracking'])
            ->make(true);
    }

    public function exportCsv(Request $request)
    {
        $product = (string) $request->get('product', 'WATERMELON WAVE');

        return response()->streamDownload(function () use ($product) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order #','Customer Name','Email','Product','Quantity','Paid Amount','Shipping Method','Shipping Cost','Tracking Number','Order Date','Coupon']);

            ShopifyOrder::byProduct($product)->orderBy('order_date', 'desc')->chunk(200, function ($orders) use ($out) {
                foreach ($orders as $o) {
                    fputcsv($out, [
                        $o->order_number,
                        $o->customer_name ?: trim(($o->raw_json['customer']['first_name'] ?? '').' '.($o->raw_json['customer']['last_name'] ?? '')),
                        $o->email_address ?: ($o->raw_json['email'] ?? ''),
                        $o->product_name,
                        (int)($o->number_of_items ?? 0),
                        number_format((float)($o->paid_amount ?? 0), 2, '.', ''),
                        $o->raw_json['shipping_lines'][0]['title'] ?? '',
                        number_format((float)($o->raw_json['shipping_lines'][0]['price'] ?? 0), 2, '.', ''),
                        $o->tracking_number ?: ($o->raw_json['fulfillments'][0]['tracking_number'] ?? ''),
                        optional($o->order_date)->format('Y-m-d H:i:s'),
                        $o->coupon ?: ($o->raw_json['discount_codes'][0]['code'] ?? ''),
                    ]);
                }
            });

            fclose($out);
        }, sprintf('%s_orders_%s.csv', Str::slug($product, '_'), now()->format('Y-m-d')));
    }
}
