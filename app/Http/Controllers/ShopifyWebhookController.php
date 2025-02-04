<?php

namespace App\Http\Controllers;

use App\Models\ShopifyOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\MixpanelService;

class ShopifyWebhookController extends Controller
{
    public function handleOrderWebhook(Request $request, MixpanelService $mixpanelService)
    {
        Log::info('Shopify Order Webhook Received:', ['body' => $request->all()]);

        $orderData = $request->all();

        foreach ($orderData['line_items'] as $item) {
            ShopifyOrder::updateOrCreate(
                ['order_number' => $orderData['id']], // Ensure uniqueness
                [
                    'order_date' => Carbon::parse($orderData['created_at'])->format('Y-m-d H:i:s'),
                    'product_name' => $item['title'],
                    'customer_name' => ($orderData['customer']['first_name'] ?? '') . ' ' . ($orderData['customer']['last_name'] ?? ''),
                    'email_address' => $orderData['email'] ?? null,
                    'tracking_number' => $orderData['fulfillments'][0]['tracking_number'] ?? null,
                    'tracking_url' => $orderData['fulfillments'][0]['tracking_url'] ?? null,
                    'coupon' => $orderData['discount_codes'][0]['code'] ?? null,
                    'paid_amount' => $orderData['total_price'] ?? 0.00,
                    'discount' => $orderData['total_discounts'] ?? 0.00,
                    'number_of_items' => count($orderData['line_items']),
                ]
            );
        }

        // Send Order Created Event to Mixpanel
        $mixpanelService->trackEvent('Order Created', [
            'Order ID' => $orderData['id'],
            'Customer' => ($orderData['customer']['first_name'] ?? '') . ' ' . ($orderData['customer']['last_name'] ?? ''),
            'Email' => $orderData['email'] ?? 'N/A',
            'Total Price' => $orderData['total_price'],
            'Discount' => $orderData['total_discounts'],
            'Coupon' => $orderData['discount_codes'][0]['code'] ?? 'None',
            'Items Count' => count($orderData['line_items']),
            'Date' => Carbon::parse($orderData['created_at'])->format('Y-m-d H:i:s'),
        ]);

        return response()->json(['message' => 'Webhook received and order saved.']);
    }

    public function handleFulfillmentUpdate(Request $request, MixpanelService $mixpanelService)
    {
        Log::info('Shopify Fulfillment Webhook Received:', ['body' => $request->all()]);

        $fulfillmentData = $request->all();

        // Check if the order exists in our database
        $order = ShopifyOrder::where('order_number', $fulfillmentData['order_id'])->first();

        if ($order) {
            // Update fulfillment details
            $order->update([
                'tracking_number' => $fulfillmentData['tracking_number'] ?? $order->tracking_number,
                'tracking_url' => $fulfillmentData['tracking_url'] ?? $order->tracking_url,
                'customer_name' => ($fulfillmentData['recipient']['first_name'] ?? '') . ' ' . ($fulfillmentData['recipient']['last_name'] ?? ''),
            ]);

            // Track fulfillment update in Mixpanel
            $mixpanelService->trackEvent('Order Fulfilled', [
                'Order ID' => $fulfillmentData['order_id'],
                'Customer' => $order->customer_name,
                'Tracking Number' => $fulfillmentData['tracking_number'] ?? 'N/A',
                'Tracking URL' => $fulfillmentData['tracking_url'] ?? 'N/A',
                'Fulfillment Date' => now()->format('Y-m-d H:i:s'),
            ]);

            return response()->json(['message' => 'Fulfillment updated and tracked.']);
        }

        return response()->json(['message' => 'Order not found.'], 404);
    }
}
