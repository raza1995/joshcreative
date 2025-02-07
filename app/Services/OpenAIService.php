<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ShopifyOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
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
     * before we attempt to summarize it in a single chunk.
     */
    private const MAX_CONTEXT_LENGTH = 2000; // Adjust as needed

    /**
     * Summaries can be appended or replace older context 
     * to prevent token overflow.
     */
    private const CONTEXT_SUMMARY_CHUNK_SIZE = 1000;

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
     * Entry point for generating a reply to the user’s query.
     */
    public function generateReply(string $customerQuery, bool $useSlackBlocks = false, ?string $slackUserId = null): string|array
    {
        // 1. Parse user’s query
        $orderNumber = $this->extractOrderNumber($customerQuery);
        $email       = $this->extractEmail($customerQuery);

        // 2. Determine the cache key
        //    Use Slack user ID if available, otherwise fallback to email/order/general
        $cacheKey = $this->determineCacheKey($slackUserId, $orderNumber, $email);

        // 3. Load prior conversation from cache
        $contextData = $this->getCachedContext($cacheKey);

        // Summarize older context if it’s too large
        $contextData = $this->checkConversationLengthAndSummarize($contextData);

        // 4. Fallback to last known order/email if not found in this query
        $orderNumber = $orderNumber ?: $contextData['lastOrderNumber'];
        $email       = $email       ?: $contextData['lastEmail'];

        // 5. Detect special requests / user intent
        $intent = $this->detectIntent($customerQuery);

        // 6. Handle “show me my last record”
        if ($intent['lastRecord']) {
            $lastOrderResponse = $this->handleLastRecordRequest($contextData, $useSlackBlocks);
            if ($lastOrderResponse) {
                return $lastOrderResponse;
            }
        }

        // 7. Fetch data from DB or Shopify
        $orders = collect();
        if ($intent['updateRequest']) {
            // Force refresh from Shopify
            $orders = $this->fetchFromShopify($orderNumber, $email);
        } else {
            $orders = $this->fetchFromDatabase($orderNumber, $email);
            if ($orders->isEmpty()) {
                $orders = $this->fetchFromShopify($orderNumber, $email);
            }
        }

        // 8. If user requested a specific field (email, phone, etc.)
        if ($intent['specificField'] && $orders->isNotEmpty()) {
            $fieldValue = $orders->first()[$intent['specificField']] ?? 'Information not available.';
            return "Requested info ({$intent['specificField']}): {$fieldValue}";
        }

        // 9. If multiple orders found and no specific orderNumber given, ask user to specify
        if ($orders->count() > 1 && !$orderNumber) {
            return $this->handleMultipleOrders($orders, $useSlackBlocks);
        }

        // 10. Format found order data
        $orderContext = $this->formatOrderDetails($orders);

        // 11. Build a ChatGPT prompt
        $prompt = $this->buildPrompt($contextData, $orderContext, $customerQuery);

        // 12. Call OpenAI
        $reply = $this->callOpenAI($prompt);

        // 13. Store in cache + DB for conversation continuity
        $this->storeInCache(
            $cacheKey,
            $customerQuery,
            $reply,
            $orders->first(),
            $orderNumber,
            $email,
            $contextData
        );

        // 14. Return final response
        if ($useSlackBlocks) {
            return $this->buildSlackBlockResponse($reply, $orders);
        }
        return $reply;
    }

    /* ========================================================================
     *                     INTENT / DETECTION HELPERS
     * ======================================================================== */

    /**
     * A more advanced intent detection that checks if the user wants:
     *  - An update/refresh
     *  - The last record
     *  - A specific field (e.g. email, phone, etc.)
     *  - Potentially other future expansions (cancellations, returns, etc.)
     */
    private function detectIntent(string $query): array
    {
        $intent = [
            'updateRequest' => false,
            'lastRecord'    => false,
            'specificField' => null,
        ];

        // Check for update request
        $updatePatterns = [
            '/\b(updated data|refresh data|latest data|get recent data|fetch latest|update info|refresh info|current status|latest status)\b/i'
        ];
        foreach ($updatePatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                $intent['updateRequest'] = true;
                break;
            }
        }

        // Check for last record request
        $lastRecordPatterns = [
            '/\b(last record|previous order|recent order|show last|latest order|last details)\b/i'
        ];
        foreach ($lastRecordPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                $intent['lastRecord'] = true;
                break;
            }
        }

        // Check for specific field (expandable)
        $specificFieldPatterns = [
            'email_address' => '/\b(email|e-mail|mail address)\b/i',
            'customer_name' => '/\b(name|first name|last name|customer name)\b/i',
            'phone'         => '/\b(phone|contact number|mobile)\b/i',
        ];

        foreach ($specificFieldPatterns as $field => $pattern) {
            if (preg_match($pattern, $query)) {
                $intent['specificField'] = $field;
                break;
            }
        }

        return $intent;
    }

    /**
     * Extract an order number with more robust pattern detection.
     * For instance, if user typed "Order #1234" or "#1234" or "1234".
     */
    private function extractOrderNumber(string $query): ?string
    {
        // Example pattern that catches "#1234", "Order #1234", or just "1234"
        // This pattern might still be simplistic, but is a step up.
        preg_match('/(?:order\s*#?\s*|#)(\d+)/i', $query, $matches);

        if (isset($matches[1])) {
            return $matches[1];
        }

        // Fallback: any raw digits if not matched above
        preg_match('/\b\d{3,}\b/', $query, $digitsOnly);
        return $digitsOnly[0] ?? null;
    }

    /**
     * Extract email address from the user’s query using a robust pattern.
     */
    private function extractEmail(string $query): ?string
    {
        // More robust email pattern
        preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,7}\b/i', $query, $matches);
        return $matches[0] ?? null;
    }

    /**
     * If you have a Slack user ID (or any unique user identifier),
     * prefer that to avoid collisions. Otherwise, fallback to email or order.
     */
    private function determineCacheKey(?string $slackUserId, ?string $orderNumber, ?string $email): string
    {
        if ($slackUserId) {
            return 'slack_' . $slackUserId;
        }
        if ($email) {
            return $email;
        }
        if ($orderNumber) {
            return 'order_' . $orderNumber;
        }
        return 'general';
    }

    /* ========================================================================
     *                CONTEXT SUMMARIZATION & CONVERSATION
     * ======================================================================== */

    /**
     * If conversation context is too large, we chunk it and summarize older parts.
     */
    private function checkConversationLengthAndSummarize(array $contextData): array
    {
        $previousContext = $contextData['previousContext'] ?? '';
        if (strlen($previousContext) <= self::MAX_CONTEXT_LENGTH) {
            return $contextData; // No need to summarize
        }

        // Summarize older portion in chunks
        $chunks = str_split($previousContext, self::CONTEXT_SUMMARY_CHUNK_SIZE);
        $summary = '';

        foreach ($chunks as $chunk) {
            $summary .= $this->generateSummary($chunk) . "\n";
        }

        $contextData['previousContext'] = "[CONTEXT WAS SUMMARIZED]\n" . $summary;
        return $contextData;
    }

    /**
     * Summarizes a piece of text, e.g. an internal email or conversation snippet.
     */
    public function generateSummary($textContent): string
    {
        try {
            $prompt = <<<EOT
Please summarize the following text in a concise, professional tone, highlighting only key points:

"$textContent"
EOT;

            $response = Http::withToken($this->apiKey)->post(
                'https://api.openai.com/v1/chat/completions',
                [
                    'model' => $this->model,
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => 'You are a concise summarizer. Keep important details, omit fluff.'
                        ],
                        [
                            'role'    => 'user',
                            'content' => $prompt
                        ],
                    ],
                    'temperature' => 0.3,
                    'max_tokens'  => 150,
                ]
            );

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
     *                    DATABASE + SHOPIFY FETCH
     * ======================================================================== */

    private function fetchFromDatabase(?string $orderNumber, ?string $email): Collection
    {
        if ($email) {
            return ShopifyOrder::where('email_address', $email)->get();
        }
        if ($orderNumber) {
            $order = ShopifyOrder::where('order_number', $orderNumber)->first();
            return $order ? collect([$order]) : collect();
        }
        return collect();
    }

    private function fetchFromShopify(?string $orderNumber = null, ?string $email = null): Collection
    {
        try {
            // If we believe $orderNumber is the Shopify ID (integer),
            // we use single-order endpoint. Otherwise, listing with filters.
            // Adjust this logic if your local "order_number" differs from Shopify "id".
            if ($orderNumber) {
                $endpoint = "https://{$this->shopifyDomain}/admin/api/2024-01/orders/{$orderNumber}.json";
            } else {
                $endpoint = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json";
            }

            $params = [
                'status' => 'any',
                'limit'  => 5,
            ];

            if (!$orderNumber && $email) {
                $params['email'] = $email;
            }

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type'           => 'application/json',
            ])->get($endpoint, $params);

            if ($response->successful()) {
                $json = $response->json();

                if ($orderNumber && isset($json['order'])) {
                    $orders = collect([$json['order']]);
                } else {
                    $orders = collect($json['orders'] ?? []);
                }

                if ($orders->isNotEmpty()) {
                    // Store/update in local DB
                    $this->syncOrdersToLocalDb($orders);
                } else {
                    Log::info("No Shopify orders found for orderNumber={$orderNumber}, email={$email}");
                }

                return $orders;
            }

            Log::error('Shopify API Error: ' . $response->body());
            return collect();
        } catch (\Exception $e) {
            Log::error('Shopify API Exception: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Sync each fetched Shopify order into local DB.
     * Customize the mapping per your DB schema.
     */
    private function syncOrdersToLocalDb(Collection $shopifyOrders): void
    {
        foreach ($shopifyOrders as $order) {
            $data = [
                'id'              => $order['id'], // or your local PK logic
                'order_number'    => $order['name'] ?? $order['order_number'],
                'email_address'   => $order['email'] ?? null,
                'customer_name'   => $order['customer']['first_name'] ?? null,
                'paid_amount'     => $order['total_price'] ?? null,
                'tracking_number' => null,
                'tracking_url'    => null,
                'order_date'      => $order['created_at'] ?? null,
            ];

            ShopifyOrder::updateOrCreate(
                ['id' => $data['id']],
                $data
            );
        }
    }


    /* ========================================================================
     *          ORDER FORMATTING + MULTI-ORDER RESPONSES
     * ======================================================================== */

    private function handleMultipleOrders(Collection $orders, bool $useSlackBlocks = false): string|array
    {
        $count = $orders->count();
        if ($count < 2) {
            // Fallback, though we expect > 1 here
            return $this->formatOrderDetails($orders);
        }

        $summary = "I found $count matching orders:\n\n";

        foreach ($orders as $idx => $order) {
            $idxDisplay   = $idx + 1;
            $shopifyId    = $order['id'] ?? 'N/A';
            $nameOrNumber = $order['order_number'] ?? 'Unknown #';
            $summary     .= "$idxDisplay) Order #$nameOrNumber (Shopify ID: $shopifyId)\n";
        }

        $summary .= "\nPlease specify which order you want details on (e.g. 'Order #2').";

        if ($useSlackBlocks) {
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
     * Format order details for 1 or more orders in plain text.
     */
    private function formatOrderDetails(Collection $orders): string
    {
        if ($orders->isEmpty()) {
            return "No orders found.";
        }

        $formatted = '';
        foreach ($orders as $order) {
            $orderNumber    = $order['order_number'] ?? 'N/A';
            $productName    = $order['product_name'] ?? null; // if you store product details
            $numItems       = $order['number_of_items'] ?? null;
            $customerName   = $order['customer_name'] ?? 'N/A';
            $email          = $order['email_address'] ?? 'N/A';
            $paidAmount     = $order['paid_amount'] ?? 'N/A';
            $trackingNumber = $order['tracking_number'] ?? 'N/A';
            $trackingUrl    = $order['tracking_url'] ?? '#';
            $orderDate      = $order['order_date'] ?? 'N/A';

            $formatted .= "Order #{$orderNumber}";
            if ($productName) {
                $formatted .= " | {$productName}";
            }
            if ($numItems) {
                $formatted .= " ({$numItems} items)";
            }

            $formatted .= "\n"
                . " - Name: {$customerName}\n"
                . " - Email: {$email}\n"
                . " - Paid: {$paidAmount}\n"
                . " - Tracking: {$trackingNumber} | [Track]({$trackingUrl})\n"
                . " - Date: {$orderDate}\n\n";
        }

        return $formatted;
    }


    /* ========================================================================
     *                   OPENAI PROMPT & RESPONSE LOGIC
     * ======================================================================== */

    private function buildPrompt(array $contextData, string $orderContext, string $customerQuery): string
    {
        $previousContext = $contextData['previousContext'] ?? '';

        return <<<PROMPT
You are a helpful internal support AI assistant with knowledge about Shopify orders and relevant user history. 
Adopt a friendly, concise, and professional tone. 

Here is the conversation context so far:
{$previousContext}

Relevant order data (if any):
{$orderContext}

The user just asked: "{$customerQuery}"

Please provide a concise, direct response with any relevant details.
PROMPT;
    }

    private function callOpenAI(string $prompt): string
    {
        try {
            $response = Http::withToken($this->apiKey)->post(
                'https://api.openai.com/v1/chat/completions',
                [
                    'model'       => $this->model,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => "You are an AI assistant. Respond helpfully and professionally."
                        ],
                        [
                            'role'    => 'user',
                            'content' => $prompt
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 350,
                ]
            );

            if ($response->successful()) {
                return $response->json()['choices'][0]['message']['content'] ?? 'No response from AI.';
            }

            Log::error('OpenAI API Error: ' . $response->body());
            return 'Oops, something went wrong with OpenAI. Try again.';
        } catch (\Exception $e) {
            Log::error('OpenAI call exception: ' . $e->getMessage());
            return 'Error contacting OpenAI API. Please try again later.';
        }
    }

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

        // If there's exactly 1 order, show a summary
        if ($orders->count() === 1) {
            $o = $orders->first();
            $fields = [
                [
                    "type" => "mrkdwn",
                    "text" => "*Order Number:*\n" . ($o['order_number'] ?? 'N/A')
                ],
                [
                    "type" => "mrkdwn",
                    "text" => "*Email:*\n" . ($o['email_address'] ?? 'N/A')
                ],
                [
                    "type" => "mrkdwn",
                    "text" => "*Paid:* \n" . ($o['paid_amount'] ?? 'N/A')
                ],
                // Add more fields as you want
            ];

            $blocks[] = [
                "type"   => "section",
                "fields" => $fields
            ];
        }

        return [
            "response_type" => "ephemeral",
            "blocks"        => $blocks,
        ];
    }


    /* ========================================================================
     *         LAST RECORD HANDLING + STORING CONVERSATION HISTORY
     * ======================================================================== */

    private function handleLastRecordRequest(array $contextData, bool $useSlackBlocks = false): string|array
    {
        // Look at the conversationLog array and get the last entry with orderDetails
        $lastOrder = collect($contextData['conversationLog'])->last(function ($entry) {
            return !empty($entry['orderDetails']);
        });

        if ($lastOrder) {
            $o = $lastOrder['orderDetails'];
            $text = "Here is your last referenced order:\n"
                . "- **Order #**: ".($o['order_number'] ?? 'N/A')."\n"
                . "- **Name**: ".($o['customer_name'] ?? 'N/A')."\n"
                . "- **Email**: ".($o['email_address'] ?? 'N/A')."\n"
                . "- **Paid**: ".($o['paid_amount'] ?? 'N/A')."\n"
                . "- **Tracking**: ".($o['tracking_number'] ?? 'N/A')." | [Track](".($o['tracking_url'] ?? '#').")\n"
                . "- **Date**: ".($o['order_date'] ?? 'N/A')."\n";

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

        return null;
    }

    /**
     * Store conversation context + log in both cache and DB
     * so we can reference it in future queries.
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
        // 1. Append new message to conversation log
        $conversationLog = $contextData['conversationLog'] ?? [];
        $conversationLog[] = [
            'timestamp'    => now()->toDateTimeString(),
            'user'         => $customerQuery,
            'ai'           => $reply,
            'orderDetails' => $firstOrder ?? null,
        ];

        // 2. Update the context
        $updatedContext = [
            'previousContext' => $contextData['previousContext'] 
                . "\nUser: {$customerQuery}\nAI: {$reply}",
            'lastOrderNumber' => $orderNumber,
            'lastEmail'       => $email,
            'conversationLog' => $conversationLog,
        ];

        // 3. Save to DB
        $this->saveConversation($cacheKey, $orderNumber, $updatedContext);

        // 4. Save to cache
        $this->setCachedContext($cacheKey, $updatedContext);
    }

    /**
     * Retrieve context from cache or fall back to defaults.
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
        // Store for 6 hours (adjust as needed).
        Cache::put($key, $data, now()->addHours(6));
    }

    /**
     * Save conversation in the DB. 
     * Adjust the fields as per your `Conversation` model/table.
     */
    private function saveConversation($userIdentifier, $orderNumber, $conversationData)
    {
        Conversation::updateOrCreate(
            [
                'user_identifier' => $userIdentifier,
                'order_number'    => $orderNumber ?? null,
            ],
            [
                'conversation_data' => $conversationData 
            ]
        );
    }
}
