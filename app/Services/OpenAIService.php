<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ShopifyOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Services\ShopifyService;

class OpenAIService
{
    protected string $apiKey;
    protected string $model;
    protected ShopifyService $shopifyService;
    protected string $shopifyDomain;
    protected string $accessToken;

    // ~~~~~~~~~ CONFIG CONSTANTS ~~~~~~~~~
    /**
     * The maximum character length of conversation context
     * before we attempt to summarize it.
     */
    private const MAX_CONTEXT_LENGTH = 2000;

    public function __construct(ShopifyService $shopifyService)
    {
        // Load env config
        $this->shopifyDomain = env('SHOPIFY_STORE_DOMAIN');
        $this->accessToken   = env('SHOPIFY_ACCESS_TOKEN');
        $this->apiKey        = config('services.openai.api_key');
        $this->model         = 'gpt-3.5-turbo';

        $this->shopifyService = $shopifyService;
    }

    /**
     * Primary function to handle a user query, fetch the data needed,
     * and produce an AI-based reply (potentially Slack-formatted).
     */
    public function generateReply(string $customerQuery, bool $useSlackBlocks = false): string|array
    {
        // 1. Parse user’s query for an order number or email
        $orderNumber = $this->extractOrderNumber($customerQuery);
        $email       = $this->extractEmail($customerQuery);

        // 2. Determine the cache key for conversation continuity
        $cacheKey    = $this->determineCacheKey($orderNumber, $email);

        // 3. Load prior conversation context from cache
        $contextData = $this->getCachedContext($cacheKey);

        // Summarize the old context if it’s too large
        $contextData = $this->checkConversationLengthAndSummarize($contextData);

        // 4. If new query didn't have them, use last known order/email
        $orderNumber = $orderNumber ?: $contextData['lastOrderNumber'];
        $email       = $email       ?: $contextData['lastEmail'];

        // 5. Check for special requests
        $isUpdateRequest      = $this->detectUpdateRequest($customerQuery);
        $isLastRecordRequest  = $this->detectLastRecordRequest($customerQuery);
        $specificRequestField = $this->detectSpecificRequest($customerQuery);

        // 6. If user asked: "show me the last record"
        if ($isLastRecordRequest) {
            $lastOrderResponse = $this->handleLastRecordRequest($contextData, $useSlackBlocks);
            if ($lastOrderResponse) {
                return $lastOrderResponse;
            }
        }

        // 7. Fetch data (from local DB or Shopify)
        $orders = collect();

        if ($isUpdateRequest) {
            // Force refresh from Shopify
            $orders = $this->fetchFromShopify($orderNumber, $email);
        } else {
            // Check local DB first
            $orders = $this->fetchFromDatabase($orderNumber, $email);

            // If no local records, fallback to Shopify
            if ($orders->isEmpty()) {
                $orders = $this->fetchFromShopify($orderNumber, $email);
            }
        }

        // 8. If the user specifically wants "email", "customer_name", etc.
        if ($specificRequestField && $orders->isNotEmpty()) {
            $fieldValue = $orders->first()[$specificRequestField] ?? 'Information not available.';
            return "Requested info ({$specificRequestField}): {$fieldValue}";
        }

        // 9. If multiple orders are found, provide a summary & prompt the user
        if ($orders->count() > 1 && !$orderNumber) {
            // Return a short summary or Slack block with multiple orders
            return $this->handleMultipleOrders($orders, $useSlackBlocks);
        }

        // 10. Format the orders found (or show empty if none)
        $orderContext = $this->formatOrderDetails($orders);

        // 11. Build a prompt that includes conversation context + user query + order data
        $prompt = $this->buildPrompt($contextData, $orderContext, $customerQuery);

        // 12. Call OpenAI
        $reply = $this->callOpenAI($prompt);

        // 13. Store conversation log in cache for continuity
        $this->storeInCache($cacheKey, $customerQuery, $reply, $orders->first(), $orderNumber, $email, $contextData);

        // 14. Return either raw text or Slack blocks
        if ($useSlackBlocks) {
            return $this->buildSlackBlockResponse($reply, $orders);
        }
        return $reply;
    }

