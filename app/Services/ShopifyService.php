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

    
    // public function fetchOrders($from_date = null, $to_date = null)
    // {
    //     $from = $from_date ? Carbon::parse($from_date)->startOfDay() : now()->subDays(30)->startOfDay();
    //     $to   = $to_date   ? Carbon::parse($to_date)->endOfDay()     : now()->endOfDay();
    
    //     $baseUrl = "https://{$this->shopifyDomain}/admin/api/2025-07/orders.json";
    //     $params = [
    //         'status'          => 'any',
    //         'created_at_min'  => $from->toIso8601String(),
    //         'created_at_max'  => $to->toIso8601String(),
    //         'limit'           => 250,
    //         // NOTE: removed 'attributes' (not a valid order field)
    //         'fields'          => 'id,name,email,created_at,line_items,customer,fulfillments,total_price,total_discounts,discount_codes,landing_site,note_attributes',
    //         // Optional but recommended to make Link pagination deterministic
    //         'order'           => 'created_at asc',
    //     ];
    
    //     $nextUrl = null;
    //     $created = 0;
    //     $updated = 0;
    
    //     do {
    //         $url = $nextUrl ?: $baseUrl;
    
    //         // Basic retry wrapper for 429/5xx
    //         $attempts = 0;
    //         $response = null;
    //         while ($attempts < 3) {
    //             $attempts++;
    //             $response = Http::withHeaders([
    //                 'X-Shopify-Access-Token' => $this->accessToken,
    //             ])->get($url, $nextUrl ? [] : $params);
    
    //             if ($response->successful()) break;
    
    //             $status = $response->status();
    //             if ($status == 429 || ($status >= 500 && $status < 600)) {
    //                 $retryAfter = (int)($response->header('Retry-After') ?? 2);
    //                 sleep(max(2, $retryAfter));
    //                 continue;
    //             }
    //             // Hard fail on other statuses
    //             Log::error('Shopify Order Fetch Failed', ['status' => $status, 'body' => $response->body()]);
    //             return 'Fetch failed!';
    //         }
    
    //         if (!$response || !$response->successful()) {
    //             Log::error('Shopify Order Fetch Failed after retries', [
    //                 'status' => optional($response)->status(),
    //                 'body'   => optional($response)->body(),
    //             ]);
    //             return 'Fetch failed after retries!';
    //         }
    
    //         $orders = $response->json('orders') ?? [];
    
    //         foreach ($orders as $order) {
    //             $rawJson     = json_encode($order);
    //             $orderId     = (string)($order['id'] ?? '');
    //             $createdAt   = isset($order['created_at']) ? Carbon::parse($order['created_at']) : now();
    //             $email       = $order['email'] ?? null;
    //             $customerName = trim(
    //                 ($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? '')
    //             ) ?: 'Unknown Customer';
    
    //             $couponCode  = $order['discount_codes'][0]['code'] ?? null;
    //             $totalPrice  = (float)($order['total_price'] ?? 0);
    //             $totalDisc   = (float)($order['total_discounts'] ?? 0);
    //             $itemsCount  = count($order['line_items'] ?? []);
    
    //             // Pick the most recent fulfillment that has a tracking number/url
    //             $trackingNumber = null;
    //             $trackingUrl    = null;
    //             if (!empty($order['fulfillments'])) {
    //                 // Sort by created_at desc if present
    //                 $fulfillments = $order['fulfillments'];
    //                 usort($fulfillments, function ($a, $b) {
    //                     $aT = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
    //                     $bT = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
    //                     return $bT <=> $aT;
    //                 });
    //                 foreach ($fulfillments as $f) {
    //                     $tn = $f['tracking_number'] ?? null;
    //                     $tu = $f['tracking_urls'][0] ?? ($f['tracking_url'] ?? null);
    //                     if ($tn || $tu) {
    //                         $trackingNumber = $tn;
    //                         $trackingUrl    = $tu;
    //                         break;
    //                     }
    //                 }
    //             }
    
    //             // _anon_id from note_attributes only (Shopify orders don’t have a top-level "attributes")
    //             $anonId = null;
    //             if (!empty($order['note_attributes'])) {
    //                 foreach ($order['note_attributes'] as $attr) {
    //                     if (($attr['name'] ?? '') === '_anon_id') {
    //                         $anonId = $attr['value'] ?? null;
    //                         break;
    //                     }
    //                 }
    //             }
    
    //             // ad_id from landing_site (utm params)
    //             $adId = null;
    //             if (!empty($order['landing_site'])) {
    //                 $query = parse_url($order['landing_site'], PHP_URL_QUERY);
    //                 if ($query) {
    //                     parse_str($query, $utm);
    //                     // priority: ad_id -> utm_content -> utm_term
    //                     $adId = $utm['ad_id'] ?? ($utm['utm_content'] ?? ($utm['utm_term'] ?? null));
    //                 }
    //             }
    
    //             // Optional: pack key line item info for later reporting/debug (requires a JSON column)
    //             $lineItemsBrief = collect($order['line_items'] ?? [])->map(function ($li) {
    //                 return [
    //                     'line_item_id' => $li['id']        ?? null,
    //                     'title'        => $li['title']     ?? null,
    //                     'sku'          => $li['sku']       ?? null,
    //                     'variant_id'   => $li['variant_id']?? null,
    //                     'variant_title'=> $li['variant_title'] ?? null,
    //                     'quantity'     => $li['quantity']  ?? null,
    //                     'price'        => $li['price']     ?? null,
    //                 ];
    //             })->values()->all();
    //             $existsWithRaw = ShopifyOrder::where('order_number', $orderId)
    //             ->whereNotNull('raw_json')
    //             ->exists();
        
    //         if ($existsWithRaw) {
    //             if (app()->runningInConsole()) {
    //                 echo "[{$createdAt}] Skipping order #{$orderId} (raw_json already stored)\n";
    //             }
    //             continue;
    //         }
        
    //         $wasExisting = ShopifyOrder::where('order_number', $orderId)->exists();
        
    //             // Upsert per ORDER (single row per order_number)
    //             $wasExisting = ShopifyOrder::where('order_number', $orderId)->exists();
    
    //             ShopifyOrder::updateOrCreate(
    //                 ['order_number' => $orderId],
    //                 [
    //                     'order_date'      => $createdAt,
    //                     'customer_name'   => $customerName,
    //                     'email_address'   => $email,
    //                     'paid_amount'     => $totalPrice,
    //                     'discount'        => $totalDisc,
    //                     'number_of_items' => $itemsCount,
    //                     'tracking_number' => $trackingNumber,
    //                     'tracking_url'    => $trackingUrl,
    //                     'coupon'          => $couponCode,
    //                     'anon_id'         => $anonId,       // add column in migration if missing
    //                     'ad_id'           => $adId,         // add column in migration if missing
    //                     'raw_json'        => $rawJson,      // add longtext/json column if missing
    //                     'line_items_json' => json_encode($lineItemsBrief), // optional JSON column
    //                     'updated_at'      => now(),
    //                 ]
    //             );
    
    //             if ($wasExisting) { $updated++; } else { $created++; }
    
    //             if (app()->runningInConsole()) {
    //                 echo "[{$createdAt}] " . ($wasExisting ? 'Updated' : 'Created') . " order #{$orderId}\n";
    //             }
    //         }
    
    //         // Pagination via Link header
    //         $nextUrl = null;
    //         $link = $response->header('Link');
    //         if ($link && str_contains($link, 'rel="next"') && preg_match('/<([^>]+)>;\s*rel="next"/', $link, $m)) {
    //             $nextUrl = $m[1];
    //             Log::info('Fetching next page', ['next' => $nextUrl]);
    //         }
    
    //     } while ($nextUrl);
    
    //     Log::info('Shopify orders upsert complete', ['created' => $created, 'updated' => $updated]);
    //     return "Orders fetched. Created: {$created}, Updated: {$updated}.";
    // }


    public function fetchOrders($from_date = null, $to_date = null)
    {
        // Use date range if provided, otherwise default to last 30 days
        $from = $from_date ? Carbon::parse($from_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $to   = $to_date   ? Carbon::parse($to_date)->endOfDay()      : now()->endOfDay();
    
        // Preload order_numbers that already have raw_json filled in this window
        $skipOrderNumbers = ShopifyOrder::query()
            ->whereNotNull('raw_json')
            ->whereBetween('order_date', [$from, $to])
            ->pluck('order_number')
            ->all();
    
        $skip = [];
        foreach ($skipOrderNumbers as $orderNumber) {
            $skip[$orderNumber] = true;
        }
    
        $baseUrl = "https://{$this->shopifyDomain}/admin/api/2025-07/orders.json";
        $params = [
            'status'          => 'any',
            'created_at_min'  => $from->toIso8601String(),
            'created_at_max'  => $to->toIso8601String(),
            'limit'           => 250,
            'fields'          => 'id,name,email,created_at,line_items,customer,fulfillments,total_price,total_discounts,discount_codes,landing_site,note_attributes',
            'order'           => 'created_at asc',
        ];
    
        $nextUrl = null;
        $created = 0;
        $updated = 0;
        $skipped = 0;
    
        do {
            $url = $nextUrl ?: $baseUrl;
    
            // Retry logic
            $attempts = 0; $response = null;
            while ($attempts < 3) {
                $attempts++;
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->accessToken,
                ])->get($url, $nextUrl ? [] : $params);
    
                if ($response->successful()) break;
    
                $status = $response->status();
                if ($status == 429 || ($status >= 500 && $status < 600)) {
                    sleep(max(2, (int)($response->header('Retry-After') ?? 2)));
                    continue;
                }
    
                Log::error('Shopify Order Fetch Failed', ['status' => $status, 'body' => $response->body()]);
                return 'Fetch failed!';
            }
    
            if (!$response || !$response->successful()) {
                Log::error('Shopify Order Fetch Failed after retries', [
                    'status' => optional($response)->status(),
                    'body'   => optional($response)->body(),
                ]);
                return 'Fetch failed after retries!';
            }
    
            $orders = $response->json('orders') ?? [];
    
            foreach ($orders as $order) {
                $orderId     = (string)($order['id'] ?? '');
                $orderNumber = $order['name'] ?? '';
                $createdAt   = isset($order['created_at']) ? Carbon::parse($order['created_at']) : now();
    
                // Skip if already stored with raw_json
                if (isset($skip[$orderId])) {
                    $skipped++;
                    if (app()->runningInConsole()) {
                        echo "[{$createdAt}] Skipping order {$orderId} (already has raw_json)\n";
                    }
                    continue;
                }
    
                $rawJson      = json_encode($order);
                $email        = $order['email'] ?? null;
                $customerName = trim(
                    ($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? '')
                ) ?: 'Unknown Customer';
    
                $couponCode = $order['discount_codes'][0]['code'] ?? null;
                $totalPrice = (float)($order['total_price'] ?? 0);
                $totalDisc  = (float)($order['total_discounts'] ?? 0);
                $itemsCount = count($order['line_items'] ?? []);
    
                // Track fulfillment info
                $trackingNumber = null;
                $trackingUrl    = null;
                if (!empty($order['fulfillments'])) {
                    usort($order['fulfillments'], fn($a, $b) =>
                        strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '')
                    );
                    foreach ($order['fulfillments'] as $f) {
                        $tn = $f['tracking_number'] ?? null;
                        $tu = $f['tracking_urls'][0] ?? ($f['tracking_url'] ?? null);
                        if ($tn || $tu) {
                            $trackingNumber = $tn;
                            $trackingUrl = $tu;
                            break;
                        }
                    }
                }
    
                // Extract anon_id from note_attributes
                $anonId = null;
                foreach ($order['note_attributes'] ?? [] as $attr) {
                    if (($attr['name'] ?? '') === '_anon_id') {
                        $anonId = $attr['value'] ?? null;
                        break;
                    }
                }
    
                // Extract ad_id from landing_site
                $adId = null;
                if (!empty($order['landing_site'])) {
                    if ($query = parse_url($order['landing_site'], PHP_URL_QUERY)) {
                        parse_str($query, $utm);
                        $adId = $utm['ad_id'] ?? ($utm['utm_content'] ?? ($utm['utm_term'] ?? null));
                    }
                }
    
                // Condensed line items
                $lineItemsBrief = collect($order['line_items'] ?? [])->map(fn($li) => [
                    'line_item_id'  => $li['id']            ?? null,
                    'title'         => $li['title']         ?? null,
                    'sku'           => $li['sku']           ?? null,
                    'variant_id'    => $li['variant_id']    ?? null,
                    'variant_title' => $li['variant_title'] ?? null,
                    'quantity'      => $li['quantity']      ?? null,
                    'price'         => $li['price']         ?? null,
                ])->values()->all();
    
                $wasExisting = ShopifyOrder::where('order_number', $orderId)->exists();
    
                $updatedRowCount = ShopifyOrder::where('order_number', $orderId)
                ->whereNull('raw_json') // only update if raw_json is missing
                ->update([
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
                ]);
            
            if ($updatedRowCount > 0) {
                $updated++;
                if (app()->runningInConsole()) {
                    echo "[{$createdAt}] Updated order {$orderNumber}\n";
                }
            } else {
                $skipped++;
                if (app()->runningInConsole()) {
                    echo "[{$createdAt}] Skipped order {$orderNumber} (no match or raw_json already exists)\n";
                }
            }
    
                if ($wasExisting) {
                    $updated++;
                } else {
                    $created++;
                }
    
                if (app()->runningInConsole()) {
                    echo "[{$createdAt}] " . ($wasExisting ? 'Updated' : 'Created') . " order {$orderId}\n";
                }
            }
    
            // Handle pagination
            $nextUrl = null;
            $link = $response->header('Link');
            if ($link && str_contains($link, 'rel="next"') && preg_match('/<([^>]+)>;\s*rel="next"/', $link, $m)) {
                $nextUrl = $m[1];
                Log::info('Fetching next page', ['next' => $nextUrl]);
            }
        } while ($nextUrl);
    
        Log::info('Shopify orders upsert complete', [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped
        ]);
    
        return "Orders fetched. Created: {$created}, Updated: {$updated}, Skipped (already had raw_json): {$skipped}.";
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
