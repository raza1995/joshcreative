<?php

namespace App\Http\Controllers;

use App\Models\ShopifyOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\MixpanelService;
use App\Services\AttributionService;
use App\Services\ShipStationOrderConsolidatorService;
use App\Jobs\PushOrderToShipStationJob;

class ShopifyWebhookController extends Controller
{
    public function handleOrderWebhook(Request $request, MixpanelService $mixpanelService)
    {
        Log::info('Shopify Order Webhook Received myco:', ['body' => $request->all()]);
    
        $orderData = $request->all();
        $anonId = $orderData['attributes']['_anon_id'] ?? null;


        if (!$anonId && isset($orderData['note_attributes'])) {
            foreach ($orderData['note_attributes'] as $attr) {
                if ($attr['name'] === '_anon_id') {
                    $anonId = $attr['value'];
                    break;
                }
            }
        }
        $customerId = $orderData['customer']['id'] ?? null;
        $customerEmail = $orderData['email'] ?? null;
        $customerName = trim(($orderData['customer']['first_name'] ?? '') . ' ' . ($orderData['customer']['last_name'] ?? ''));
        $createdAt = Carbon::parse($orderData['created_at'])->toDateTimeString();
        $mixpanelId = $_COOKIE['mixpanel_id'] ?? null;
        // UTM Source
        $utm_source = collect($orderData['note_attributes'])->firstWhere('name', 'utm_source')['value'] ?? null;
        $rawPayload = json_encode($orderData);
        // Discount
        $couponCode = $orderData['discount_codes'][0]['code'] ?? null;
        $usedDiscount = !empty($couponCode);
    
        // Check previous orders from DB
        $previousOrders = ShopifyOrder::where('email_address', $customerEmail)->count();
        $isFirstTimeBuyer = $previousOrders === 0;
        $isReturningCustomer = !$isFirstTimeBuyer;
        $identityProps = [
            '$device_id' => $anonId,
            '$user_id' => $customerEmail,
            'distinct_id' => $customerEmail,
            'Returning Customer' => $isReturningCustomer,
        ];
        // LTV calculation (only if stored in DB)
        $currentOrderAmount = (float) $orderData['total_price'];
        $totalSpend = ShopifyOrder::where('email_address', $customerEmail)->sum('paid_amount') + $currentOrderAmount;
        if ($totalSpend >= 500 && $totalSpend < 1000) {
            $mixpanelService->trackUserEvent($customerEmail, 'LTV Milestone: $500+', ['LTV' => $totalSpend]);
        } elseif ($totalSpend >= 1000) {
            $mixpanelService->trackUserEvent($customerEmail, 'LTV Milestone: $1000+', ['LTV' => $totalSpend]);
        }
      
        // Save each product and trigger event
        foreach ($orderData['line_items'] as $item) {

            $landingSite = $orderData['landing_site'] ?? '';
            $parsedAdId = null;
            
            if ($landingSite) {
                $query = parse_url($landingSite, PHP_URL_QUERY);
                parse_str($query, $utm);
            
                // Check for 'ad_id' first, then 'utm_content' if missing
                if (!empty($utm['ad_id']) && preg_match('/^\d{10,30}$/', $utm['ad_id'])) {
                    $parsedAdId = $utm['ad_id'];
                } elseif (!empty($utm['utm_content']) && preg_match('/^\d{10,30}$/', $utm['utm_content'])) {
                    $parsedAdId = $utm['utm_content'];
                }
            }
            


            $attr = AttributionService::extract($orderData);

            ShopifyOrder::updateOrCreate(
                ['order_number' => $orderData['id']],
                [
                    'order_date' => $createdAt,
                    'product_name' => $item['title'],
                    'customer_name' => $customerName,
                    'email_address' => $customerEmail,
                    'anon_id' => $anonId,
                    'raw_json' => $rawPayload,
                    'tracking_number' => $orderData['fulfillments'][0]['tracking_number'] ?? null,
                    'tracking_url' => $orderData['fulfillments'][0]['tracking_url'] ?? null,
                    'coupon' => $couponCode,
                    'paid_amount' => $currentOrderAmount,
                    'discount' => $orderData['total_discounts'] ?? 0.00,
                    'number_of_items' => count($orderData['line_items']),
                    'ad_id' => $attr['ad_id'] ?? $parsedAdId ?? null,
                    // UTM + channel fields
                    'utm_source'   => $attr['utm_source']   ?? null,
                    'utm_medium'   => $attr['utm_medium']   ?? null,
                    'utm_campaign' => $attr['utm_campaign'] ?? null,
                    'utm_content'  => $attr['utm_content']  ?? null,
                    'utm_term'     => $attr['utm_term']     ?? null,
                    'utm_id'       => $attr['utm_id']       ?? null,
                    'campaign_id'  => $attr['campaign_id']  ?? null,
                    'gclid'        => $attr['gclid']        ?? null,
                    'fbclid'       => $attr['fbclid']       ?? null,
                    'channel'      => $attr['channel']      ?? null,
                ]
            );
    
            // Track per product
            $mixpanelService->trackUserEvent($customerEmail, 'identity', array_merge($identityProps, [
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
                'email' => $customerEmail,  
                'Is First-Time Buyer' => $isFirstTimeBuyer,
            ]));
            
        }
        Log::info('Alias Attempt', [
            'anon_id' => $anonId,
            'email' => $customerEmail
        ]);
        // Mixpanel purchase (Order Created) event disabled per requirement
    
    
        if ($customerEmail) {
            // First alias anonymous ID to email
        
            // Then identify using the email
            $mixpanelService->identifyUser($customerEmail, [
                'name' => $customerName,
                'email' => $customerEmail,
                'First-Time Buyer' => $isFirstTimeBuyer,
                'Returning Customer' => $isReturningCustomer,
                'Total Orders' => $previousOrders + 1,
                'Total Spend' => $totalSpend,
                'Last Order Date' => $createdAt,
                'Used Discount' => $usedDiscount,
                'Discount Code' => $couponCode ?? 'None',
            ]);
        }
        
        // ShipStation Integration: Consolidate and push order
        $this->processShipStationOrder($orderData['id']);
    
        return response()->json(['message' => 'Webhook received, saved, and tracked.']);
    }
    

