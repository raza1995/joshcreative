<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\ShopifyOrder;
use App\Services\ShopifyService;
class OpenAIService
{
    protected $apiKey;
    protected $model;
    protected $shopifyService;
    protected string $shopifyDomain;
    protected string $accessToken;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyDomain = env('SHOPIFY_STORE_DOMAIN');
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN');
        $this->apiKey = config('services.openai.api_key');
        $this->model = 'gpt-3.5-turbo'; // Using GPT-3.5 Turbo for faster, cost-effective responses
        $this->shopifyService = $shopifyService;
    }

    // Fetch order by number
    private function getOrderByOrderNumber($orderNumber)
    {
        return ShopifyOrder::where('order_number', $orderNumber)->first();
    }

    // Fetch orders by email
    private function getOrdersByEmail($email)
    {
        return ShopifyOrder::where('email_address', $email)->get();
    }

    // Format order details compactly
    private function formatOrderDetails($orders)
    {
        $formattedDetails = '';

        foreach ($orders as $order) {
            $formattedDetails .= "
Order #{$order->order_number} | {$order->product_name} ({$order->number_of_items} items)
- Name: {$order->customer_name}
- Paid: {$order->paid_amount} (Disc: {$order->discount}, Coupon: {$order->coupon})
- Tracking: {$order->tracking_number} | [Track]({$order->tracking_url})
- Date: {$order->order_date}\n\n";
        }

        return $formattedDetails ?: "No orders found.";
    }

    // Simulate learning by caching context
    private function getCachedContext($key)
    {
        return Cache::get($key, '');
    }

    private function setCachedContext($key, $value)
    {
        Cache::put($key, $value, now()->addHours(6)); // Cache for 6 hours
    }

    public function generateReply($customerQuery, $context = '')
    {
        try {
            preg_match('/\d+/', $customerQuery, $orderMatches);
            preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}\b/', $customerQuery, $emailMatches);
    
            $orderNumber = $orderMatches[0] ?? null;
            $email = $emailMatches[0] ?? null;
    
            // Step 1: Fetch data from the local database
            $orders = collect();
            if ($email) {
                $orders = $this->getOrdersByEmail($email);
            } elseif ($orderNumber) {
                $order = $this->getOrderByOrderNumber($orderNumber);
                if ($order) {
                    $orders = collect([$order]);
                }
            }
    
            // Retrieve cached context
            $cacheKey = $email ?: ($orderNumber ? "order_{$orderNumber}" : 'general');
            $previousContext = $this->getCachedContext($cacheKey);
    
            // Step 2: Check if the requested information exists in the database
            $missingInfo = $this->isInformationMissing($customerQuery, $orders);
    
            // Step 3: If missing, fetch from Shopify
            if ($missingInfo) {
                $shopifyData = $this->fetchFromShopify($orderNumber, $email);
                $orders = $orders->merge($shopifyData); // Merge data with existing orders
            }
    
            // Format orders concisely
            $orderContext = $this->formatOrderDetails($orders);
    
            // AI Prompt
            $prompt = "You are a professional customer support assistant. 
                       Respond concisely, using bullet points for clarity. 
                       Be empathetic and helpful, without suggesting returns.
    
                       Previous Info: {$previousContext}
                       Order Info: {$orderContext}
    
                       Customer Query: \"$customerQuery\"";
    
            $response = Http::withToken($this->apiKey)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'n' => 1,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful customer support assistant providing concise, clear responses.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.6, // Slightly reduced for consistency
                    'max_tokens' => 150,   // Limit tokens to reduce costs
                ]);
    
            if ($response->successful()) {
                $reply = $response->json()['choices'][0]['message']['content'] ?? 'Hmm, I’m not sure, but I’m here to help!';
    
                // Update cached context for "learning"
                $this->setCachedContext($cacheKey, "{$previousContext}\n{$customerQuery}: {$reply}");
    
                return $reply;
            } else {
                Log::error('OpenAI API Error: ' . $response->body());
                return 'Oops, something went wrong. Could you try again?';
            }
        } catch (\Exception $e) {
            Log::error('Exception in OpenAIService: ' . $e->getMessage());
            return 'Oh no! I hit a snag. Mind trying again?';
        }
    }
    
    // Check if the requested information exists in the database
    private function isInformationMissing($customerQuery, $orders)
    {
        $keywords = [
            'shipping' => ['shipping_address', 'tracking_number', 'tracking_url','location'],
            'payment' => ['payment_status', 'financial_status'],
            'status' => ['fulfillment_status', 'order_status'],
        ];
    
        foreach ($keywords as $key => $fields) {
            if (stripos($customerQuery, $key) !== false) {
                foreach ($fields as $field) {
                    if (!$orders->pluck($field)->filter()->isNotEmpty()) {
                        return true; // Information missing
                    }
                }
            }
        }
    
        return false; // All required info is present
    }
    public function getAccessToken()
{
    return $this->accessToken;
}

public function getShopifyDomain()
{
    return $this->shopifyDomain;
}
    // Fetch data from Shopify if not available in the database
    private function fetchFromShopify($orderNumber = null, $email = null)
{
    try {
        $endpoint = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json";
        $params = [
            'status' => 'any',  // Include all orders (open, closed, etc.)
            'limit' => 1        // Limit to the first order to reduce data load
        ];

        if ($orderNumber) {
            $params['name'] = $orderNumber;  // Correct way to query by order number
        } elseif ($email) {
            $params['email'] = $email;       // Query by email address
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->get($endpoint, $params);

        if ($response->successful()) {
            $orders = collect($response->json()['orders'] ?? []);
            if ($orders->isEmpty()) {
                Log::info("No orders found for OrderNumber: {$orderNumber}, Email: {$email}");
            }
            return $orders;
        } else {
            Log::error('Shopify API Error: ' . $response->body());
            return collect();
        }
    } catch (\Exception $e) {
        Log::error('Exception in Shopify API: ' . $e->getMessage());
        return collect();
    }
}

    public function generateSummary($emailContent)
{
    try {
        $prompt = "Summarize the following customer email, focusing on the main issue or concern in a professional tone without unnecessary details.

        Email Content:
        \"$emailContent\"";

        $response = Http::withToken($this->apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert in summarizing customer service emails concisely.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.5,
                'max_tokens' => 150,
            ]);

        if ($response->successful()) {
            return $response->json()['choices'][0]['message']['content'] ?? 'Summary not available.';
        } else {
            Log::error('OpenAI API Summary Error: ' . $response->body());
            return 'Error generating summary.';
        }
    } catch (\Exception $e) {
        Log::error('Exception in OpenAIService (Summary): ' . $e->getMessage());
        return 'Error communicating with AI for summary.';
    }
}

}
