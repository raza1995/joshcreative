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

     protected array $config = [
        'max_orders' => 5,
        'cache_ttl' => 1800, // 30 minutes
        'min_confidence' => 0.7,
    ];

    /* ========================================================================
     *                          MAIN ENTRY POINT
     * ======================================================================== */

    public function generateReply(string $customerQuery, bool $useSlackBlocks = false, ?string $slackUserId = null): string|array
    {
        try {
            // 1. Intent Detection
            $intentData = $this->detectIntentWithModel($customerQuery);
            
            // 2. Log detection results
            Log::info('Intent detection', [
                'query' => $customerQuery,
                'intent' => $intentData,
                'user' => $slackUserId
            ]);

            // 3. Handle low confidence scenarios
            if ($intentData['confidence'] < $this->config['min_confidence']) {
                return $this->handleLowConfidenceQuery($customerQuery);
            }

            // 4. Route to appropriate handler
            return match($intentData['intent']) {
                'remove_cache' => $this->handleCacheRemoval(),
                'help' => $this->generateHelpResponse($useSlackBlocks),
                'check_emails' => $this->handleEmailIntent($intentData),
                'fetch_orders' => $this->handleOrderIntent($intentData, $customerQuery),
                'draft_email' => $this->handleEmailDrafting($intentData),
                'discount_query' => $this->handleDiscountIntent($intentData),
                'payment_query' => $this->handlePaymentIntent($intentData),
                'refund_query' => $this->handleRefundIntent($intentData),
                default => $this->handleGeneralQuery($customerQuery)
            };

        } catch (\Exception $e) {
            Log::error('Reply generation failed: ' . $e->getMessage());
            return "Sorry, I'm having trouble processing your request. Please try again later.";
        }
    }

    /* ========================================================================
     *                      INTENT DETECTION & PROCESSING
     * ======================================================================== */

    private function detectIntentWithModel(string $query): array
    {
        try {
            $response = Http::timeout(3)
                ->retry(3, 100)
                ->post(config('services.intent_model.endpoint'), [
                    'query' => $query,
                    'context' => $this->getConversationContext()
                ])->throw()->json();

            return [
                'intent' => $response['intent'] ?? 'general_help',
                'entities' => $response['entities'] ?? [],
                'confidence' => $response['confidence'] ?? 0,
                'fallback_reason' => $response['fallback_reason'] ?? null
            ];

        } catch (\Exception $e) {
            Log::error('Model request failed: ' . $e->getMessage());
            return $this->getFallbackIntent($query);
        }
    }

    private function getFallbackIntent(string $query): array
    {
        $legacyIntent = $this->detectIntentLegacy($query);
        return $this->mapLegacyIntent($legacyIntent);
    }

    private function detectIntentLegacy(string $query): array
    {
        // Original regex-based detection implementation
        $lowerQuery = strtolower($query);
        $intent = [
            'helpRequest' => false,
            'checkNewEmails' => false,
            // ... other legacy intent flags
        ];

        // Implement original regex checks here
        if (preg_match('/\b(remove|delete|clear)\b.*\bcache\b/i', $query)) {
            $intent['removeCache'] = true;
        }
        // ... other regex checks

        return $intent;
    }

    /* ========================================================================
     *                          INTENT HANDLERS
     * ======================================================================== */

    private function handleOrderIntent(array $intentData, string $query): string
    {
        $orderNumber = $this->extractOrderNumberFromQuery($query, $intentData);
        
        if (!$orderNumber) {
            return "Please provide an order number so I can help you.";
        }

        $order = $this->fetchOrderDetails($orderNumber);
        
        if (!$order) {
            return "Order #$orderNumber not found. Please verify the order number.";
        }

        $this->cacheOrderContext($order, $intentData);

        return match($intentData['intent']) {
            'FETCH_ORDER_STATUS' => $this->formatOrderStatus($order),
            'FETCH_SHIPPING_STATUS' => $this->formatShippingStatus($order),
            'FETCH_PAYMENT_STATUS' => $this->formatPaymentStatus($order),
            'FETCH_ORDER_ITEMS_COUNT' => $this->formatItemCount($order),
            'FETCH_ORDER_PRODUCTS' => $this->formatOrderProducts($order),
            'FETCH_TRACKING_INFO' => $this->formatTrackingInfo($order),
            'FETCH_SHIPPING_ADDRESS' => $this->formatShippingAddress($order),
            default => $this->formatOrderDetails([$order])
        };
    }

    private function handleEmailIntent(array $intentData): string
    {
        $gmailService = app(GmailService::class);

        return match($intentData['intent']) {
            'CHECK_NEW_EMAILS' => $this->formatEmailCount($gmailService->fetchUnreadEmails()),
            'OPEN_LATEST_EMAIL' => $this->formatEmailContent($gmailService->getLatestEmail()),
            'OPEN_EMAIL_FROM' => $this->handleSpecificSenderEmail($intentData),
            default => 'Email handling not implemented yet'
        };
    }

    private function handleEmailDrafting(array $intentData): string
    {
        $orderNumber = $intentData['entities']['order_number'] ?? null;
        $order = $orderNumber ? $this->fetchOrderDetails($orderNumber) : null;

        return match($intentData['intent']) {
            'DRAFT_EMAIL_DELAYED_ORDER' => $this->draftDelayEmail($order),
            'DRAFT_EMAIL_REFUND_CONFIRMATION' => $this->draftRefundEmail($order),
            'DRAFT_EMAIL_SHIPPING_CONFIRMATION' => $this->draftShippingConfirmation($order),
            default => 'Email drafting not implemented yet'
        };
    }

    /* ========================================================================
     *                          FORMATTING METHODS
     * ======================================================================== */

    private function formatOrderStatus(array $order): string
    {
        $status = $order['fulfillment_status'] ?? 'processing';
        $orderNumber = $order['order_number'] ?? 'N/A';
        
        $statusMap = [
            'fulfilled' => "✅ Order #$orderNumber has been shipped",
            'partial' => "⏳ Order #$orderNumber is partially fulfilled",
            'unfulfilled' => "📦 Order #$orderNumber is being processed",
            'cancelled' => "❌ Order #$orderNumber was cancelled",
        ];

        return $statusMap[strtolower($status)] ?? "ℹ️ Current status: " . ucfirst($status);
    }

    private function formatTrackingInfo(array $order): string
    {
        $tracking = $order['tracking_info'] ?? [];
        
        if (empty($tracking)) {
            return "No tracking information available for order #{$order['order_number']}";
        }

        return implode("\n", [
            "📦 Tracking info for order #{$order['order_number']}:",
            "• Carrier: {$tracking['carrier']}",
            "• Tracking #: {$tracking['number']}",
            "• Status: {$tracking['status']}",
            "• Last update: {$this->formatDate($tracking['updated_at'])}"
        ]);
    }

    private function formatEmailCount(array $emails): string
    {
        $count = count($emails);
        return $count > 0 
            ? "📬 You have $count new emails"
            : "📭 No new emails in your inbox";
    }

    /* ========================================================================
     *                          EMAIL DRAFT TEMPLATES
     * ======================================================================== */

    private function draftDelayEmail(?array $order): string
    {
        if (!$order) {
            return "Unable to draft email - order information missing";
        }

        return implode("\n\n", [
            "✉️ Draft Email for Order #{$order['order_number']}:",
            "Subject: Update on Your Order #{$order['order_number']}",
            "Hi {$order['customer']['first_name']},",
            "We wanted to inform you that your order is experiencing a slight delay. ",
            "We apologize for the inconvenience and will notify you once it ships.",
            "Best regards,\nCustomer Support Team"
        ]);
    }

    private function draftRefundEmail(?array $order): string
    {
        // Similar template structure with refund-specific content
    }

    /* ========================================================================
     *                          SHOPIFY INTEGRATION
     * ======================================================================== */

    private function fetchOrderDetails(string $orderNumber): ?array
    {
        try {
            $response = Http::shopify()
                ->retry(3, 100)
                ->get("/orders.json", [
                    'name' => $orderNumber,
                    'status' => 'any',
                    'fields' => implode(',', [
                        'id,name,created_at,financial_status,fulfillment_status',
                        'total_price,currency,customer,shipping_address',
                        'line_items,tracking_info,discount_codes'
                    ])
                ]);

            return $response->json('orders.0');

        } catch (\Exception $e) {
            Log::error("Shopify API failure: " . $e->getMessage());
            return null;
        }
    }

    /* ========================================================================
     *                          CONTEXT MANAGEMENT
     * ======================================================================== */

    private function getConversationContext(): array
    {
        return Cache::remember('conversation_context', $this->config['cache_ttl'], function () {
            return [
                'recent_orders' => $this->getRecentOrdersFromCache(),
                'common_queries' => $this->getCommonQueryPatterns(),
                'customer_preferences' => $this->getCustomerPreferences()
            ];
        });
    }

    private function cacheOrderContext(array $order, array $intentData): void
    {
        $context = Cache::get('order_context', []);
        $context[$order['order_number']] = [
            'last_accessed' => now(),
            'intent' => $intentData,
            'customer' => $order['customer']['email'] ?? null
        ];
        Cache::put('order_context', $context, $this->config['cache_ttl']);
    }

    /* ========================================================================
     *                          UTILITIES & HELPERS
     * ======================================================================== */

    private function extractOrderNumberFromQuery(string $query, array $intentData): ?string
    {
        return $intentData['entities']['order_number'] 
            ?? $this->extractOrderNumberLegacy($query)
            ?? $this->getLastOrderNumberFromCache();
    }

    private function extractOrderNumberLegacy(string $query): ?string
    {
        preg_match('/#?(\d{5,})/i', $query, $matches);
        return $matches[1] ?? null;
    }

    private function formatDate(string $dateString): string
    {
        try {
            return now()->parse($dateString)->diffForHumans();
        } catch (\Exception $e) {
            return $dateString;
        }
    }

    /* ========================================================================
     *                          FALLBACK HANDLING
     * ======================================================================== */

    private function handleLowConfidenceQuery(string $query): string
    {
        $logContext = [
            'query' => $query,
            'confidence' => $intentData['confidence'] ?? 0
        ];
        
        Log::warning('Low confidence intent detection', $logContext);
        
        return $this->isOrderRelatedQuery($query)
            ? "I'm not sure I understand. Could you please provide the order number?"
            : $this->callOpenAIFallback($query);
    }

    private function callOpenAIFallback(string $query): string
    {
        try {
            return app(OpenAIService::class)->generateResponse(
                "User asked: $query. Provide a concise helpful response."
            );
        } catch (\Exception $e) {
            Log::error('OpenAI fallback failed: ' . $e->getMessage());
            return "I'm sorry, I didn't understand that. Could you rephrase your question?";
        }
    }