    /**
     * Process order for ShipStation (consolidate SKUs and queue push)
     */
    protected function processShipStationOrder($orderNumber)
    {
        // Check if ShipStation integration is enabled
        if (!config('shipstation.consolidate_skus', false)) {
            Log::info('ShipStation consolidation disabled', ['order_number' => $orderNumber]);
            return;
        }

        try {
            // Find the Shopify order
            $shopifyOrder = ShopifyOrder::where('order_number', $orderNumber)->first();

            if (!$shopifyOrder) {
                Log::warning('ShopifyOrder not found for ShipStation processing', [
                    'order_number' => $orderNumber,
                ]);
                return;
            }

            // Consolidate order
            $consolidator = app(ShipStationOrderConsolidatorService::class);
            $shipstationOrder = $consolidator->consolidateOrder($shopifyOrder);

            if (!$shipstationOrder) {
                Log::warning('Failed to consolidate order for ShipStation', [
                    'order_number' => $orderNumber,
                ]);
                return;
            }

            Log::info('Order consolidated for ShipStation', [
                'order_number' => $orderNumber,
                'shipstation_order_id' => $shipstationOrder->id,
            ]);

            // Queue push to ShipStation if auto-push is enabled
            if (config('shipstation.auto_push', false)) {
                $delayMinutes = config('shipstation.webhook_delay_minutes', 0);
                
                if ($delayMinutes > 0) {
                    // Delay push to let ShipStation auto-sync first - HIGH PRIORITY
                    PushOrderToShipStationJob::dispatch($shipstationOrder->id)
                        ->delay(now()->addMinutes($delayMinutes))
                        ->onQueue('high');
                    
                    Log::info('Order queued for ShipStation push with delay [HIGH PRIORITY]', [
                        'order_number' => $orderNumber,
                        'shipstation_order_id' => $shipstationOrder->id,
                        'delay_minutes' => $delayMinutes,
                        'priority' => 'HIGH',
                        'will_process_at' => now()->addMinutes($delayMinutes)->toDateTimeString(),
                    ]);
                } else {
                    // Immediate push - HIGHEST PRIORITY
                    PushOrderToShipStationJob::dispatch($shipstationOrder->id)
                        ->onQueue('high');
                    
                    Log::info('Order queued for ShipStation push (immediate) [HIGHEST PRIORITY]', [
                        'order_number' => $orderNumber,
                        'shipstation_order_id' => $shipstationOrder->id,
                        'priority' => 'HIGHEST',
                    ]);
                }
            } else {
                Log::info('Auto-push disabled, order consolidated but not queued', [
                    'order_number' => $orderNumber,
                ]);
            }

        } catch (\Exception $e) {
            // Log error but don't fail webhook
            Log::error('Error processing order for ShipStation', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function handleFulfillmentUpdate(Request $request, MixpanelService $mixpanelService)
    {
        Log::info('Shopify Fulfillment Webhook Received myco:', ['body' => $request->all()]);
    
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
    
        // Update DB record (do NOT change order_number; keep Shopify Order ID stable)
        $order->update([
            'product_name' => $fulfillmentData['title'] ?? $order->product_name,
            'customer_name' => $customerName,
            'email_address' => $fulfillmentData['email'] ?? $order->email_address,
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingUrl,
        ]);
    
        // Track event in Mixpanel
        $mixpanelService->trackEvent('Order Fulfilled', [
            'Order ID' => $orderId,
            'Customer' => $customerName,
            'email' => $fulfillmentData['email'], 
            'Tracking Number' => $trackingNumber,
            'Tracking URL' => $trackingUrl,
            'Fulfillment Date' => now()->toDateTimeString(),
        ]);
    
        return response()->json(['message' => 'Fulfillment updated and tracked.']);
    }
    
}
