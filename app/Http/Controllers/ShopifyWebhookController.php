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
        $customerId = $orderData['customer']['id'] ?? null;
        $customerEmail = $orderData['email'] ?? null;
        $customerName = trim(($orderData['customer']['first_name'] ?? '') . ' ' . ($orderData['customer']['last_name'] ?? ''));
        $createdAt = Carbon::parse($orderData['created_at'])->toDateTimeString();
        $mixpanelId = $_COOKIE['mixpanel_id'] ?? null;
        // UTM Source
        $utm_source = collect($orderData['note_attributes'])->firstWhere('name', 'utm_source')['value'] ?? null;
    
        // Discount
        $couponCode = $orderData['discount_codes'][0]['code'] ?? null;
        $usedDiscount = !empty($couponCode);
    
        // Check previous orders from DB
        $previousOrders = ShopifyOrder::where('email_address', $customerEmail)->count();
        $isFirstTimeBuyer = $previousOrders === 0;
    
        // LTV calculation (only if stored in DB)
        $currentOrderAmount = (float) $orderData['total_price'];
        $totalSpend = ShopifyOrder::where('email_address', $customerEmail)->sum('paid_amount') + $currentOrderAmount;
        if ($totalSpend >= 500 && $totalSpend < 1000) {
            $mixpanelService->trackUserEvent($customerId, 'LTV Milestone: $500+', ['LTV' => $totalSpend]);
        } elseif ($totalSpend >= 1000) {
            $mixpanelService->trackUserEvent($customerId, 'LTV Milestone: $1000+', ['LTV' => $totalSpend]);
        }
        
        // Save each product and trigger event
        foreach ($orderData['line_items'] as $item) {
            ShopifyOrder::updateOrCreate(
                ['order_number' => $orderData['id']],
                [
                    'order_date' => $createdAt,
                    'product_name' => $item['title'],
                    'customer_name' => $customerName,
                    'email_address' => $customerEmail,
                    'tracking_number' => $orderData['fulfillments'][0]['tracking_number'] ?? null,
                    'tracking_url' => $orderData['fulfillments'][0]['tracking_url'] ?? null,
                    'coupon' => $couponCode,
                    'paid_amount' => $currentOrderAmount,
                    'discount' => $orderData['total_discounts'] ?? 0.00,
                    'number_of_items' => count($orderData['line_items']),
                ]
            );
    
            // Track per product
            $mixpanelService->trackUserEvent($customerId, 'Product Purchased', [
                'Product ID' => $item['product_id'],
                'Variant ID' => $item['variant_id'],
                'Product Title' => $item['title'],
                'SKU' => $item['sku'],
                'Quantity' => $item['quantity'],
                'Price' => (float) $item['price'],
                'Vendor' => $item['vendor'] ?? null,
                'Product Type' => $item['product_exists'] ? $item['product_type'] ?? null : 'Custom Line Item',
                'Tags' => $orderData['tags'] ?? null,
            
                // Order Details
                'Order ID' => $orderData['id'],
                'Order Name' => $orderData['name'],
                'Order Date' => $createdAt,
                'Currency' => $orderData['currency'],
                'Financial Status' => $orderData['financial_status'],
                'Fulfillment Status' => $orderData['fulfillment_status'] ?? 'unfulfilled',
                'Discount Used' => $usedDiscount,
                'UTM Source' => $utm_source,
            
                // Shipping
                'Shipping Country' => $orderData['shipping_address']['country'] ?? null,
                'Shipping Province' => $orderData['shipping_address']['province'] ?? null,
                'Shipping City' => $orderData['shipping_address']['city'] ?? null,
                'Shipping Zip' => $orderData['shipping_address']['zip'] ?? null,
            
                // Customer Meta
                'Customer First Name' => $orderData['customer']['first_name'] ?? null,
                'Customer Last Name' => $orderData['customer']['last_name'] ?? null,
                'Customer Email' => $customerEmail,
                'Is First-Time Buyer' => $isFirstTimeBuyer,
            ]);
            
        }
    
        // Track full order
        $mixpanelService->trackUserEvent($customerId, 'Order Created', [
            'Order ID' => $orderData['id'],
            'Order Name' => $orderData['name'],
            'Order Date' => $createdAt,
            'Currency' => $orderData['currency'],
            'Financial Status' => $orderData['financial_status'],
            'Fulfillment Status' => $orderData['fulfillment_status'] ?? 'unfulfilled',
            'Total Price' => (float) $orderData['total_price'],
            'Subtotal Price' => (float) $orderData['subtotal_price'] ?? 0.0,
            'Total Discount' => (float) $orderData['total_discounts'] ?? 0.0,
            'Coupon Code' => $couponCode ?? 'None',
            'Tax' => $orderData['total_tax'] ?? 0.0,
            'Shipping Price' => $orderData['total_shipping_price_set']['shop_money']['amount'] ?? 0.0,
            'Items Count' => count($orderData['line_items']),
            'Tags' => $orderData['tags'] ?? null,
        
            // Customer Info
            'Customer Name' => $customerName,
            'Customer Email' => $customerEmail,
            'Customer Phone' => $orderData['customer']['phone'] ?? null,
            'Customer ID' => $orderData['customer']['id'] ?? null,
            'Customer Note' => $orderData['note'] ?? null,
        
            // Address Info
            'Shipping Address' => $orderData['shipping_address']['address1'] ?? null,
            'Shipping City' => $orderData['shipping_address']['city'] ?? null,
            'Shipping Province' => $orderData['shipping_address']['province'] ?? null,
            'Shipping Country' => $orderData['shipping_address']['country'] ?? null,
            'Shipping Zip' => $orderData['shipping_address']['zip'] ?? null,
            'Billing Address' => $orderData['billing_address']['address1'] ?? null,
            'Billing City' => $orderData['billing_address']['city'] ?? null,
            'Billing Province' => $orderData['billing_address']['province'] ?? null,
            'Billing Country' => $orderData['billing_address']['country'] ?? null,
            'Billing Zip' => $orderData['billing_address']['zip'] ?? null,
        
            // Attribution
            'UTM Source' => $utm_source,
            'Used Discount' => $usedDiscount,
            'First-Time Buyer' => $isFirstTimeBuyer,
        ]);
        
        if ($mixpanelId && $mixpanelId !== $customerId) {
            $mixpanelService->alias($customerId, $mixpanelId);
        }
        
        // Update user profile
        $mixpanelService->identifyUser($customerId, [
            'name' => $customerName,
            'email' => $customerEmail,
            'First-Time Buyer' => $isFirstTimeBuyer,
            'Total Orders' => $previousOrders + 1,
            'Total Spend' => $totalSpend,
            'Last Order Date' => $createdAt,
            'Used Discount' => $usedDiscount,
            'Discount Code' => $couponCode ?? 'None',
        ]);
    
        return response()->json(['message' => 'Webhook received, saved, and tracked.']);
    }
    

    public function handleFulfillmentUpdate(Request $request, MixpanelService $mixpanelService)
    {
        Log::info('Shopify Fulfillment Webhook Received:', ['body' => $request->all()]);
    
        $fulfillmentData = $request->all();
        $orderId = $fulfillmentData['order_id'] ?? null;
    
        if (!$orderId) {
            return response()->json(['message' => 'Missing order ID.'], 400);
        }
    
        // Attempt to find the order in DB
        $order = ShopifyOrder::where('order_number', $orderId)->first();
    
        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }
    
        // Extract fallback-safe values
        $trackingNumber = $fulfillmentData['tracking_number'] ?? $order->tracking_number ?? 'N/A';
        $trackingUrl = $fulfillmentData['tracking_url'] ?? $order->tracking_url ?? 'N/A';
        $recipientFirstName = $fulfillmentData['recipient']['first_name'] ?? '';
        $recipientLastName = $fulfillmentData['recipient']['last_name'] ?? '';
        $customerName = trim($recipientFirstName . ' ' . $recipientLastName) ?: $order->customer_name;
    
        // Update DB record
        $order->update([
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingUrl,
            'customer_name' => $customerName,
        ]);
    
        // Track event in Mixpanel
        $mixpanelService->trackEvent('Order Fulfilled', [
            'Order ID' => $orderId,
            'Customer' => $customerName,
            'Tracking Number' => $trackingNumber,
            'Tracking URL' => $trackingUrl,
            'Fulfillment Date' => now()->toDateTimeString(),
        ]);
    
        return response()->json(['message' => 'Fulfillment updated and tracked.']);
    }
    
}