    /**
     * Summarizes a piece of text (like an email) (unchanged from your example).
     */
    public function generateSummary($emailContent): string
    {
        try {
            $prompt = <<<EOT
Summarize the following internal email. Focus on the main issue or concern, in a professional tone, removing unnecessary details:

"$emailContent"
EOT;
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

    /* ========================================================================
     *                 DETECT / EXTRACT / CHECK HELPERS
     * ======================================================================== */

    private function extractOrderNumber(string $query): ?string
    {
        preg_match('/\d+/', $query, $matches);
        return $matches[0] ?? null;
    }

    private function extractEmail(string $query): ?string
    {
        preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,4}\b/i', $query, $matches);
        return $matches[0] ?? null;
    }

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
     * We return the name of the DB column if we detect the user
     * wants that piece of info. Expand this as needed.
     */
    private function detectSpecificRequest(string $query): ?string
    {
        $patterns = [
            'email_address' => '/\b(email|e-mail|mail address)\b/i',
            'customer_name' => '/\b(name|first name|last name|customer name)\b/i',
            'phone'         => '/\b(phone|contact number|mobile)\b/i',
            // add more fields here if needed, e.g. shipping_address, payment_status, etc.
        ];

        foreach ($patterns as $field => $pattern) {
            if (preg_match($pattern, $query)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * If conversation context is too large, we call OpenAI to summarize it
     * and store only the summary, preventing token overflow in subsequent calls.
     */
    private function checkConversationLengthAndSummarize(array $contextData): array
    {
        if (strlen($contextData['previousContext'] ?? '') > self::MAX_CONTEXT_LENGTH) {
            // Summarize it using generateSummary (or a dedicated summarization)
            $oldContext = $contextData['previousContext'];
            $summary    = $this->generateSummary($oldContext);

            // Store just the summary (plus a note that older logs were summarized)
            $contextData['previousContext'] = "[CONTEXT WAS SUMMARIZED]\n" . $summary;
        }
        return $contextData;
    }


    /* ========================================================================
     *                       DATABASE + SHOPIFY FETCH
     * ======================================================================== */

    private function fetchFromDatabase(?string $orderNumber, ?string $email): Collection
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
     * Example of fetching from Shopify:
     * - If we have an "orderNumber" that is truly a numeric Shopify ID, we call single-order endpoint.
     * - Otherwise, we call the listing endpoint with possible filters (e.g. email).
     * Then we store the retrieved data in the local DB so we don’t have to re-fetch next time.
     */
    private function fetchFromShopify(?string $orderNumber = null, ?string $email = null): Collection
    {
        try {
            // Single-order endpoint if numeric ID is given
            if ($orderNumber) {
                $endpoint = "https://{$this->shopifyDomain}/admin/api/2024-01/orders/{$orderNumber}.json";
            } else {
                $endpoint = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json";
            }

            $params = [
                'status' => 'any',
                'limit'  => 5,
            ];

            // If listing by email
            if (!$orderNumber && $email) {
                $params['email'] = $email;
            }

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type'           => 'application/json',
            ])->get($endpoint, $params);

            if ($response->successful()) {
                $json = $response->json();

                // single-order endpoint => { "order": {...} }
                if ($orderNumber && isset($json['order'])) {
                    $orders = collect([$json['order']]);
                } else {
                    // list endpoint => { "orders": [...] }
                    $orders = collect($json['orders'] ?? []);
                }

                if ($orders->isEmpty()) {
                    Log::info("No orders found on Shopify for orderNumber={$orderNumber}, email={$email}");
                } else {
                    // Store or update them in local DB
                    $this->syncOrdersToLocalDb($orders);
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
     * Store or update the orders in our local DB.
     * This depends heavily on how your local DB is structured.
     */
    private function syncOrdersToLocalDb(Collection $shopifyOrders): void
    {
        foreach ($shopifyOrders as $order) {
            // We assume $order is an array from Shopify’s JSON
            // Map the relevant fields to your ShopifyOrder columns:
            $data = [
                'id'              => $order['id'], // or your local PK logic
                'order_number'    => $order['name'] ?? $order['order_number'], 
                'email_address'   => $order['email'] ?? null,
                'customer_name'   => $order['customer']['first_name'] ?? null,
                'paid_amount'     => $order['total_price'] ?? null,
                'tracking_number' => null,  // If you store it differently 
                'tracking_url'    => null,  // same note
                'order_date'      => $order['created_at'] ?? null,
                // Add more fields as needed...
            ];

            ShopifyOrder::updateOrCreate(
                ['id' => $data['id']],  // or ['order_number' => $data['order_number']]
                $data
            );
        }
    }


    /* ========================================================================
     *                       ORDER FORMATTING / MULTIPLE ORDERS
     * ======================================================================== */

    /**
     * If multiple orders are found and user did NOT specify an order number,
     * prompt them to select which one or display a short summary.
     */
    private function handleMultipleOrders(Collection $orders, bool $useSlackBlocks = false): string|array
    {
        if ($orders->count() < 2) {
            return ''; // fallback, shouldn’t happen here
        }

        $count = $orders->count();
        $summary = "I found $count matching orders:\n";

        // Provide a short list
        foreach ($orders as $idx => $order) {
            $idxDisplay     = $idx + 1;
            $shopifyId      = $order['id'] ?? 'N/A';
            $nameOrNumber   = $order['order_number'] ?? 'Unknown #';
            $summary       .= "$idxDisplay) #$nameOrNumber (Shopify ID: $shopifyId)\n";
        }
        $summary .= "\nPlease specify which order you want details on (e.g. 'Order #2').";

        if ($useSlackBlocks) {
            // Return Slack block structure
            return [
                "response_type" => "ephemeral",
                "blocks" => [
                    [
                        "type" => "section",
                        "text" => [
                            "type" => "mrkdwn",
                            "text" => $summary
                        ]
                    ],
                ]
            ];
        }

        return $summary;
    }

    /**
     * Format the orders found (usually just one in this scenario).
     */
    private function formatOrderDetails(Collection $orders): string
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

            $formatted .= "Order #{$orderNumber}";
            if ($productName) {
                $formatted .= " | {$productName}";
            }
            if ($numItems) {
                $formatted .= " ({$numItems} items)";
            }

            $formatted .= "\n"
                         ." - Name: {$customerName}\n"
                         ." - Email: {$email}\n"
                         ." - Paid: {$paidAmount}\n"
                         ." - Tracking: {$trackingNumber} | [Track]({$trackingUrl})\n"
                         ." - Date: {$orderDate}\n\n";
        }
        return $formatted;
    }


    /* ========================================================================
     *                       PROMPT / OPENAI / RESPONSES
     * ======================================================================== */

    /**
     * Build the main prompt for ChatGPT with relevant context.
     */
    private function buildPrompt(array $contextData, string $orderContext, string $customerQuery): string
    {
        return <<<PROMPT
You are an internal support assistant. 
You have the following context from previous conversation:

{$contextData['previousContext']}

Orders found / relevant info:
{$orderContext}

The internal user just asked: "{$customerQuery}"

Provide a concise answer focusing on what was requested or relevant info needed.
PROMPT;
    }

    /**
     * Actually call the OpenAI chat/completions endpoint.
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
     * Optionally build a Slack BlockKit-style response
     * from the final text reply (and maybe order details).
     */
    private function buildSlackBlockResponse(string $reply, Collection $orders): array
    {
        $blocks = [];

        $blocks[] = [
            "type" => "section",
            "text" => [
                "type" => "mrkdwn",
                "text" => $reply
            ]
        ];

        // If there's an order to display, we can do a section for it
        if ($orders->count() === 1) {
            $o = $orders->first();
            $fields = [];
            $fields[] = [
                "type" => "mrkdwn",
                "text" => "*Order Number:*\n" . ($o['order_number'] ?? 'N/A')
            ];
            $fields[] = [
                "type" => "mrkdwn",
                "text" => "*Email:*\n" . ($o['email_address'] ?? 'N/A')
            ];
            $fields[] = [
                "type" => "mrkdwn",
                "text" => "*Paid:* \n" . ($o['paid_amount'] ?? 'N/A')
            ];
            // Add more as you wish

            $blocks[] = [
                "type" => "section",
                "fields" => $fields
            ];
        }

        return [
            "response_type" => "ephemeral",
            "blocks" => $blocks,
        ];
    }


    /* ========================================================================
     *             HANDLING LAST RECORD REQUEST / STORING CONTEXT
     * ======================================================================== */

    private function handleLastRecordRequest(array $contextData, bool $useSlackBlocks = false): string|array
    {
        $lastOrder = collect($contextData['conversationLog'])->last(function ($entry) {
            return isset($entry['orderDetails']);
        });

        if ($lastOrder && isset($lastOrder['orderDetails'])) {
            $o = $lastOrder['orderDetails'];
            $text = "Here is your last referenced order:\n".
                    "- **Order #**: ".($o['order_number'] ?? 'N/A')."\n".
                    "- **Name**: ".($o['customer_name'] ?? 'N/A')."\n".
                    "- **Email**: ".($o['email_address'] ?? 'N/A')."\n".
                    "- **Paid**: ".($o['paid_amount'] ?? 'N/A')."\n".
                    "- **Tracking**: ".($o['tracking_number'] ?? 'N/A')." | [Track](".($o['tracking_url'] ?? '#').")\n".
                    "- **Date**: ".($o['order_date'] ?? 'N/A')."\n";

            if ($useSlackBlocks) {
                return [
                    "response_type" => "ephemeral",
                    "blocks" => [
                        [
                            "type" => "section",
                            "text" => [
                                "type" => "mrkdwn",
                                "text" => $text
                            ]
                        ]
                    ]
                ];
            }

            return $text;
        }
        return null; // no last order found
    }

    /**
     * Store the conversation log in the cache so we can reference it
     * in subsequent queries for the same user or same order context.
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

    /**
     * Retrieve context from cache or provide defaults.
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

    private function setCachedContext(string $key, array $data): void
    {
        // Cache for 6 hours
        Cache::put($key, $data, now()->addHours(6));
    }


    /* ========================================================================
     *                     GETTERS (OPTIONAL)
     * ======================================================================== */

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function getShopifyDomain(): ?string
    {
        return $this->shopifyDomain;
    }


 
private function saveConversation($userIdentifier, $orderNumber, $conversationLog)
{
    Conversation::updateOrCreate(
        ['user_identifier' => $userIdentifier, 'order_number' => $orderNumber],
        ['conversation_data' => $conversationLog]
    );
}
}
