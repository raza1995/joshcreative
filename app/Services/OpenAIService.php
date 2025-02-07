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
    private const MAX_CONTEXT_LENGTH         = 2000;
    private const CONTEXT_SUMMARY_CHUNK_SIZE = 1000;

    public function __construct(ShopifyService $shopifyService)
    {
        $this->shopifyDomain   = env('SHOPIFY_STORE_DOMAIN');
        $this->accessToken     = env('SHOPIFY_ACCESS_TOKEN');
        $this->apiKey          = config('services.openai.api_key');
        $this->model           = 'gpt-3.5-turbo';
        $this->shopifyService  = $shopifyService;
    }

    /**
     * Main entry point for generating a reply. 
     * - We fetch from DB first for basic info.
     * - If user wants more detail, we fetch from Shopify (no DB update).
     */
    public function generateReply(string $customerQuery, bool $useSlackBlocks = false, ?string $slackUserId = null): string|array
    {
        // 1. Detect overall intent (/help, etc.)
        $intent = $this->detectIntent($customerQuery);
        Log::info('Intent detected:', $intent);
        if ($intent['removeCache']) {
            Cache::flush(); // or Cache::clear() in Laravel 10
            return "All conversation caches have been successfully removed.";
        }


        if ($intent['helpRequest']) {
            return $this->generateHelpResponse($useSlackBlocks);
        }

            // ✅ New: Check for New Emails
    if ($intent['checkNewEmails']) {
        $newEmails = app(GmailService::class)->fetchUnreadEmails();
        return "📬 You have " . count($newEmails) . " new emails.";
    }

    
        // NEW: Handle "get last X orders"
    if ($intent['fetchLastOrders'] > 0) {
        $lastOrders = $this->fetchShopifyOrders(null, null, false, $intent['fetchLastOrders']);
        if ($lastOrders->isNotEmpty()) {
            return $this->formatOrderDetails($lastOrders);
        }
        return "No recent orders found.";
    }
        // 2. Extract order number / email
        $orderNumber = $this->extractOrderNumber($customerQuery);
        $email       = $this->extractEmail($customerQuery);

        // 3. Determine cache key & load context
        $cacheKey   = $this->determineCacheKey($slackUserId, $orderNumber, $email);
        $contextData = $this->getCachedContext($cacheKey);
        $contextData = $this->checkConversationLengthAndSummarize($contextData);

        // 4. Fallback to last known if none provided
        $orderNumber = $orderNumber ?: $contextData['lastOrderNumber'];
        $email       = $email       ?: $contextData['lastEmail'];

        // 5. If user says "show me last record"
        if ($intent['lastRecord']) {
            $lastOrderResponse = $this->handleLastRecordRequest($contextData, $useSlackBlocks);
            if ($lastOrderResponse) {
                return $lastOrderResponse;
            }
        }

        // 6. Fetch basic data from DB or Shopify
        //    (DB is always minimal info: order_number, date, name, email, etc.)
        $orders = $this->fetchRelevantOrders($intent, $orderNumber, $email);

        // 7. If user specifically wants one field (like email or phone) and we have it in DB:
        if ($intent['specificField'] && $orders->isNotEmpty()) {
            // Check if the DB has that field:
            $fieldValue = $orders->first()[$intent['specificField']] ?? null;
            if ($fieldValue) {
                return "Requested info ({$intent['specificField']}): {$fieldValue}";
            } else {
                // If DB doesn't have it, fetch from Shopify for that single order
                if ($orders->count() === 1 && $orderNumber) {
                    $shopifyData = $this->fetchSingleOrderFromShopify($orderNumber);
                    if ($shopifyData) {
                        // Attempt to retrieve the requested field from the Shopify JSON
                        $fieldValue = $this->extractAdditionalField($shopifyData, $intent['specificField']);
                        if ($fieldValue) {
                            return "Requested info ({$intent['specificField']}): {$fieldValue}";
                        }
                    }
                }
                // fallback message
                return "Information for ({$intent['specificField']}) not available.";
            }
        }

        // 8. If multiple orders but no specific order # given, show summary
        if ($orders->count() > 1 && !$orderNumber) {
            return $this->handleMultipleOrders($orders, $useSlackBlocks);
        }

        // 9. Format basic details from DB
        $orderContext = $this->formatOrderDetails($orders);

        // 10. If user wants more data than the DB can store (like financial_status, shipping cost),
        //     or you want to always show expanded info:
        //     We can fetch the single order from Shopify and enrich the response in memory.
        $shopifyExtra = [];
        if ($orders->count() === 1 && $orderNumber) {
            $shopifyData = $this->fetchSingleOrderFromShopify($orderNumber);
            if ($shopifyData) {
                // Build a text snippet with additional data
                $shopifyExtra = $this->formatAdditionalShopifyInfo($shopifyData);
            }
        }

        // Merge the "extra" text block into orderContext for final display
        if (!empty($shopifyExtra)) {
            $orderContext .= "\n\nAdditional real-time Shopify data:\n" . $shopifyExtra;
        }

        // 11. Build ChatGPT prompt
        $prompt = $this->buildPrompt($contextData, $orderContext, $customerQuery);
        Log::info('Generated prompt: ' . $prompt);


        $reply  = $this->callOpenAI($prompt);

        // 12. Store conversation
        $this->storeInCache($cacheKey, $customerQuery, $reply, $orders->first(), $orderNumber, $email, $contextData);

        // 13. Return final response
        if ($useSlackBlocks) {
            return $this->buildSlackBlockResponse($reply, $orders, $shopifyExtra);
        }
        return $reply;
    }

    /* ========================================================================
     *     HELPER: DETECT INTENT, EXTRACT ORDER/EMAIL, /HELP, ETC.
     * ======================================================================== */

     private function detectIntent(string $query): array
     {
         $lowerQuery = strtolower($query);
     
         $intent = [
             'helpRequest'       => false,
             'updateRequest'     => false,
             'lastRecord'        => false,
             'specificField'     => null,
             'removeCache'       => false,
             'fetchLastOrders'   => 0,          // Existing intent
             'checkNewEmails'    => false,      // New intent for checking emails
             'openEmailFrom'     => null,       // New intent for opening specific emails
         ];
     
         // Check for removing cache
         if (preg_match('/\b(remove|delete|clear)\b.*\bcache\b/i', $query)) {
             $intent['removeCache'] = true;
         }
     
         // Help request
         if (str_contains($lowerQuery, '/help') || preg_match('/\bhelp\b/i', $query)) {
             $intent['helpRequest'] = true;
         }
     
         // Update request
         if (preg_match('/\b(updated data|refresh data|latest data|get recent data|fetch latest|update info|refresh info|current status|latest status)\b/i', $query)) {
             $intent['updateRequest'] = true;
         }
     
         // Last record
         if (preg_match('/\b(last record|previous order|recent order|show last|latest order|last details)\b/i', $query)) {
             $intent['lastRecord'] = true;
         }
     
         // Fetch last X orders
         if (preg_match('/\b(?:last|recent|show|fetch|give|get)\s*(?:me)?\s*(\d+)\s*orders?\b/i', $query, $matches)) {
             $intent['fetchLastOrders'] = (int) $matches[1];
         }
     
         // Check for new/unread emails
         if (preg_match('/\b(check|any|get|show)\s*(?:new|unread)?\s*emails?\b/i', $query)) {
             $intent['checkNewEmails'] = true;
         }
     
         // Open specific email (e.g., "open email from john@example.com")
         if (preg_match('/\bopen (?:the )?last email from\s+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/i', $query, $matches)) {
        $intent['openEmailFrom'] = $matches[1];
    }
     
         // Specific field requests
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
     * If user specifically wants a field that DB doesn't store, 
     * we can parse it from Shopify's raw JSON response. 
     * (Expand this logic as needed.)
     */
    private function extractAdditionalField(array $shopifyData, string $field)
    {
        // For example, "phone" might live in $shopifyData['phone'] or shipping/billing phone
        if ($field === 'phone') {
            // Check top-level phone, or shipping/billing address phone
            return $shopifyData['phone']
                ?? $shopifyData['shipping_address']['phone']
                ?? $shopifyData['billing_address']['phone']
                ?? null;
        }
        // Add more logic if you store or want to parse other fields
        return null;
    }

    private function generateHelpResponse(bool $useSlackBlocks = false): string|array
    {
        $helpText = <<<'EOT'
*Here are some commands/prompts you can use:*

• **Order lookup by number**  
  - Example: "Show me order #1234" or "Find status of order 1002"
• **Lookup by email**  
  - Example: "Any orders for example@example.com?"
• **Show the last record**  
  - Example: "Show me the last record" or "What was the previous order we discussed?"
• **Refresh or update data**  
  - Example: "Refresh data for order #1234"
• **Request specific information**  
  - Example: "What's the email address on that order?" or "Give me the customer's phone"
• **Help**  
  - Type "/help" or "help" to see this message again.
EOT;

        if ($useSlackBlocks) {
            return [
                "response_type" => "ephemeral",
                "blocks" => [
                    [
                        "type" => "section",
                        "text" => [
                            "type" => "mrkdwn",
                            "text" => $helpText
                        ]
                    ]
                ]
            ];
        }

        return $helpText;
    }

    private function extractOrderNumber(string $query): ?string
    {
        preg_match('/(?:order\s*#?\s*|#)(\d+)/i', $query, $matches);
        if (!empty($matches[1])) {
            return $matches[1];
        }
        preg_match('/\b\d{3,}\b/', $query, $digitsOnly);
        return $digitsOnly[0] ?? null;
    }

    private function extractEmail(string $query): ?string
    {
        preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,7}\b/i', $query, $matches);
        return $matches[0] ?? null;
    }

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
     *        FETCH ORDERS: DB BASIC + (OPTIONALLY) SHOPIFY
     * ======================================================================== */

    /**
     * We fetch local DB for minimal info, unless user wants an update/refresh. 
     */
    private function fetchRelevantOrders(array $intent, ?string $orderNumber, ?string $email): Collection
    {
        if ($intent['updateRequest']) {
            // Directly fetch from Shopify
            return $this->fetchShopifyOrders($orderNumber, $email, false);
        }
        // Otherwise check DB first, fallback to Shopify if none
        $orders = $this->fetchFromDatabase($orderNumber, $email);
        if ($orders->isEmpty()) {
            $orders = $this->fetchShopifyOrders($orderNumber, $email, false);
        }
        return $orders;
    }

    /**
     * DB only stores minimal columns. If user wants more details 
     * we won't store them in DB (just show them in the response).
     */
    private function fetchFromDatabase(?string $orderNumber, ?string $email): Collection
    {
        if ($email) {
            return ShopifyOrder::where('email_address', $email)->get();
        }
        if ($orderNumber) {
            return ShopifyOrder::where('order_number', 'like', "%{$orderNumber}%")->get();
        }
        return collect();
    }

    /**
     * Return a list of orders from Shopify, 
     * but do NOT store extended fields in DB (only minimal).
     * 
     * @param bool $storeInDb If true, we store basic columns in DB. 
     *                        If false, we skip DB update entirely.
     */
    private function fetchShopifyOrders(?string $orderNumber, ?string $email, bool $storeInDb = true,int $limit = 5): Collection
    {
        try {
            // Single or multiple
            if ($orderNumber) {
                $endpoint = "https://{$this->shopifyDomain}/admin/api/2024-01/orders/{$orderNumber}.json";
            } else {
                $endpoint = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json";
            }

            $params = [
                'status' => 'any',
                'limit'  => $limit,
                'order'     => 'created_at desc',  // Ensure latest orders

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

                if ($orders->isNotEmpty() && $storeInDb) {
                    // Optionally store minimal columns only
                    $this->storeBasicOrderData($orders);
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
     * If we need just one single order’s real-time data (for extra fields),
     * we call Shopify directly. 
     * We do NOT store any data in DB.
     */
    private function fetchSingleOrderFromShopify(string $orderNumber): ?array
    {

        Log::info('Order Number IN fetchSingleOrderFromShopify:', ['order_number' => $orderNumber]);
        try {
            $endpoint = "https://{$this->shopifyDomain}/admin/api/2024-01/orders/{$orderNumber}.json";
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type'           => 'application/json',
            ])->get($endpoint);

            if ($response->successful()) {
                $json = $response->json();
                return $json['order'] ?? null;
            }
            Log::error('Shopify Single-Order API Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Shopify Single-Order Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Minimal data insertion. Only store columns your DB is built for:
     * order_number, order_date, product_name, etc.
     */
    private function storeBasicOrderData(Collection $shopifyOrders): void
    {

        $orderNumberVal = $order['order_number'] ?? null;  // e.g. 13300

        foreach ($shopifyOrders as $order) {
            // build line item names
            $lineItems    = $order['line_items'] ?? [];
            $productNames = collect($lineItems)->pluck('name')->implode(', ');
            $numItems     = collect($lineItems)->sum('quantity');

            // fulfillment tracking
            $fulfillments   = $order['fulfillments'] ?? [];
            $trackingNumber = null;
            $trackingUrl    = null;
            if (!empty($fulfillments)) {
                $firstFulfillment = $fulfillments[0];
                $trackingNumber   = $firstFulfillment['tracking_numbers'][0] ?? null;
                $trackingUrl      = $firstFulfillment['tracking_urls'][0] ?? null;
            }

            // discount codes
            $discountCodes = $order['discount_codes'] ?? [];
            $coupon        = $discountCodes ? collect($discountCodes)->pluck('code')->implode(', ') : null;
            Log::info('Order Data:', $order);
            $orderNumberVal = $order['order_number'] ?? null;
            $nameField      = $order['name'] ?? '';

            $data = [
                'order_number' => $orderNumberVal,
                'order_date'      => $order['created_at'] ?? null,
                'product_name'    => $productNames,
                'customer_name'   => $this->resolveCustomerName($order),
                'email_address'   => $order['email'] ?? $order['contact_email'] ?? null,
                'tracking_number' => $trackingNumber,
                'tracking_url'    => $trackingUrl,
                'coupon'          => $coupon,
                'paid_amount'     => $order['total_price'] ?? null,
                'discount'        => $order['total_discounts'] ?? null,
                'number_of_items' => $numItems,
            ];

          
        }
    }

    private function resolveCustomerName(array $order): string
    {
        // billing
        if (!empty($order['billing_address']['first_name']) || !empty($order['billing_address']['last_name'])) {
            $first = $order['billing_address']['first_name'] ?? '';
            $last  = $order['billing_address']['last_name'] ?? '';
            return trim("$first $last");
        }
        // shipping
        if (!empty($order['shipping_address']['first_name']) || !empty($order['shipping_address']['last_name'])) {
            $first = $order['shipping_address']['first_name'] ?? '';
            $last  = $order['shipping_address']['last_name'] ?? '';
            return trim("$first $last");
        }
        // customer object
        if (!empty($order['customer'])) {
            $first = $order['customer']['first_name'] ?? '';
            $last  = $order['customer']['last_name'] ?? '';
            return trim("$first $last");
        }
        return 'N/A';
    }

    /* ========================================================================
     *           ADDITIONAL SHOPIFY DATA (not in DB)
     * ======================================================================== */

    /**
     * Format extra fields from the Shopify JSON that you do NOT store in DB, 
     * e.g. phone, shipping lines, financial_status, fulfillment_status, etc.
     * Return a string snippet to append to the final user message.
     */
    private function formatAdditionalShopifyInfo(array $shopifyData): string
    {
        $phone              = $shopifyData['phone'] ?? $shopifyData['shipping_address']['phone'] ?? 'N/A';
        $financialStatus    = $shopifyData['financial_status'] ?? 'N/A';
        $fulfillmentStatus  = $shopifyData['fulfillment_status'] ?? 'N/A';
        $shippingLines      = $shopifyData['shipping_lines'] ?? [];
        $shippingTitle      = 'N/A';
        $shippingCost       = 'N/A';

        if (!empty($shippingLines)) {
            $firstLine     = $shippingLines[0];
            $shippingTitle = $firstLine['title'] ?? 'N/A';
            $shippingCost  = $firstLine['price'] ?? 'N/A';
        }

        // Build a small text snippet
        $extraSnippet = " - Phone: {$phone}\n"
            . " - Financial Status: {$financialStatus}\n"
            . " - Fulfillment Status: {$fulfillmentStatus}\n"
            . " - Shipping Method: {$shippingTitle} (Cost: {$shippingCost})\n";

        return $extraSnippet;
    }

    /* ========================================================================
     *          ORDER FORMATTING + MULTIPLE ORDERS
     * ======================================================================== */

    private function handleMultipleOrders(Collection $orders, bool $useSlackBlocks = false): string|array
    {
        $count = $orders->count();
        if ($count < 2) {
            return $this->formatOrderDetails($orders);
        }
        $summary = "I found $count matching orders:\n\n";
        foreach ($orders as $idx => $order) {
            $idxDisplay   = $idx + 1;
            $nameOrNumber = $order['order_number'] ?? 'Unknown #';
            $summary .= "$idxDisplay) Order $nameOrNumber\n";
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

    private function formatOrderDetails(Collection $orders): string
{
    if ($orders->isEmpty()) {
        return "No orders found.";
    }

    $formatted = '';
    foreach ($orders as $order) {
        $formatted .= "Order: " . ($order['order_number'] ?? 'N/A') . "\n"
            . " - Date: "       . ($order['created_at']      ?? 'N/A') . "\n"
            . " - Name: "       . ($this->resolveCustomerName($order) ?? 'N/A') . "\n"
            . " - Email: "      . ($order['email']           ?? 'N/A') . "\n"
            . " - Paid: $"      . ($order['total_price']     ?? 'N/A') . "\n"
            . " - Status: "     . ($order['financial_status'] ?? 'N/A') . "\n"
            . " - Tracking: "   . (($order['fulfillments'][0]['tracking_number'] ?? 'N/A')) . "\n\n";
    }
    return $formatted;
}


    /* ========================================================================
     *                 OPENAI PROMPT & RESPONSE
     * ======================================================================== */

    private function buildPrompt(array $contextData, string $orderContext, string $customerQuery): string
    {
        $previousContext = $contextData['previousContext'] ?? '';
        return <<<PROMPT
You are a helpful internal support AI assistant with knowledge about Shopify orders and relevant user history. 
Maintain a friendly, concise, and professional tone.

Here is the conversation so far:
{$previousContext}

Relevant order data (if any):
{$orderContext}

The user just asked: "{$customerQuery}"

Provide a concise, direct response with any relevant details.
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
                return $response->json()['choices'][0]['message']['content'] 
                    ?? 'No response from AI.';
            }
            Log::error('OpenAI API Error: ' . $response->body());
            return 'Oops, something went wrong with OpenAI. Try again.';
        } catch (\Exception $e) {
            Log::error('OpenAI call exception: ' . $e->getMessage());
            return 'Error contacting OpenAI API. Please try again later.';
        }
    }

    /**
     * Build Slack block response. Incorporate extra Shopify data if present.
     */
    private function buildSlackBlockResponse(string $reply, Collection $orders, string $shopifyExtra = ''): array
    {
        $blocks = [
            [
                "type" => "section",
                "text" => [
                    "type" => "mrkdwn",
                    "text" => $reply
                ]
            ]
        ];

        // If exactly 1 order
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
                [
                    "type" => "mrkdwn",
                    "text" => "*Discount:* \n" . ($o['discount'] ?? '0.00')
                ],
                [
                    "type" => "mrkdwn",
                    "text" => "*Coupon:* \n" . ($o['coupon'] ?? 'None')
                ],
                [
                    "type" => "mrkdwn",
                    "text" => "*Tracking:* \n" 
                        . ($o['tracking_number'] ?? 'N/A')
                        . " | <" . ($o['tracking_url'] ?? '#') . "|Track>"
                ]
            ];

            $blocks[] = [
                "type"   => "section",
                "fields" => $fields
            ];

            // If we have extra real-time data from Shopify
            if ($shopifyExtra) {
                $blocks[] = [
                    "type" => "section",
                    "text" => [
                        "type" => "mrkdwn",
                        "text" => "*Additional Shopify Info:*\n" . $shopifyExtra
                    ]
                ];
            }
        }

        return [
            "response_type" => "ephemeral",
            "blocks"        => $blocks,
        ];
    }

    /* ========================================================================
     *         LAST RECORD / STORING CONVERSATION
     * ======================================================================== */

    private function handleLastRecordRequest(array $contextData, bool $useSlackBlocks = false): string|array
    {
        $lastOrder = collect($contextData['conversationLog'])->last(function ($entry) {
            return !empty($entry['orderDetails']);
        });

        if ($lastOrder) {
            $o = $lastOrder['orderDetails'];
            $text = "Here is your last referenced order:\n"
                . "- **Order #**: ".($o['order_number']    ?? 'N/A')."\n"
                . "- **Name**: ".($o['customer_name']      ?? 'N/A')."\n"
                . "- **Email**: ".($o['email_address']      ?? 'N/A')."\n"
                . "- **Paid**: ".($o['paid_amount']         ?? 'N/A')."\n"
                . "- **Discount**: ".($o['discount']        ?? '0.00')."\n"
                . "- **Coupon**: ".($o['coupon']            ?? 'None')."\n"
                . "- **Tracking**: ".($o['tracking_number'] ?? 'N/A')
                . " | [Track](".($o['tracking_url']         ?? '#').")\n"
                . "- **Date**: ".($o['order_date']          ?? 'N/A')."\n";

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
        return "No previously referenced order found in this conversation.";
    }

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
            'emailContent' => $email ? $reply : null,  // Store email content if present
        ];
    
        $updatedContext = [
            'previousContext' => ($contextData['previousContext'] ?? '')
                . "\nUser: {$customerQuery}\nAI: {$reply}",
            'lastOrderNumber' => $orderNumber,
            'lastEmail'       => $email,
            'conversationLog' => $conversationLog,
        ];
    
        $this->setCachedContext($cacheKey, $updatedContext);
    }
    

    private function getCachedContext(string $key): array
    {
        $cachedData = Cache::get($key, []);
        return array_merge([
            'previousContext' => '',
            'lastOrderNumber' => null,
            'lastEmail'       => null,
            'conversationLog' => [],
        ], (array)$cachedData);
    }

    private function setCachedContext(string $key, array $data): void
    {
        Cache::put($key, $data, now()->addHours(6));
    }

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
            }

            Log::error('OpenAI API Summary Error: ' . $response->body());
            return 'Error generating summary.';
        } catch (\Exception $e) {
            Log::error('Exception in OpenAIService (Summary): ' . $e->getMessage());
            return 'Error communicating with AI for summary.';
        }
    }



        /* ========================================================================
     *               SUMMARIZATION AND CONTEXT MANAGEMENT
     * ======================================================================== */

     private function checkConversationLengthAndSummarize(array $contextData): array
     {
         $previousContext = $contextData['previousContext'] ?? '';
         if (strlen($previousContext) <= self::MAX_CONTEXT_LENGTH) {
             return $contextData; 
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


     private function fetchLastOrdersFromShopify(int $limit = 2): Collection
{
    try {
        $endpoint = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json";
        $params = [
            'status' => 'any',
            'limit'  => $limit,
            'order'  => 'created_at desc' // Ensures latest orders
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type'           => 'application/json',
        ])->get($endpoint, $params);

        if ($response->successful()) {
            $json = $response->json();
            return collect($json['orders'] ?? []);
        }

        Log::error('Shopify API Error (Last Orders): ' . $response->body());
        return collect();
    } catch (\Exception $e) {
        Log::error('Shopify Exception (Last Orders): ' . $e->getMessage());
        return collect();
    }
}
private function fetchAndSaveEmailFromSender(string $emailAddress, string $cacheKey): string
{
    $gmailService = app(GmailService::class);
    $emailContent = $gmailService->getEmailBySender($emailAddress);

    if ($emailContent) {
        // Save in conversation log
        $this->storeInCache(
            $cacheKey,
            "Opened email from {$emailAddress}",
            $emailContent,
            null,
            null,
            $emailAddress,
            $this->getCachedContext($cacheKey)
        );

        return "📩 *Email from:* {$emailAddress}\n\n" . $emailContent;
    }

    return "❌ No recent emails found from {$emailAddress}.";
}

 
}
