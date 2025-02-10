<?php

namespace App\Services;

use App\Models\ShopifyOrder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
class ShopifyService
{
    protected string $shopifyDomain;
    protected string $accessToken;

    public function __construct()
    {
        $this->shopifyDomain = env('SHOPIFY_STORE_DOMAIN');
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN');
    }

    
    public function fetchOrders($from_date = null, $to_date = null)
    {
        // Default to last 12 months if no date is provided
        if (!$from_date || !$to_date) {
            $from_date = now()->subMonths(12)->format('Y-m-d');
            $to_date = now()->format('Y-m-d');
        }
    
        // Shopify API base URL
        $base_url = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json";
        $params = [
            'status' => 'any',
            'created_at_min' => $from_date . 'T00:00:00Z',
            'created_at_max' => $to_date . 'T23:59:59Z',
            'limit' => 250, // Fetch max records per page
            'fields' => 'id,name,email,created_at,line_items,customer,fulfillments,total_price,total_discounts,discount_codes'
        ];
    
        do {
            Log::info('Fetching Shopify Orders from API', ['params' => $params]);
    
            // Send request to Shopify API
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->get($base_url, $params);
    
            // Check if the response is successful
            if (!$response->successful()) {
                Log::error('Failed to fetch orders from Shopify', ['response' => $response->body()]);
                return 'Failed to fetch orders!';
            }
    
            // Get orders data
            $orders = $response->json()['orders'];
    
            // Process each order and save it to the database
            foreach ($orders as $order) {
                $customerName = !empty($order['customer']['first_name']) || !empty($order['customer']['last_name'])
                    ? trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? ''))
                    : 'Unknown Customer';
    
                // Get discount code if available
                $couponCode = $order['discount_codes'][0]['code'] ?? null;
    
                foreach ($order['line_items'] as $item) {
                    // Check if the order already exists with the same data
                    $existingOrder = ShopifyOrder::where('order_number', $order['id'])
                        ->where('product_name', $item['title'])
                        ->where('order_date', Carbon::parse($order['created_at'])->format('Y-m-d H:i:s'))
                        ->where('customer_name', $customerName)
                        ->where('email_address', $order['email'] ?? null)
                        ->where('tracking_number', $order['fulfillments'][0]['tracking_number'] ?? null)
                        ->where('tracking_url', $order['fulfillments'][0]['tracking_url'] ?? null)
                        ->where('coupon', $couponCode)
                        ->where('paid_amount', $order['total_price'] ?? 0.00)
                        ->where('discount', $order['total_discounts'] ?? 0.00)
                        ->where('number_of_items', count($order['line_items']))
                        ->exists();
    
                    if (!$existingOrder) {
                        // Insert new order only if no duplicate record exists
                        ShopifyOrder::create([
                            'product_name' => $item['title'] ?? null,
                            'order_date' => Carbon::parse($order['created_at'])->format('Y-m-d H:i:s'), // Convert date to MySQL format
                            'customer_name' => $customerName,
                            'email_address' => $order['email'] ?? null,
                            'tracking_number' => $order['fulfillments'][0]['tracking_number'] ?? null,
                            'tracking_url' => $order['fulfillments'][0]['tracking_url'] ?? null,
                            'coupon' => $couponCode,
                            'paid_amount' => $order['total_price'] ?? 0.00,
                            'discount' => $order['total_discounts'] ?? 0.00,
                            'number_of_items' => count($order['line_items']),
                        ]);
                    } else {
                        Log::info("Skipping duplicate order: " . $order['id']);
                    }
                }
            }
    
            // Get the next page URL from the "Link" header
            $next_url = null;
            $link_header = $response->header('Link');
    
            if ($link_header && strpos($link_header, 'rel="next"') !== false) {
                preg_match('/<([^>]+)>; rel="next"/', $link_header, $matches);
                if (isset($matches[1])) {
                    $next_url = $matches[1];
                }
            }
    
            // Continue fetching the next page if available
            if ($next_url) {
                // Parse the next page URL to get query parameters
                $parsed_url = parse_url($next_url);
                parse_str($parsed_url['query'], $params);
                $base_url = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json";
    
                // Create new tab for each pagination step
                Log::info('Fetching next page: ' . $next_url);
            }
    
        } while ($next_url);
    
        return 'All orders from the last 12 months fetched and stored successfully!';
    }
    
public function registerWebhook()
{
    $webhookUrl = route('shopify.webhook.orders'); // Your Laravel webhook route
    $topic = 'orders/create'; // Event type

    Log::info('Attempting to register Shopify webhook.', [
        'webhook_url' => $webhookUrl,
        'topic' => $topic,
    ]);

    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $this->accessToken,
        'Content-Type' => 'application/json',
    ])->post("https://{$this->shopifyDomain}/admin/api/2024-01/webhooks.json", [
        'webhook' => [
            'topic' => $topic,
            'address' => $webhookUrl,
            'format' => 'json',
        ],
    ]);

    if ($response->successful()) {
        \Log::info('Webhook registered successfully.');
        return 'Webhook registered successfully!';
    } else {
        \Log::error('Failed to register webhook.', [
            'response_status' => $response->status(),
            'response_body' => $response->body(),
        ]);
        return 'Failed to register webhook.';
    }
}

