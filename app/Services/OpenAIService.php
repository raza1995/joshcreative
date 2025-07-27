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
        $this->shopifyDomain = env('SHOPIFY_STORE_DOMAIN', 'dummy.myshopify.com');

        $this->accessToken     = env('SHOPIFY_ACCESS_TOKEN','');
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
        Cache::flush(); 
        return "All conversation caches have been successfully removed.";
    }

    if ($intent['helpRequest']) {
        return $this->generateHelpResponse($useSlackBlocks);
    }

    // ✅ Check for New Emails
    if ($intent['checkNewEmails']) {
        $newEmails = app(GmailService::class)->fetchUnreadEmails();
        return "📬 You have " . count($newEmails) . " new emails.";
    }

    // ✅ Handle "get last X orders"
    if ($intent['fetchLastOrders'] > 0) {
        $lastOrders = $this->fetchShopifyOrders(null, null, false, $intent['fetchLastOrders']);
        if ($lastOrders->isNotEmpty()) {
            return $this->formatOrderDetails($lastOrders);
        }
        return "No recent orders found.";
    }

    // ✅ Open Latest Email
    if ($intent['openLatestEmail']) {
        $emailContent = app(GmailService::class)->getLatestEmail();
        return "📬 *Latest Email:*\n\n" . $emailContent;
    }

    // ✅ Open Specific Email by Sender
    if ($intent['openEmailFrom']) {
        $emailContent = app(GmailService::class)->getLatestEmailBySender($intent['openEmailFrom']);
        return "📩 *Email from:* {$intent['openEmailFrom']}\n\n" . $emailContent;
    }

    if ($intent['generateReplyEmail']) {
        Log::info('Generating reply email intent detected.');
        $email = $this->extractEmail($customerQuery);
        Log::info('Extracted email:', ['email' => $email]);

        if ($email) {
            $lastConversation = $this->getLastConversationByEmail($email);
            Log::info('Last conversation retrieved:', ['lastConversation' => $lastConversation]);
        
            if ($lastConversation) {
                // ✅ Convert to JSON if it's an array BEFORE passing it to generateEmailReply
                $conversationData = is_array($lastConversation) ? json_encode($lastConversation, JSON_PRETTY_PRINT) : $lastConversation;
        
                $replyContent = $this->generateEmailReply($conversationData);
                Log::info('Generated reply content:', ['replyContent' => $replyContent]);
        
                return "✉️ *Generated Reply:*\n\n" . $replyContent;
            }
            Log::warning('No conversation history found for email:', ['email' => $email]);
            return "❌ No conversation history found for {$email}.";
        }
        
        Log::warning('No valid email found in customer query:', ['customerQuery' => $customerQuery]);
        return "❌ No valid email found to generate a reply.";
    }

    // ✅ Request Specific Field (Order-Related)
    if ($intent['specificField']) {
        $orderNumber = $this->extractOrderNumber($customerQuery);
        $email       = $this->extractEmail($customerQuery);

        $orders = $this->fetchRelevantOrders($intent, $orderNumber, $email);
        if ($orders->isNotEmpty()) {
            $fieldValue = $orders->first()[$intent['specificField']] ?? null;
            if ($fieldValue) {
                return "Requested info ({$intent['specificField']}): {$fieldValue}";
            } else {
                if ($orders->count() === 1 && $orderNumber) {
                    $shopifyData = $this->fetchSingleOrderFromShopify($orderNumber);
                    $fieldValue = $this->extractAdditionalField($shopifyData, $intent['specificField']);
                    if ($fieldValue) {
                        return "Requested info ({$intent['specificField']}): {$fieldValue}";
                    }
                }
                return "Information for ({$intent['specificField']}) not available.";
            }
        }
    }



    // ✅ If user requests the last referenced order
    if ($intent['lastRecord']) {
        $cacheKey    = $this->determineCacheKey($slackUserId);
        $contextData = $this->getCachedContext($cacheKey);
        $lastOrderResponse = $this->handleLastRecordRequest($contextData, $useSlackBlocks);
        if ($lastOrderResponse) {
            return $lastOrderResponse;
        }
    }

    // ✅ Handle General Queries (Fallback)
    if ($intent['cleanQuery']) {
        $prompt = "User asked: {$intent['cleanQuery']}. Provide a concise and helpful response.";
        return $this->callOpenAI($prompt);
    }

    // Default Fallback Response
    return $intent['cleanQuery'];
}
private function getLastConversationByEmail(string $email)
{
    $conversation = Conversation::where('user_identifier', $email)
                                ->latest('updated_at')
                                ->first();

    if ($conversation) {
        return $conversation->conversation_data; // Assuming it's JSON or text
    }

    return null;
}

