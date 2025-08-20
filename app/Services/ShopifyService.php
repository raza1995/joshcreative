<?php

namespace App\Services;

use App\Models\ShopifyOrder;
use DB;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShopifyService
{
    protected  $shopifyDomain;
    protected  $accessToken;

    public function __construct()
    {
        $this->shopifyDomain = env('SHOPIFY_STORE_DOMAIN');
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN');
    }

    
    public function fetchOrders($from_date = null, $to_date = null)
    {
        $from = $from_date ? Carbon::parse($from_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $to   = $to_date   ? Carbon::parse($to_date)->endOfDay()     : now()->endOfDay();
    
        $baseUrl = "https://{$this->shopifyDomain}/admin/api/2025-07/orders.json";
        $params = [
            'status'          => 'any',
            'created_at_min'  => $from->toIso8601String(),
            'created_at_max'  => $to->toIso8601String(),
            'limit'           => 250,
            'fields'          => 'id,name,email,created_at,line_items,customer,fulfillments,total_price,total_discounts,discount_codes,landing_site,attributes,note_attributes',
        ];
    
        $nextUrl = null;
    
        do {
            $url = $nextUrl ?: $baseUrl;
    
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
            ])->get($url, $nextUrl ? [] : $params);
    
            if (!$response->successful()) {
                Log::error('Shopify Order Fetch Failed', ['status' => $response->status(), 'body' => $response->body()]);
                return 'Fetch failed!';
            }
    
            $orders = $response->json('orders') ?? [];
    
            foreach ($orders as $order) {
                $rawJson = json_encode($order);
                $orderId = (string) $order['id'];
                $createdAt = Carbon::parse($order['created_at']);
                $email = $order['email'] ?? null;
                $customerName = trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? '')) ?: 'Unknown';
                $couponCode = $order['discount_codes'][0]['code'] ?? null;
                $totalPrice = (float) ($order['total_price'] ?? 0);
                $totalDisc = (float) ($order['total_discounts'] ?? 0);
                $itemsCount = count($order['line_items'] ?? []);
    
                $trackingNumber = $order['fulfillments'][0]['tracking_number'] ?? null;
                $trackingUrl = $order['fulfillments'][0]['tracking_urls'][0] ?? ($order['fulfillments'][0]['tracking_url'] ?? null);
    
                // anon_id from attributes or note_attributes
                $anonId = $order['attributes']['_anon_id'] ?? null;
                if (!$anonId && !empty($order['note_attributes'])) {
                    foreach ($order['note_attributes'] as $attr) {
                        if (($attr['name'] ?? '') === '_anon_id') {
                            $anonId = $attr['value'] ?? null;
                            break;
                        }
                    }
                }
    
                // ad_id from landing_site
                $adId = null;
                if (!empty($order['landing_site'])) {
                    if ($query = parse_url($order['landing_site'], PHP_URL_QUERY)) {
                        parse_str($query, $utm);
                        $adId = $utm['ad_id'] ?? $utm['utm_content'] ?? $utm['utm_term'] ?? null;
                    }
                }
    
                foreach ($order['line_items'] as $item) {
                    $productTitle = $item['title'] ?? 'Untitled';
    
                    // Upsert based on order_number
                    $result = ShopifyOrder::updateOrCreate(
                        ['order_number' => $orderId],
                        [
                            'order_date'      => $createdAt,
                            'customer_name'   => $customerName,
                            'email_address'   => $email,
                            'paid_amount'     => $totalPrice,
                            'discount'        => $totalDisc,
                            'number_of_items' => $itemsCount,
                            'tracking_number' => $trackingNumber,
                            'tracking_url'    => $trackingUrl,
                            'coupon'          => $couponCode,
                            'anon_id'         => $anonId,
                            'ad_id'           => $adId,
                            'raw_json'        => $rawJson,
                            'updated_at'      => now(),
                        ]
                    );

                    if (app()->runningInConsole()) {
                        $action = $result ? 'Created or Updated' : 'No change';
                        echo "[{$createdAt}] {$action} order #{$orderId} ({$productTitle})\n";
                    }
                }
            }
    
            // Handle pagination
            $nextUrl = null;
            $link = $response->header('Link');
            if ($link && str_contains($link, 'rel="next"')) {
                if (preg_match('/<([^>]+)>;\s*rel="next"/', $link, $m)) {
                    $nextUrl = $m[1];
                }
            }
    
        } while ($nextUrl);
    
        return 'Orders fetched and updated successfully.';
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