//     public function generateReply(string $customerQuery, bool $useSlackBlocks = false, ?string $slackUserId = null): string|array
// {
//     // 1. Detect overall intent (/help, etc.)
//     $intent = $this->detectIntent($customerQuery);
//     Log::info('Intent detected:', $intent);

//     if ($intent['removeCache']) {
//         Cache::flush(); 
//         return "All conversation caches have been successfully removed.";
//     }

//     if ($intent['helpRequest']) {
//         return $this->generateHelpResponse($useSlackBlocks);
//     }

//     // ✅ Check for New Emails
//     if ($intent['checkNewEmails']) {
//         $newEmails = app(GmailService::class)->fetchUnreadEmails();
//         return "📬 You have " . count($newEmails) . " new emails.";
//     }

//     // ✅ Handle "get last X orders"
//     if ($intent['fetchLastOrders'] > 0) {
//         $lastOrders = $this->fetchShopifyOrders(null, null, false, $intent['fetchLastOrders']);
//         if ($lastOrders->isNotEmpty()) {
//             return $this->formatOrderDetails($lastOrders);
//         }
//         return "No recent orders found.";
//     }

//     // ✅ Open Latest Email
//     if ($intent['openLatestEmail']) {
//         $emailContent = app(GmailService::class)->getLatestEmail();
//         return "📬 *Latest Email:*\n\n" . $emailContent;
//     }

