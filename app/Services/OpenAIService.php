<?php

namespace App\Services;

use App\Models\Conversation;
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
        $this->model = 'gpt-4o-mini-2024-07-18'; // Using GPT-3.5 Turbo for faster, cost-effective responses
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
                $orderNumber = $order['order_number'] ?? $order->order_number ?? null;
                $productName = $order['product_name'] ?? $order->product_name ?? null;
                $numberOfItems = $order['number_of_items'] ?? $order->number_of_items ?? null;
                $customerName = $order['customer_name'] ?? $order->customer_name ?? null;
                $email = $order['email_address'] ?? $order->email_address ?? null;
                $paidAmount = $order['paid_amount'] ?? $order->paid_amount ?? null;
                $trackingNumber = $order['tracking_number'] ?? $order->tracking_number ?? null;
                $trackingUrl = $order['tracking_url'] ?? $order->tracking_url ?? null;
                $orderDate = $order['order_date'] ?? $order->order_date ?? null;
    
                $formattedDetails .= "
                Order #{$orderNumber} | {$productName} ({$numberOfItems} items)
                - Name: {$customerName}
                - Email: {$email}
                - Paid: {$paidAmount}
                - Tracking: {$trackingNumber} | [Track]({$trackingUrl})
                - Date: {$orderDate}\n\n";
            }
    
            return $formattedDetails ?: "No orders found.";
        }
    
        private function detectSpecificRequest($query)
        {
            $patterns = [
                'email' => '/\b(email|e-mail|mail address)\b/i',
                'name'  => '/\b(name|first name|last name|customer name)\b/i',
                'phone' => '/\b(phone|contact number|mobile)\b/i',
            ];
    
            foreach ($patterns as $key => $pattern) {
                if (preg_match($pattern, $query)) {
                    return $key; // e.g., 'name', 'email', etc.
                }
            }
            return null;
        }
    
        // Retrieve cached conversation context
        private function getCachedContext($key)
        {
            $cachedData = Cache::get($key, []);
    
            return is_array($cachedData) ? array_merge([
                'previousContext' => '',
                'lastOrderNumber' => null,
                'lastEmail' => null,
                'conversationLog' => [],
            ], $cachedData) : [];
        }
    
        // Update cached context for conversation continuity
        private function setCachedContext($key, $data)
        {
            Cache::put($key, $data, now()->addHours(6)); // Cache for 6 hours
        }
    
        public function getConversationLog($key)
        {
            $contextData = $this->getCachedContext($key);
            return $contextData['conversationLog'] ?? [];
        }

        private function detectUpdateRequest($query)
{
    $patterns = [
        '/\b(updated data|refresh data|latest data|get recent data|fetch latest|update info|refresh info|current status|latest status)\b/i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $query)) {
            return true; // Update request detected
        }
    }
    return false; // No update request
}
private function detectLastRecordRequest($query)
{
    $patterns = [
        '/\b(last record|previous order|recent order|show last|latest order|last details)\b/i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $query)) {
            return true; // Request for last record detected
        }
    }
    return false;
}

    
public function generateReply($customerQuery)
{
    preg_match('/\d+/', $customerQuery, $orderMatches);
    preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}\b/', $customerQuery, $emailMatches);

    $orderNumber = $orderMatches[0] ?? null;
    $email = $emailMatches[0] ?? null;

    $cacheKey = $email ?: ($orderNumber ? "order_{$orderNumber}" : 'general');
    $contextData = $this->getCachedContext($cacheKey);

    $orderNumber = $orderNumber ?? $contextData['lastOrderNumber'];
    $email = $email ?? $contextData['lastEmail'];

    $isUpdateRequest = $this->detectUpdateRequest($customerQuery);
    $isLastRecordRequest = $this->detectLastRecordRequest($customerQuery); // Detect last record request
    $requestType = $this->detectSpecificRequest($customerQuery);

    $orders = collect();

    if ($isLastRecordRequest) {
        // Fetch the last order from conversation log
        $lastOrder = collect($contextData['conversationLog'])->last(function ($entry) {
            return isset($entry['orderDetails']);
        });

        if ($lastOrder) {
            $orderDetails = $lastOrder['orderDetails'];
            return "The last order details associated with your account are as follows:
- **Order #{$orderDetails['order_number']}**
- **Name:** " . ($orderDetails['customer_name'] ?? 'Not available') . "
- **Email:** " . ($orderDetails['email'] ?? 'Not available') . "
- **Paid:** " . ($orderDetails['paid_amount'] ?? 'Not available') . "
- **Tracking:** " . ($orderDetails['tracking_number'] ?? 'Not available') . " | [Track](" . ($orderDetails['tracking_url'] ?? '#') . ")
- **Date:** " . ($orderDetails['order_date'] ?? 'Not available');
        } else {
            return "I couldn't find any previous order records in the conversation.";
        }
    }
    if ($isUpdateRequest) {
        $orders = $this->fetchFromShopify($orderNumber, $email);
    } else {
        if ($email) {
            $orders = $this->getOrdersByEmail($email);
        } elseif ($orderNumber) {
            $order = $this->getOrderByOrderNumber($orderNumber);
            if ($order) {
                $orders = collect([$order]);
            }
        }

        if ($orders->isEmpty()) {
            $shopifyData = $this->fetchFromShopify($orderNumber, $email);
            $orders = $orders->merge($shopifyData);
        }
    }

    if ($requestType && $orders->isNotEmpty()) {
        $order = $orders->first();
        $specificDetail = $order[$requestType] ?? 'Information not available.';
        return "The customer's {$requestType} is: {$specificDetail}";
    }

    $orderContext = $this->formatOrderDetails($orders);
    $userIdentifier = $email ?: 'guest';

    $prompt = "You are a smart customer support assistant:
    - Provide concise responses.
    - Retrieve requested details (order status, tracking, name, email).
    - Fetch fresh data if the user asks for updates.

    Previous Conversation: {$contextData['previousContext']}
    Order Info: {$orderContext}
    Customer Query: \"$customerQuery\"

    Answer accordingly.";

    $response = Http::withToken($this->apiKey)
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an AI assistant. Answer based on the provided data.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.8,
            'max_tokens' => 300,
        ]);

    if ($response->successful()) {
        $reply = $response->json()['choices'][0]['message']['content'] ?? 'I’m not sure, but I’m here to help!';

        // Save conversation log with order details
        $contextData['conversationLog'][] = [
            'timestamp' => now()->toDateTimeString(),
            'user' => $customerQuery,
            'ai' => $reply,
            'orderDetails' => $orders->first() ?? null, // Save the order details
        ];

        $this->setCachedContext($cacheKey, [
            'previousContext' => "{$contextData['previousContext']}\n{$customerQuery}: {$reply}",
            'lastOrderNumber' => $orderNumber,
            'lastEmail' => $email,
            'conversationLog' => $contextData['conversationLog'],
        ]);

        return $reply;
    } else {
        Log::error('OpenAI API Error: ' . $response->body());
        return 'Oops, something went wrong. Try again!';
    }
}

    
    

    
    // Check if the requested information exists in the database
    private function isInformationMissing($customerQuery, $orders)
    {
        $keywords = [
            'shipping' => ['shipping_address', 'tracking_number', 'tracking_url', 'location'],
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
            $params['id'] = $orderNumber;  // Correct way to query by order number
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
private function saveConversation($userIdentifier, $orderNumber, $conversationLog)
{
    Conversation::updateOrCreate(
        ['user_identifier' => $userIdentifier, 'order_number' => $orderNumber],
        ['conversation_data' => $conversationLog]
    );
}
}