public function getAccessToken()
{
    return $this->accessToken;
}

public function getShopifyDomain()
{
    return $this->shopifyDomain;
}
public function listWebhooks()
{
    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $this->accessToken,
        'Content-Type' => 'application/json',
    ])->get("https://{$this->shopifyDomain}/admin/api/2024-01/webhooks.json");

    return $response->json();
}

public function registerFulfillmentWebhook()
{
    $webhookUrl = route('shopify.webhook.fulfillment'); // Ensure this route exists
    $topic = 'fulfillments/update';

    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $this->accessToken,
        'Content-Type' => 'application/json',
    ])->post("https://{$this->shopifyDomain}/admin/api/2024-01/webhooks.json", [
        'webhook' => [
            'topic' => $topic,
            'address' => $webhookUrl,
            'format' => 'json',
        ],
    ]);

    return $response->successful() ? 'Fulfillment webhook registered successfully!' : 'Failed to register fulfillment webhook!';
}

public function fetchAllOrdersFromShopify($from_date = null, $to_date = null)
{
    if (!$from_date || !$to_date) {
        $from_date = now()->subMonths(12)->format('Y-m-d');
        $to_date = now()->format('Y-m-d');
    }

    $base_url = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json";
    $params = [
        'status' => 'any',
        'created_at_min' => $from_date . 'T00:00:00Z',
        'created_at_max' => $to_date . 'T23:59:59Z',
        'limit' => 250, // Fetch max records per page
    ];

    $allOrders = [];

    do {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->get($base_url, $params);

        if (!$response->successful()) {
            return 'Failed to fetch orders!';
        }

        $orders = $response->json()['orders'];
        $allOrders = array_merge($allOrders, $orders);

        $next_url = null;
        $link_header = $response->header('Link');
        if ($link_header && strpos($link_header, 'rel="next"') !== false) {
            preg_match('/<([^>]+)>; rel="next"/', $link_header, $matches);
            if (isset($matches[1])) {
                $next_url = $matches[1];
            }
        }

        if ($next_url) {
            $parsed_url = parse_url($next_url);
            parse_str($parsed_url['query'], $params);
        }
    } while ($next_url);

    return $allOrders;
}

public function sendAllShopifyOrdersToMixpanel(MixpanelService $mixpanelService, $from_date = null, $to_date = null)
{
    $orders = $this->fetchAllOrdersFromShopify($from_date, $to_date);
    $events = [];

    foreach ($orders as $order) {
        $customerName = trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? ''));
        $couponCode = $order['discount_codes'][0]['code'] ?? null;
        $usedDiscount = $couponCode ? true : false;

        $events[] = [
            'event_name' => 'Order Created',
            'properties' => [
                'Order ID' => $order['id'],
                'Customer Name' => $customerName,
                'Email' => $order['email'] ?? 'N/A',
                'Phone' => $order['customer']['phone'] ?? 'N/A',
                'Shipping Address' => json_encode($order['shipping_address'] ?? []),
                'Billing Address' => json_encode($order['billing_address'] ?? []),
                'Total Price' => $order['total_price'],
                'Discount' => $order['total_discounts'],
                'Coupon' => $couponCode ?: 'None',
                'Items Count' => is_array($order['line_items']) ? count($order['line_items']) : 0,
                'Order Date' => Carbon::parse($order['created_at'])->format('Y-m-d H:i:s'),
                'Payment Method' => $order['payment_gateway_names'][0] ?? 'Unknown',
                'Currency' => $order['currency'],
                'Financial Status' => $order['financial_status'],
                'Order Status URL' => $order['order_status_url'],
                'Fulfillment Status' => $order['fulfillment_status'] ?? 'Unfulfilled',
                'Used Discount' => $usedDiscount,
            ]
        ];
    }

    $mixpanelService->trackBulkEvents($events);

    return "All Shopify orders sent to Mixpanel!";
}
public function getOrderByNumber($orderNumber)
{
    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $this->accessToken,
        'Content-Type' => 'application/json',
    ])->get("https://{$this->shopifyDomain}/admin/api/2024-01/orders.json", [
        'id' => $orderNumber,
        'status' => 'any'
    ]);

    return $response->successful() ? $response->json()['orders'][0] ?? null : null;
}

// Fetch orders by Email
public function getOrdersByEmail($email)
{
    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $this->accessToken,
        'Content-Type' => 'application/json',
    ])->get("https://{$this->shopifyDomain}/admin/api/2024-01/orders.json", [
        'email' => $email,
        'status' => 'any'
    ]);

    return $response->successful() ? $response->json()['orders'] ?? [] : [];
}

public function getMessagesFromHistory($historyId, $userId = 'me')
{
    try {
        $response = $this->service->users_history->listUsersHistory($userId, [
            'startHistoryId' => $historyId
        ]);

        $historyRecords = $response->getHistory();
        $messages = [];

        foreach ($historyRecords as $record) {
            if ($record->getMessagesAdded()) {
                foreach ($record->getMessagesAdded() as $addedMessage) {
                    $messages[] = $addedMessage->getMessage();
                }
            }
        }

        return $messages;
    } catch (\Exception $e) {
        \Log::error("Error fetching history: " . $e->getMessage());
        return [];
    }
}



}