//     // ✅ Open Specific Email by Sender
//     if ($intent['openEmailFrom']) {
//         $emailContent = app(GmailService::class)->getLatestEmailBySender($intent['openEmailFrom']);
//         return "📩 *Email from:* {$intent['openEmailFrom']}\n\n" . $emailContent;
//     }

//     // ✅ Request Specific Field (Order-Related)
//     if ($intent['specificField']) {
//         $orderNumber = $this->extractOrderNumber($customerQuery);
//         $email       = $this->extractEmail($customerQuery);

//         $orders = $this->fetchRelevantOrders($intent, $orderNumber, $email);
//         if ($orders->isNotEmpty()) {
//             $fieldValue = $orders->first()[$intent['specificField']] ?? null;
//             if ($fieldValue) {
//                 return "Requested info ({$intent['specificField']}): {$fieldValue}";
//             } else {
//                 if ($orders->count() === 1 && $orderNumber) {
//                     $shopifyData = $this->fetchSingleOrderFromShopify($orderNumber);
//                     $fieldValue = $this->extractAdditionalField($shopifyData, $intent['specificField']);
//                     if ($fieldValue) {
//                         return "Requested info ({$intent['specificField']}): {$fieldValue}";
//                     }
//                 }
//                 return "Information for ({$intent['specificField']}) not available.";
//             }
//         }
//     }

