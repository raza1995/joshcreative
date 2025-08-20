<?php

namespace App\Services;

use App\Models\ShopifyOrder;
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
        // Date window: use provided YYYY-MM-DD or default last 10 days
        $from = $from_date ? Carbon::parse($from_date, 'UTC')->startOfDay() : now('UTC')->subDays(30)->startOfDay();
        $to   = $to_date   ? Carbon::parse($to_date, 'UTC')->endOfDay()   : now('UTC')->endOfDay();
    
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
    
            Log::info('Fetching Shopify Orders', ['url' => $url, 'params' => $nextUrl ? [] : $params]);
    
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type'           => 'application/json',
            ])->get($url, $nextUrl ? [] : $params);
    
            if (!$response->successful()) {
                Log::error('Failed to fetch orders from Shopify', ['status' => $response->status(), 'body' => $response->body()]);
                return 'Failed to fetch orders!';
            }
    
            $orders = $response->json('orders') ?? [];
            Log::info('Fetched order count', ['count' => count($orders)]);
    
            foreach ($orders as $order) {
                // --- derive common fields once per order ---
                $customerName = !empty($order['customer']['first_name']) || !empty($order['customer']['last_name'])
                    ? trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? ''))
                    : 'Unknown Customer';
    
                $couponCode  = $order['discount_codes'][0]['code'] ?? null;
                $rawPayload  =json_encode($order); // store as array; cast will JSON it
                $landingSite = $order['landing_site'] ?? null;
    
                // anon id from attributes or note_attributes
                $anonId = $order['attributes']['_anon_id'] ?? null;
                if (!$anonId && !empty($order['note_attributes'])) {
                    foreach ($order['note_attributes'] as $attr) {
                        if (($attr['name'] ?? '') === '_anon_id') {
                            $anonId = $attr['value'] ?? null;
                            break;
                        }
                    }
                }
    
                // parse ad_id from landing_site UTM
                $parsedAdId = null;
                if (!empty($landingSite)) {
                    $query = parse_url($landingSite, PHP_URL_QUERY);
                    if ($query) {
                        parse_str($query, $utm);
                        $parsedAdId = $utm['ad_id'] ?? $utm['utm_content'] ?? $utm['utm_term'] ?? null;
                    }
                }
    
                // pick a tracking number/url (handle multiple fulfillments)
                $trackingNumber = null;
                $trackingUrl    = null;
                if (!empty($order['fulfillments'][0])) {
                    $f = $order['fulfillments'][0];
                    $trackingNumber = $f['tracking_number'] ?? null;
                    // prefer first URL in array, fallback to single tracking_url
                    $trackingUrl = $f['tracking_urls'][0] ?? ($f['tracking_url'] ?? null);
                }
    
                $createdAt   = Carbon::parse($order['created_at'])->utc();
                $email       = $order['email'] ?? null;
                $totalPrice  = (float)($order['total_price'] ?? 0);
                $totalDisc   = (float)($order['total_discounts'] ?? 0);
                $itemsCount  = is_countable($order['line_items'] ?? []) ? count($order['line_items']) : 0;
    
                // --- upsert per line item (key: order_id + product title) ---
                foreach ($order['line_items'] as $item) {
                    $productTitle = $item['title'] ?? null;
    
                    // Find or create by natural key
                    $row = ShopifyOrder::firstOrNew([
                        'order_number' => (string)$order['id'],
                        'product_name' => $productTitle,
                    ]);
    
                    // Always set these (authoritative per Shopify)
                    $row->order_date      = $createdAt;
                    $row->customer_name   = $customerName;
                    $row->email_address   = $email;
                    $row->paid_amount     = $totalPrice;
                    $row->discount        = $totalDisc;
                    $row->number_of_items = $itemsCount;
    
                    // Only fill if missing/null to avoid overwriting previously curated data
                    if (empty($row->tracking_number) && !empty($trackingNumber)) $row->tracking_number = $trackingNumber;
                    if (empty($row->tracking_url)    && !empty($trackingUrl))    $row->tracking_url    = $trackingUrl;
                    if (empty($row->coupon)          && !empty($couponCode))     $row->coupon          = $couponCode;
                    if (empty($row->anon_id)         && !empty($anonId))         $row->anon_id         = $anonId;
                    if (empty($row->ad_id)           && !empty($parsedAdId))     $row->ad_id           = $parsedAdId;
    
                    // Always keep the latest raw payload (helps debugging/evidence)
                    $row->raw_json = $rawPayload;
    
                    $row->save();
                }
            }
    
            // --- pagination (Link header, rel=next with page_info) ---
            $nextUrl = null;
            $link = $response->header('Link');
            if ($link && strpos($link, 'rel="next"') !== false) {
                if (preg_match('/<([^>]+)>;\s*rel="next"/', $link, $m) === 1) {
                    // Shopify returns an absolute URL with page_info. Use as-is.
                    $nextUrl = $m[1];
                }
            }
        } while ($nextUrl);
    
        return 'Orders fetched and upserted successfully.';
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
