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
        // Check if $order is an array or object
        $orderNumber = is_array($order) ? ($order['order_number'] ?? null) : ($order->order_number ?? null);
        $productName = is_array($order) ? ($order['product_name'] ?? null) : ($order->product_name ?? null);
        $numberOfItems = is_array($order) ? ($order['number_of_items'] ?? null) : ($order->number_of_items ?? null);
        $customerName = is_array($order) ? ($order['customer_name'] ?? null) : ($order->customer_name ?? null);
        $paidAmount = is_array($order) ? ($order['paid_amount'] ?? null) : ($order->paid_amount ?? null);
        $discount = is_array($order) ? ($order['discount'] ?? null) : ($order->discount ?? null);
        $coupon = is_array($order) ? ($order['coupon'] ?? null) : ($order->coupon ?? null);
        $trackingNumber = is_array($order) ? ($order['tracking_number'] ?? null) : ($order->tracking_number ?? null);
        $trackingUrl = is_array($order) ? ($order['tracking_url'] ?? null) : ($order->tracking_url ?? null);
        $orderDate = is_array($order) ? ($order['order_date'] ?? null) : ($order->order_date ?? null);

        $formattedDetails .= "
        Order #{$orderNumber} | {$productName} ({$numberOfItems} items)
        - Name: {$customerName}
        - Paid: {$paidAmount} (Disc: {$discount}, Coupon: {$coupon})
        - Tracking: {$trackingNumber} | [Track]({$trackingUrl})
        - Date: {$orderDate}\n\n";
    }

    return $formattedDetails ?: "No orders found.";
}


    
    // Retrieve cached conversation context
    private function getCachedContext($key)
    {
        $cachedData = Cache::get($key, '');
    
        // If cached data is a string, convert it to an array (backward compatibility)
        if (is_string($cachedData)) {
            return [
                'previousContext' => $cachedData,
                'lastOrderNumber' => null,
                'lastEmail' => null,
                'conversationLog' => [], // Initialize conversation log
            ];
        }
    
        // If already an array (new format), ensure all keys exist
        return $cachedData + [
            'previousContext' => '',
            'lastOrderNumber' => null,
            'lastEmail' => null,
            'conversationLog' => [],
        ];
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

    
public function generateReply($customerQuery)
{
    preg_match('/\d+/', $customerQuery, $orderMatches);
    preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}\b/', $customerQuery, $emailMatches);

    $orderNumber = $orderMatches[0] ?? null;
    $email = $emailMatches[0] ?? null;

    // Retrieve cached context
    $cacheKey = $email ?: ($orderNumber ? "order_{$orderNumber}" : 'general');
    $contextData = $this->getCachedContext($cacheKey);

    // Fallback to previous data if no new info is provided
 // Fallback to previous data if no new info is provided
$orderNumber = $orderNumber ?? (is_array($contextData) ? $contextData['lastOrderNumber'] ?? null : null);
$email = $email ?? (is_array($contextData) ? $contextData['lastEmail'] ?? null : null);


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

    // Step 2: Check if the requested information exists in the database
    $missingInfo = $this->isInformationMissing($customerQuery, $orders);

    // Step 3: If missing, fetch from Shopify
    if ($missingInfo) {
        $shopifyData = $this->fetchFromShopify($orderNumber, $email);
        $orders = $orders->merge($shopifyData); // Merge data with existing orders
    }

    // Format orders concisely
    $orderContext = $this->formatOrderDetails($orders);
    $userIdentifier = $email ?: 'guest';

    $conversations = Conversation::where('user_identifier', $userIdentifier)
        ->orWhere('order_number', $orderNumber)
        ->get();

$conversationLog = $conversation->conversation_data ?? [];
    // AI Prompt
    $prompt = "You are an intelligent customer support assistant designed to:
- Provide concise, helpful responses to customer inquiries.
- Answer only relevant questions based on the provided order data.
- Write professional, empathetic emails when requested, tailored to the customer's issue.
- Be polite, solution-oriented, and avoid suggesting returns unless absolutely necessary.

DATA FLOW:
- Use 'Order Info' to understand the context of the order.
- Refer to 'Previous Info' for conversation history to maintain continuity.
- If the user requests an email draft, format it professionally with a friendly tone.

Previous Info:
{$contextData['previousContext']}

Order Info:
{$orderContext}

Customer Query:
\"$customerQuery\"

Your Task:
- Answer concisely if it's a direct question.
- Draft an email if the user asks for an email.
- Ignore irrelevant questions unrelated to customer support.";


$response = Http::withToken($this->apiKey)
    ->post('https://api.openai.com/v1/chat/completions', [
        'model' => $this->model,
        'n' => 1,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a highly intelligent customer support assistant. Provide concise responses, answer relevant questions, and draft emails based on order data when requested.'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.8,
        'max_tokens' => 300,  // Increased for more detailed responses when drafting emails
    ]);


    if ($response->successful()) {
        $reply = $response->json()['choices'][0]['message']['content'] ?? 'Hmm, I’m not sure, but I’m here to help!';

        // Append the new conversation to the log
        $contextData['conversationLog'][] = [
            'timestamp' => now()->toDateTimeString(),
            'user' => $customerQuery,
            'ai' => $reply,
        ];
        $this->saveConversation($userIdentifier, $orderNumber, $contextData['conversationLog']);
        // Update cached context with new conversation data
        $this->setCachedContext($cacheKey, [
            'previousContext' => "{$contextData['previousContext']}\n{$customerQuery}: {$reply}",
            'lastOrderNumber' => $orderNumber,
            'lastEmail' => $email,
            'conversationLog' => $contextData['conversationLog'], // Save the updated log
        ]);

        return $reply;
    } else {
        Log::error('OpenAI API Error: ' . $response->body());
        return 'Oops, something went wrong. Could you try again?';
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
private function saveConversation($userIdentifier, $orderNumber, $conversationLog)
{
    Conversation::updateOrCreate(
        ['user_identifier' => $userIdentifier, 'order_number' => $orderNumber],
        ['conversation_data' => $conversationLog]
    );
}
}