private function generateEmailReply(string $conversationData): string
{
    Log::info('Conversation Data:', ['conversationData' => $conversationData]);
    $conversationData = is_array($conversationData) ? json_encode($conversationData, JSON_PRETTY_PRINT) : $conversationData;

    $prompt = <<<EOT
You are an AI Smart responsible for drafting professional, friendly, and empathetic email replies and also you can understand json data array data coding to craft emails.

Based on the following conversation history, draft a clear, polite, and helpful response.
Also add the name of the customer 
---

**Conversation History:**
{$conversationData}

---

**Reply Instructions:**
1. Start with a warm, professional greeting.
2. Acknowledge the customer's message and express gratitude.
3. Address any questions, concerns, or feedback directly.
4. Provide clear, concise, and helpful information if needed.
5. End with a friendly and professional sign-off.
6. Data is in the json so break the data and see the body key to understand the conversation and according to that generate reply.
Ensure the tone is empathetic, polite, and concise.

Draft the reply now:
EOT;

    // Call OpenAI to generate the response
    $response = $this->callOpenAI($prompt);

    // Return the generated response or a fallback message
    return $response ?: "Unable to generate a reply at the moment.";
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
             'generateReplyEmail'       => false,
             'fetchLastOrders'   => 0,          
             'checkNewEmails'    => false, 
             'openLatestEmail' => false,  
        'openEmailFrom'   => null, 
        'cleanQuery'        => null,
           
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
     
         if (preg_match('/\blatest emails\b/i', $query)) {
            $intent['openLatestEmail'] = true;
        }
    
        // Optional: Detect if an email address is specified
        if (preg_match('/\bopen email from\s+<mailto:([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\|.*?>/i', $query, $matches)) {
            $intent['openEmailFrom'] = $matches[1];
            Log::info('Detected open email from request.', [
                'query' => $query,
                'matches' => $matches,
                'intent' => $intent['openEmailFrom']
            ]);
        }


        if (preg_match('/\b(generate|draft|write|create|compose|prepare|formulate|build|craft|send)\s*(?:a\s*)?(reply|email reply|email response|response|follow-up|customer reply|customer response|email draft|message|customer message)?\s*(?:for\s*)?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})?\b/i', $query, $matches)) {
            Log::info('Detected generate reply email request.');
            $intent['generateReplyEmail'] = true;
        
            // Extract email if present
            if (isset($matches[3])) {
                $intent['email'] = $matches[3];
            }
        }
        
         // Specific field requests
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
         if (!$intent['helpRequest'] && !$intent['updateRequest'] && !$intent['lastRecord'] &&
         !$intent['removeCache'] && !$intent['fetchLastOrders'] && !$intent['checkNewEmails'] &&
         !$intent['openEmailFrom'] && !$intent['specificField'] && !$intent['generateReplyEmail']
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
*🆘 Here are some commands you can use:*

**📦 Order Management:**
- *Lookup by Order Number:* 
  - "Show me order #1234" or "Find status of order 1002"
- *Lookup by Email:* 
  - "Any orders for example@example.com?"
- *Fetch Last Orders:* 
  - "Get the last 5 orders" or "Show me recent orders"
- *Specific Order Info:* 
  - "What's the email address on that order?" 
  - "Give me the customer's phone number"

**📧 Email Handling:**
- *Check New Emails:* 
  - "Check new emails" or "Show unread emails"
- *Open Latest Email:* 
  - "Open the latest email"
- *Open Email from Sender:* 
  - "Open email from example@gmail.com"

**✍️ Generate Replies:**
- *Draft Email Replies:* 
  - "Generate a reply for john.doe@example.com"
  - "Draft an email response for jane@example.com"
- *Compose Follow-ups:* 
  - "Write a follow-up email" 
  - "Create a customer response"

**🔄 Data Updates & Cache:**
- *Refresh Data:* 
  - "Refresh data for order #1234" or "Get the latest data"
- *Clear Cache:* 
  - "Remove cache" or "Clear all cached data"

**🗂️ Conversation History:**
- *Show Last Record:* 
  - "Show me the last record" or "What was the previous order we discussed?"

**ℹ️ General Help:**
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
public function generateEmailDraft($emailContent, $shopifyOrder = null)
{
    $supportAgentName = "Justine";
    $companyName = "Mycolean";
    $contactInfo = "support@mycolean.com";

    $prompt = "You are an AI customer service assistant. Your task is to generate a professional, friendly, and empathetic **HTML-formatted email** in response to the customer's inquiry below.\n\n";
    $prompt .= "📩 **Customer Inquiry:**\n\"$emailContent\"\n\n";

    if ($shopifyOrder) {
        $orderDetails = json_encode($shopifyOrder, JSON_PRETTY_PRINT);

        $prompt .= <<<EOT
🛒 **Order Details (JSON Format):**
$orderDetails

✅ **Strict Instructions for Drafting the Email:**
1. **Use valid HTML format only** (no markdown, plain text, or extra code blocks).
2. **Do NOT include** `<html>`, `<head>`, `<body>`, or `<!DOCTYPE>` tags. Only return the content within the `<body>`.
3. Structure the email using appropriate HTML tags:
   - Paragraphs (`<p>`) for readability.
   - Bold (`<strong>`) and italic (`<em>`) for emphasis.
   - Bullet points (`<ul><li>`) for listing items clearly.
   - Line breaks (`<br>`) where necessary for better formatting.
4. **Greeting:** Start with "Dear [Customer Name]," if available, or use "Hi [Customer Name],".
5. **Order Information:** Include:
   - **Order Number:** [Insert Order Number]
   - **Tracking Number:** [Insert Tracking Number]
   - **Tracking Link:** [Insert Clickable Tracking Link]
   - **Product Details:** [Product Name/Details]
6. **Shipping Update:** Clearly mention the shipping status or estimated delivery date.
7. **Closing:** Use the following signature format.

📬 **Email Signature (HTML Format):**
<p>Thank you for choosing {$companyName}.</p>
<p>Best regards,<br>
<strong>{$supportAgentName}</strong><br>
Customer Support Team<br>
{$companyName}<br>
<a href="mailto:{$contactInfo}">{$contactInfo}</a></p>

⚠️ **Important Notes:**
- Ensure the email is well-structured in HTML, without markdown or plain text formatting.
- Maintain a polite, professional, and empathetic tone.
- **Return ONLY the HTML body content, without any `<html>`, `<head>`, or `<!DOCTYPE>` tags.**

Generate the HTML-formatted email below:
EOT;
    }

    $response = Http::withToken($this->apiKey)->post('https://api.openai.com/v1/chat/completions', [
        'model' => 'gpt-4o',
        'messages' => [
            ["role" => "system", "content" => "You are an AI customer service assistant specializing in professional email drafting."],
            ["role" => "user", "content" => $prompt],
        ],
        'temperature' => 0.5,
        'max_tokens' => 800,
    ]);

    $content = $response->json()['choices'][0]['message']['content'] ?? "Thank you for reaching out. We'll get back to you shortly.";

    $content = preg_replace([
        '/<\/?(html|head|body|!DOCTYPE)[^>]*>/i',   // Remove HTML structural tags
        '/```html|```/i'                           // Remove markdown code blocks like ```html and ```
    ], '', $content);

    return trim($content);
}




public function analyzeDraft($emailContent, $shopifyOrder)
{
    Log::info('Starting analyzeDraft method.', ['emailContent' => $emailContent, 'shopifyOrder' => $shopifyOrder]);

    $prompt = "
    📝 **AI Email Quality Assurance (QA) Evaluation**

    Analyze the following **customer service email draft** to determine if it qualifies for auto-sending without human review.

    ⚠️ **Note:** The email content is in **HTML format**, which is acceptable. Focus on the content's tone, clarity, and policy compliance, not the HTML tags.

    📩 **Email Draft (HTML):**
    \"$emailContent\"

    🛒 **Order Details:**
    " . json_encode($shopifyOrder, JSON_PRETTY_PRINT) . "

    🚀 **Evaluation Criteria:**
    1. **Tone Check:** The tone should be friendly, professional, and empathetic. *(Pass if polite and respectful, Fail if overly blunt or robotic.)*
    2. **Content Completeness:** The email must fully address the customer's inquiry with relevant information. *(Pass if all customer concerns are covered, Fail if any are missing.)*
    3. **Risk Assessment:** Identify potential risks:
       - **Low:** No sensitive issues, suitable for auto-send.
       - **Medium/High:** Sensitive issues like refunds, legal matters, or escalations (requires human review).
    4. **Policy Compliance:** Ensure the email aligns with company policies (e.g., no unauthorized refunds or false information).
    5. **Confidence Score:** Rate the email from 0-100 based on clarity, tone, and completeness. *(90+ = suitable for auto-send.)*

    ✅ **Output Format (JSON):**
    { 
        \"tone_check\": \"Pass/Fail\",
        \"content_check\": \"Pass/Fail\",
        \"risk_assessment\": \"Low/Medium/High\",
        \"policy_compliance\": \"Pass/Fail\",
        \"confidence_score\": \"0-100 (percentage)\",
        \"suggestions\": \"Optional improvements if needed.\"
    }

    ⚡ **Auto-Send Guidelines:**
    - Only mark emails as suitable for auto-sending if all criteria pass AND the confidence score is **90 or above**.
    - Ignore HTML formatting when analyzing content quality.
    - Provide constructive suggestions ONLY if necessary.

    Respond concisely in JSON format only.
    ";

    Log::info('Generated prompt for AI analysis.', ['prompt' => $prompt]);

    $response = Http::withToken($this->apiKey)->post('https://api.openai.com/v1/chat/completions', [
        'model' => 'gpt-4o',
        'messages' => [
            ["role" => "system", "content" => "You are an AI quality assurance bot specializing in customer service email analysis."],
            ["role" => "user", "content" => $prompt],
        ],
        'temperature' => 0.3,
        'max_tokens' => 600,
    ]);

    Log::info('Received response from OpenAI.', ['response' => $response->json()]);

    $analysisResult = json_decode($response->json()['choices'][0]['message']['content'], true);

    Log::info('Parsed analysis result.', ['analysisResult' => $analysisResult]);

    return $analysisResult;
}
public function generateAdInsights(
    array $ad,
    string $adId,
    string $primaryInterval,
    string $comparisonInterval
): string {
    $intervalText = ucfirst($comparisonInterval) . ' → ' . ucfirst($primaryInterval);
    $compareDate1 = $ad['compare_date_1'] ?? 'N/A';
    $compareDate2 = $ad['compare_date_2'] ?? 'N/A';
    $comparisonDates = strip_tags($ad['comparison_dates'] ?? '-');
    $primaryDates = strip_tags($ad['primary_dates'] ?? '-');

    // Prepare trend values
    $roasTrend = strip_tags($ad['roas_trend'] ?? '-');
    $cpaTrend = strip_tags($ad['cpa_trend'] ?? '-');
    $spendTrend = strip_tags($ad['spend_trend'] ?? '-');
    $ctrTrend = strip_tags($ad['ctr_trend'] ?? '-');
    $impTrend = strip_tags($ad['impressions_trend'] ?? '-');
    $clickTrend = strip_tags($ad['clicks_trend'] ?? '-');

    $roasCustom = strip_tags($ad['custom_trends']['ROAS'] ?? '-');
    $cpaCustom = strip_tags($ad['custom_trends']['CPA'] ?? '-');
    $spendCustom = strip_tags($ad['custom_trends']['Spend'] ?? '-');
    $ctrCustom = strip_tags($ad['custom_trends']['CTR'] ?? '-');
    $impCustom = strip_tags($ad['custom_trends']['Impressions'] ?? '-');
    $clickCustom = strip_tags($ad['custom_trends']['Clicks'] ?? '-');

    // Check for sufficient data
    $criticalValues = [$roasTrend, $cpaTrend, $spendTrend, $ctrTrend, $roasCustom, $cpaCustom, $spendCustom, $ctrCustom];
    $hasValidData = collect($criticalValues)->contains(function ($value) {
        return $value !== '-' && $value !== '';
    });

    if (!$hasValidData) {
        return 'Not enough performance data to generate insights.';
    }

    $prompt = <<<EOT
You're an expert Facebook Ads marketing analyst.

The data below compares performance across two different perspectives:

📅 Interval-Based Comparison:
- Interval: {$intervalText}
- Comparison Dates: {$comparisonDates}
- Primary Dates: {$primaryDates}
- Metrics:
  • ROAS: {$roasTrend}
  • CPA: {$cpaTrend}
  • Spend: {$spendTrend}
  • CTR: {$ctrTrend}
  • Impressions: {$impTrend}
  • Clicks: {$clickTrend}

📅 Direct Date Comparison:
- Compared Period (Date 1): {$compareDate1}
- Current Period (Date 2): {$compareDate2}
- Metrics:
  • ROAS: {$roasCustom}
  • CPA: {$cpaCustom}
  • Spend: {$spendCustom}
  • CTR: {$ctrCustom}
  • Impressions: {$impCustom}
  • Clicks: {$clickCustom}

🎯 Task:
1. Provide a summary (3–5 lines) of what’s happening with the ad’s performance across both comparisons.
2. Highlight any significant improvements or declines.
3. Give 3–5 specific, actionable marketing recommendations using clear language for non-technical business stakeholders.
Use emojis (📈 for improvements, 📉 for declines) where helpful.
EOT;

    try {
        $response = Http::withToken($this->apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a senior Facebook Ads marketing analyst tasked with summarizing ad trends and making smart recommendations.',
                ],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        return trim($response['choices'][0]['message']['content'] ?? 'No insights available.');
    } catch (\Exception $e) {
        
        return 'Unable to generate insights at this time.';
    }
}



 
}
