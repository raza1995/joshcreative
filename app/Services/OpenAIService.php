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

    
        /**
         * Main entry point to generate a reply for a given user query.
         * @param  string $customerQuery
         * @return string  The AI-generated response
         */
        public function generateReply(string $customerQuery): string
        {
            // 1. Parse Order Number or Email
            $orderNumber = $this->extractOrderNumber($customerQuery);
            $email = $this->extractEmail($customerQuery);
    
            // 2. Determine a caching key
            $cacheKey = $this->determineCacheKey($orderNumber, $email);
    
            // 3. Retrieve prior context
            $contextData = $this->getCachedContext($cacheKey);
    
            // 4. Persist newly parsed identifiers if missing from the query
            $orderNumber = $orderNumber ?? $contextData['lastOrderNumber'];
            $email       = $email       ?? $contextData['lastEmail'];
    
            // 5. Detect special requests
            $isUpdateRequest      = $this->detectUpdateRequest($customerQuery);
            $isLastRecordRequest  = $this->detectLastRecordRequest($customerQuery);
            $specificRequestField = $this->detectSpecificRequest($customerQuery);
    
            // 6. Handle "show me the last record" scenario
            if ($isLastRecordRequest) {
                $lastOrderResponse = $this->handleLastRecordRequest($contextData);
                if ($lastOrderResponse) {
                    return $lastOrderResponse;
                }
            }
    
            // 7. Retrieve or update data
            $orders = collect();
    
            if ($isUpdateRequest) {
                // Force fetch from Shopify
                $orders = $this->fetchFromShopify($orderNumber, $email);
            } else {
                // Attempt to get from local DB
                $orders = $this->fetchFromDatabase($orderNumber, $email);
    
                // If DB is empty, fallback to Shopify
                if ($orders->isEmpty()) {
                    $orders = $this->fetchFromShopify($orderNumber, $email);
                }
            }
    
            // 8. If user specifically wants e.g. "email" or "name", return that
            if ($specificRequestField && $orders->isNotEmpty()) {
                $fieldValue = $orders->first()[$specificRequestField] ?? 'Information not available.';
                return "Requested info ({$specificRequestField}): {$fieldValue}";
            }
    
            // 9. Format Order Details or fallback if no data
            $orderContext = $this->formatOrderDetails($orders);
    
            // 10. Build prompt for OpenAI
            $prompt = $this->buildPrompt($contextData, $orderContext, $customerQuery);
    
            // 11. Call OpenAI
            $reply = $this->callOpenAI($prompt);
    
            // 12. Store conversation logs in cache
            $this->storeInCache($cacheKey, $customerQuery, $reply, $orders->first(), $orderNumber, $email, $contextData);
    
            // 13. Return the AI response
            return $reply;
        }
    
        /**
         * Extract an order number (digits) from the query.
         */
        private function extractOrderNumber(string $query): ?string
        {
            preg_match('/\d+/', $query, $matches);
            return $matches[0] ?? null;
        }
    
        /**
         * Extract an email from the query.
         */
        private function extractEmail(string $query): ?string
        {
            preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,4}\b/i', $query, $matches);
            return $matches[0] ?? null;
        }
    
        /**
         * Determine the cache key based on email or order number.
         */
        private function determineCacheKey(?string $orderNumber, ?string $email): string
        {
            if ($email) {
                return $email;
            }
            if ($orderNumber) {
                return "order_{$orderNumber}";
            }
            return 'general';
        }
    
        /**
         * Retrieve cached context data. If none, return default structure.
         */
        private function getCachedContext(string $key): array
        {
            $cachedData = Cache::get($key, []);
            return array_merge([
                'previousContext' => '',
                'lastOrderNumber' => null,
                'lastEmail'       => null,
                'conversationLog' => [],
            ], (array) $cachedData);
        }
    
        /**
         * Store updated context in cache.
         */
        private function setCachedContext(string $key, array $data): void
        {
            // Cache for 6 hours
            Cache::put($key, $data, now()->addHours(6));
        }
    
        /**
         * Detect a user asking for updated data from Shopify.
         */
        private function detectUpdateRequest(string $query): bool
        {
            $patterns = [
                '/\b(updated data|refresh data|latest data|get recent data|fetch latest|update info|refresh info|current status|latest status)\b/i'
            ];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $query)) {
                    return true;
                }
            }
            return false;
        }
    
        /**
         * Detect a user asking for the last record in the conversation.
         */
        private function detectLastRecordRequest(string $query): bool
        {
            $patterns = [
                '/\b(last record|previous order|recent order|show last|latest order|last details)\b/i'
            ];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $query)) {
                    return true;
                }
            }
            return false;
        }
    
        /**
         * Detect a user asking for a specific field (email, name, phone, etc.).
         */
        private function detectSpecificRequest(string $query): ?string
        {
            $patterns = [
                'email_address' => '/\b(email|e-mail|mail address)\b/i',
                'customer_name' => '/\b(name|first name|last name|customer name)\b/i',
                'phone'         => '/\b(phone|contact number|mobile)\b/i',
            ];
    
            foreach ($patterns as $field => $pattern) {
                if (preg_match($pattern, $query)) {
                    return $field;
                }
            }
    
            return null;
        }
    
        /**
         * Handle last-record request by looking at the conversation log.
         * Returns a string if last record found, otherwise null.
         */
        private function handleLastRecordRequest(array $contextData): ?string
        {
            $lastOrder = collect($contextData['conversationLog'])->last(function ($entry) {
                return isset($entry['orderDetails']);
            });
    
            if ($lastOrder && isset($lastOrder['orderDetails'])) {
                $o = $lastOrder['orderDetails'];
                return "Here is your last referenced order:\n".
                       "- **Order #**: {$o['order_number']}\n".
                       "- **Name**: ".($o['customer_name'] ?? 'N/A')."\n".
                       "- **Email**: ".($o['email_address'] ?? 'N/A')."\n".
                       "- **Paid**: ".($o['paid_amount'] ?? 'N/A')."\n".
                       "- **Tracking**: ".($o['tracking_number'] ?? 'N/A')." | [Track](".($o['tracking_url'] ?? '#').")\n".
                       "- **Date**: ".($o['order_date'] ?? 'N/A')."\n";
            }
    
            return null;
        }
    
        /**
         * Fetch orders from the local database (by email or order number).
         */
        private function fetchFromDatabase(?string $orderNumber, ?string $email)
        {
            if ($email) {
                return ShopifyOrder::where('email_address', $email)->get();
            } elseif ($orderNumber) {
                $order = ShopifyOrder::where('order_number', $orderNumber)->first();
                return $order ? collect([$order]) : collect();
            }
            return collect();
        }
    
        /**
         * Fetch data from Shopify if not available or if refresh is requested.
         */
        private function fetchFromShopify(?string $orderNumber = null, ?string $email = null)
        {
            try {
                $endpoint = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json";
                $params   = [
                    'status' => 'any',   // Include all orders
                    'limit'  => 5        // fetch up to 5 for demonstration
                ];
    
                if ($orderNumber) {
                    // NOTE: Actual Shopify typically uses "name" to query by # or "id" for the internal ID
                    // For demonstration, we just pass 'id' param, but you might need to revise
                    $params['name'] = $orderNumber;
                } elseif ($email) {
                    $params['email'] = $email;
                }
    
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $this->accessToken,
                    'Content-Type'           => 'application/json',
                ])->get($endpoint, $params);
    
                if ($response->successful()) {
                    $orders = collect($response->json()['orders'] ?? []);
                    if ($orders->isEmpty()) {
                        Log::info("No orders found from Shopify for number={$orderNumber}, email={$email}");
                    }
                    return $orders;
                } else {
                    Log::error('Shopify API Error: ' . $response->body());
                    return collect();
                }
            } catch (\Exception $e) {
                Log::error('Shopify API Exception: ' . $e->getMessage());
                return collect();
            }
        }
    
        /**
         * Format multiple orders into a concise string.
         */
        private function formatOrderDetails($orders): string
        {
            if ($orders->isEmpty()) {
                return "No orders found.";
            }
    
            $formatted = '';
            foreach ($orders as $order) {
                $orderNumber    = $order['order_number']     ?? $order->order_number     ?? null;
                $productName    = $order['product_name']      ?? $order->product_name      ?? null;
                $numItems       = $order['number_of_items']   ?? $order->number_of_items   ?? null;
                $customerName   = $order['customer_name']     ?? $order->customer_name     ?? null;
                $email          = $order['email_address']     ?? $order->email_address     ?? null;
                $paidAmount     = $order['paid_amount']       ?? $order->paid_amount       ?? null;
                $trackingNumber = $order['tracking_number']   ?? $order->tracking_number   ?? null;
                $trackingUrl    = $order['tracking_url']      ?? $order->tracking_url      ?? null;
                $orderDate      = $order['order_date']        ?? $order->order_date        ?? null;
    
                $formatted .= "Order #{$orderNumber} | {$productName} ({$numItems} items)\n"
                            ." - Name: {$customerName}\n"
                            ." - Email: {$email}\n"
                            ." - Paid: {$paidAmount}\n"
                            ." - Tracking: {$trackingNumber} | [Track]({$trackingUrl})\n"
                            ." - Date: {$orderDate}\n\n";
            }
            return $formatted;
        }
    
        /**
         * Build a message prompt to send to OpenAI.
         */
        private function buildPrompt(array $contextData, string $orderContext, string $customerQuery): string
        {
            return <<<PROMPT
    You are an internal support assistant. 
    You have the following context from previous conversation:
    
    {$contextData['previousContext']}
    
    Orders found / relevant info:
    {$orderContext}
    
    Customer (internal user) just asked: "{$customerQuery}"
    
    Provide a concise answer focusing on what was requested or relevant info needed.
    PROMPT;
        }
    
        /**
         * Call OpenAI with the prompt and return the reply.
         */
        private function callOpenAI(string $prompt): string
        {
            $response = Http::withToken($this->apiKey)->post(
                'https://api.openai.com/v1/chat/completions',
                [
                    'model'       => $this->model,
                    'messages'    => [
                        ['role' => 'system', 'content' => 'You are an AI assistant.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 300,
                ]
            );
    
            if ($response->successful()) {
                return $response->json()['choices'][0]['message']['content'] 
                       ?? 'No response from AI.';
            }
    
            Log::error('OpenAI API Error: ' . $response->body());
            return 'Oops, something went wrong with OpenAI. Try again.';
        }
    
        /**
         * Store the conversation log and updated context data in the cache.
         */
        private function storeInCache(
            string $cacheKey,
            string $customerQuery,
            string $reply,
            $firstOrder,
            ?string $orderNumber,
            ?string $email,
            array $contextData
        ): void {
            // Log this conversation turn
            $conversationLog = $contextData['conversationLog'] ?? [];
            $conversationLog[] = [
                'timestamp'    => now()->toDateTimeString(),
                'user'         => $customerQuery,
                'ai'           => $reply,
                'orderDetails' => $firstOrder ?? null,
            ];
    
            $updatedContext = [
                'previousContext' => $contextData['previousContext']."\nUser: {$customerQuery}\nAI: {$reply}",
                'lastOrderNumber' => $orderNumber,
                'lastEmail'       => $email,
                'conversationLog' => $conversationLog,
            ];
    
            $this->setCachedContext($cacheKey, $updatedContext);
        }
    
        // Additional utility methods if needed
       
    
    

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
