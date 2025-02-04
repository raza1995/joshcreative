<?php

namespace App\Http\Controllers;

use App\Models\ShopifyOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    public function handleOrderWebhook(Request $request)
    {
        Log::info('Shopify Order Webhook Received:', $request->all());

        $orderData = $request->all();

        // Save order details to database
        foreach ($orderData['line_items'] as $item) {
            ShopifyOrder::create([
                'order_number' => $orderData['id'],
                'order_date' => $orderData['created_at'],
                'product_name' => $item['title'],
                'customer_name' => ($orderData['customer']['first_name'] ?? '') . ' ' . ($orderData['customer']['last_name'] ?? ''),
                'email_address' => $orderData['email'] ?? null,
                'tracking_number' => $orderData['fulfillments'][0]['tracking_number'] ?? null,
                'tracking_url' => $orderData['fulfillments'][0]['tracking_url'] ?? null,
                'coupon' => $orderData['discount_codes'][0]['code'] ?? null,
                'paid_amount' => $orderData['total_price'] ?? 0.00,
                'discount' => $orderData['total_discounts'] ?? 0.00,
                'number_of_items' => count($orderData['line_items']),
            ]);
        }

        return response()->json(['message' => 'Order received and saved.']);
    }
}
