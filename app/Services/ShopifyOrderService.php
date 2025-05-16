<?php


namespace App\Services;

use App\Models\ShopifyMycoleanOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShopifyOrderService
{

    protected  $shopifyDomain;
    protected  $accessToken;

    public function __construct()
    {
        $this->shopifyDomain = env('SHOPIFY_STORE_DOMAIN');
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN');
    }
    public function fetchMonthlyOrders($startDate, $endDate)
    {
        $url = "https://" . env('SHOPIFY_STORE_DOMAIN') . "/admin/api/2024-01/orders.json";
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN')
        ])->get($url, [
            'status' => 'any',
            'created_at_min' => $startDate,
            'created_at_max' => $endDate,
            'limit' => 250,
        ]);

        return $response->json()['orders'] ?? [];
    }

    // public function fetchAndSaveOrders($from_date = null, $to_date = null)
    // {
    //     if (!$from_date || !$to_date) {
    //         $from_date = now()->subDays(30)->format('Y-m-d');
    //         $to_date = now()->format('Y-m-d');
    //     }
    
    //     $base_url = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json";
    //     $params = [
    //         'status' => 'any',
    //         'created_at_min' => $from_date . 'T00:00:00Z',
    //         'created_at_max' => $to_date . 'T23:59:59Z',
    //         'limit' => 250,
    //         'fields' => 'id,created_at,line_items'
    //     ];
    
    //     // Build cache of existing composite keys to skip duplicates
    
    
    //     do {
    //         Log::info('📦 Fetching Shopify Orders', ['params' => $params]);
    
    //         $response = Http::withHeaders([
    //             'X-Shopify-Access-Token' => $this->accessToken,
    //             'Content-Type' => 'application/json',
    //         ])->get($base_url, $params);
    
    //         if (!$response->successful()) {
    //             Log::error('❌ Failed to fetch Shopify orders', ['response' => $response->body()]);
    //             return 'Failed to fetch orders!';
    //         }
    
    //         $orders = $response->json()['orders'] ?? [];
    
    //         foreach ($orders as $order) {
    //             foreach ($order['line_items'] as $item) {
    //                 $compositeKey = $order['id'] . '-' . $item['variant_id'];
    
    //                 if (isset($existingKeys[$compositeKey])) {
    //                     Log::info("⏭ Duplicate skipped: {$compositeKey}");
    //                     continue;
    //                 }
    
    //                 try {
    //                    ShopifyMycoleanOrder::create([
    //                         'order_id'      => $order['id'],
    //                         'product_title' => $item['title'] ?? null,
    //                         'variant_id'    => $item['variant_id'] ?? null,
    //                         'quantity'      => $item['quantity'] ?? 0,
    //                         'total_price'   => $item['price'] * $item['quantity'],
    //                         'order_date'    => Carbon::parse($order['created_at'])->toDateString(),
    //                         'raw_json'      => json_encode($order),
    //                     ]);
    
    //                     Log::info("✅ Saved order: {$compositeKey}");
    //                 } catch (\Exception $e) {
    //                     Log::error("❌ Save failed: {$compositeKey}", ['error' => $e->getMessage()]);
    //                 }
    //             }
    //         }
    
    //         // Handle pagination
    //         $next_url = null;
    //         $link_header = $response->header('Link');
    //         if ($link_header && strpos($link_header, 'rel="next"') !== false) {
    //             preg_match('/<([^>]+)>; rel="next"/', $link_header, $matches);
    //             if (isset($matches[1])) {
    //                 $next_url = $matches[1];
    //                 $parsed_url = parse_url($next_url);
    //                 parse_str($parsed_url['query'], $params);
    //                 $base_url = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json";
    //             }
    //         }
    
    //     } while ($next_url);
    
    //     return '✅ Orders fetched and saved (strict model alignment).';
    // }
    public function fetchAndSaveOrders($from_date = null, $to_date = null)
{
    // Default to last 2 days if no date is provided
    if (!$from_date || !$to_date) {
        $from_date = now()->subDays(10)->format('Y-m-d');
        $to_date = now()->format('Y-m-d');
    }

    // Shopify API base URL
    $base_url = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json";
    $params = [
        'status' => 'any',
        'created_at_min' => now()->subMonths(2)->format('Y-m-d') . 'T00:00:00Z',
        'created_at_max' => now()->format('Y-m-d') . 'T23:59:59Z',
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
        Log::info('Fetched orders from Shopify', ['orders' => $orders]);
        // Process each order and save it to the database
        foreach ($orders as $order) {
            $customerName = !empty($order['customer']['first_name']) || !empty($order['customer']['last_name'])
                ? trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? ''))
                : 'Unknown Customer';

            // Get discount code if available
            $couponCode = $order['discount_codes'][0]['code'] ?? null;

            foreach ($order['line_items'] as $item) {
                // Check if the order already exists with the same data
                $existingOrder = ShopifyMycoleanOrder::where('order_id', $order['id'])
                    ->exists();

                if (!$existingOrder) {
                    // Insert new order only if no duplicate record exists
                    ShopifyMycoleanOrder::create([
                        'order_id'      => $order['id'],
                        'product_title' => $item['title'] ?? null,
                        'variant_id'    => $item['variant_id'] ?? null,
                        'quantity'      => $item['quantity'] ?? 0,
                        'total_price'   => $item['price'] * $item['quantity'],
                        'order_date'    => Carbon::parse($order['created_at'])->toDateString(),
                        'raw_json'      => json_encode($order),
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

    return 'All orders from the last 2 days fetched and stored successfully!';
}
}




// JSONDECODE RAW DATA

// $order = ShopifyMycoleanOrder::latest()->first();
// $data = json_decode($order->raw_json, true);
// $lineItems = $data['line_items'] ?? [];
// $discountCodes = $data['discount_codes'] ?? [];

// public function getDecodedJsonAttribute()
// {
//     return json_decode($this->raw_json, true);
// }