//     // ✅ If user requests the last referenced order
//     if ($intent['lastRecord']) {
//         $cacheKey    = $this->determineCacheKey($slackUserId);
//         $contextData = $this->getCachedContext($cacheKey);
//         $lastOrderResponse = $this->handleLastRecordRequest($contextData, $useSlackBlocks);
//         if ($lastOrderResponse) {
//             return $lastOrderResponse;
//         }
//     }

//     // ✅ Handle General Queries (Fallback)
//     if ($intent['cleanQuery']) {
//         $prompt = "User asked: {$intent['cleanQuery']}. Provide a concise and helpful response.";
//         return $this->callOpenAI($prompt);
//     }

//     // Default Fallback Response
//     return $intent['cleanQuery'];
// }


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
        'fetchLastOrders'   => 0,
        'checkNewEmails'    => false,
        'openLatestEmail'   => false,
        'openEmailFrom'     => null,
        'cleanQuery'        => null,
    ];

    // ✅ Remove Cache
    if (preg_match('/\b(remove|delete|clear)\b.*\bcache\b/i', $query)) {
        $intent['removeCache'] = true;
    }

    // ✅ Help Request
    if (str_contains($lowerQuery, '/help') || preg_match('/\bhelp\b/i', $query)) {
        $intent['helpRequest'] = true;
    }

    // ✅ Update Request
    if (preg_match('/\b(updated data|refresh data|latest data|get recent data|fetch latest|update info|refresh info|current status|latest status)\b/i', $query)) {
        $intent['updateRequest'] = true;
    }

    // ✅ Last Record Request
    if (preg_match('/\b(last record|previous order|recent order|show last|latest order|last details)\b/i', $query)) {
        $intent['lastRecord'] = true;
    }

    // ✅ Fetch Last X Orders
    if (preg_match('/\b(?:last|recent|show|fetch|give|get)\s*(?:me)?\s*(\d+)\s*orders?\b/i', $query, $matches)) {
        $intent['fetchLastOrders'] = (int) $matches[1];
    }

    // ✅ Check for New Emails
    if (preg_match('/\b(check|any|get|show)\s*(?:new|unread)?\s*emails?\b/i', $query)) {
        $intent['checkNewEmails'] = true;
    }

    // ✅ Detect "Open Latest Email"
    if (preg_match('/\bopen (?:the )?latest email\b/i', $query)) {
        $intent['openLatestEmail'] = true;
    }

    // ✅ Detect Email Addresses (Standard & Slack Format)
    if (preg_match('/(?:open email\s*)?(?:<mailto:)?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})(?:\|.*?>)?/i', $query, $matches)) {
        $intent['openEmailFrom'] = $matches[1];  // Extract email address correctly
    }

    // ✅ Specific Field Requests
    $specificFieldPatterns = [
        'email_address' => '/\b(order email|order e-mail|order mail address)\b/i',
        'customer_name' => '/\b(order name|order first name|order last name|order customer name)\b/i',
        'phone'         => '/\b(order phone|order contact number|order mobile)\b/i',
    ];
    foreach ($specificFieldPatterns as $field => $pattern) {
        if (preg_match($pattern, $query)) {
            $intent['specificField'] = $field;
            break;
        }
    }

    // ✅ Fallback to Clean Query if No Intent Detected
    if (
        !$intent['helpRequest'] &&
        !$intent['updateRequest'] &&
        !$intent['lastRecord'] &&
        !$intent['specificField'] &&
        !$intent['removeCache'] &&
        !$intent['fetchLastOrders'] &&
        !$intent['checkNewEmails'] &&
        !$intent['openLatestEmail'] &&
        !$intent['openEmailFrom']
    ) {
        $intent['cleanQuery'] = $query;
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
