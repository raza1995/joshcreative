<?php

namespace App\Services;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\Draft;
use Google\Service\Gmail\ModifyMessageRequest;
use App\Models\ShopifyOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GmailShopifyInvoiceService
{
    protected $client;
    protected $service;
    protected $tokenPath;
    protected string $shopifyDomain;
    protected string $accessToken;
    public function __construct()
    {
        $this->tokenPath = storage_path('app/token.json');

        $this->client = new Client();
        $this->client->setAuthConfig(storage_path('app/credentials.json'));
        $this->client->addScope(Gmail::MAIL_GOOGLE_COM);
        $this->client->addScope('https://www.googleapis.com/auth/pubsub');
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
        $this->shopifyDomain = env('SHOPIFY_STORE_DOMAIN');
        $this->accessToken = env('SHOPIFY_ACCESS_TOKEN');
        $this->authenticate();
        $this->service = new Gmail($this->client);
    }

    private function authenticate()
    {
        if (file_exists($this->tokenPath)) {
            $accessToken = json_decode(file_get_contents($this->tokenPath), true);
            $this->client->setAccessToken($accessToken);

            if ($this->client->isAccessTokenExpired()) {
                Log::warning("🔄 Google API token expired. Refreshing...");

                if ($this->client->getRefreshToken()) {
                    $newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    $this->client->setAccessToken($newAccessToken);
                    file_put_contents($this->tokenPath, json_encode($newAccessToken));
                    Log::info("✅ Google API token refreshed.");
                } else {
                    Log::error("⚠️ No refresh token available. Re-authenticating required.");
                }
            }
        } else {
            Log::error("❌ Google API token file not found.");
        }
    }
    private function messageHasLabel($messageId, $labelId)
{
    try {
        $message = $this->service->users_messages->get('me', $messageId);
        return in_array($labelId, $message->getLabelIds() ?? []);
    } catch (\Exception $e) {
        Log::error("🚨 Error checking labels for message $messageId: " . $e->getMessage());
        return false;
    }
}
public function processLabeledEmails()
{
    $user = 'me';

    // Get all labels dynamically
    $labels = $this->getAllLabels();

    // Find the label ID dynamically based on text
    // $highPriorityLabelId = $this->getLabelIdByName($labels, '🔴 HIGH/3. ⚠️ 🛒 Can\'t Purchase!');
    $highPriorityLabelId = $this->getLabelIdByName($labels, 'HIGH/3. ⚠️ 🛒 Can\'t Purchase!');

    $solvedQueriesLabelId = $this->getLabelIdByName($labels, 'Replied');

    
   
    if (!$highPriorityLabelId) {
        Log::error("❌ '🔴 HIGH/3. ⚠️ 🛒 Can't Purchase!' label not found. Cannot proceed.");
        return;
    }

    if (!$solvedQueriesLabelId) {
        Log::error("❌ 'Solved Queries' label not found. Cannot proceed.");
        return;
    }

    try {
        Log::info("📩 Fetching emails with label ID: $highPriorityLabelId");

        // Fetch messages with the High Priority label
        $messages = $this->service->users_messages->listUsersMessages($user, [
            'labelIds' => [$highPriorityLabelId],
            'maxResults' => 20,
        ])->getMessages();

        if (!$messages) {
            Log::info("✅ No new unresolved queries found.");
            return;
        }

        foreach ($messages as $message) {
            $messageId = $message->getId();

            // Skip if the email already has the "Solved Queries" label
            if ($this->messageHasLabel($messageId, $solvedQueriesLabelId)) {
                Log::info("⏩ Skipping already processed email (Message ID: $messageId)");
                continue;
            }

            // Process the email and send invoice
            $emailSent = $this->handleLabeledEmail($messageId);

            // Only apply label if the email was successfully sent
            if ($emailSent) {
                Log::info("✅ Email successfully sent. Applying 'Solved Queries' label to Message ID: $messageId");
                $this->applyLabel($messageId, $solvedQueriesLabelId);
            } else {
                Log::warning("⚠️ Email was not sent. Skipping label application for Message ID: $messageId");
            }
        }
    } catch (\Exception $e) {
        Log::error("🚨 Error processing labeled emails: " . $e->getMessage());
    }
}



    private function getAllLabels()
{
    try {
        $labels = $this->service->users_labels->listUsersLabels('me')->getLabels();
        Log::info("✅ Retrieved Gmail Labels: " . json_encode($labels));
        return $labels;
    } catch (\Exception $e) {
        Log::error("🚨 Error fetching Gmail labels: " . $e->getMessage());
        return [];
    }
}

private function getLabelIdByName($labels, $labelName)
{
    foreach ($labels as $label) {
        if (stripos($label->getName(), $labelName) !== false) {
            Log::info("✅ Found label ID for '$labelName': " . $label->getId() . " (Actual Name: " . $label->getName() . ")");
            return $label->getId();
        }
    }
    
    Log::warning("❌ Label '$labelName' not found in Gmail.");
    return null;
}


private function handleLabeledEmail($messageId)
{
    try {
        Log::info("📩 Processing labeled email (Message ID: $messageId)");

        // Extract customer email
        $fromEmail = $this->getCustomerEmail(messageId: $messageId);

        if (!$fromEmail) {
            Log::warning("❌ No valid customer email found for Message ID: $messageId");
            return false;
        }

        Log::info("✅ Extracted customer email: $fromEmail");

        // Retrieve latest Shopify order for this customer
        $order = ShopifyOrder::where('email_address', $fromEmail)->latest('created_at')->first();

        if (!$order) {
            Log::info("❌ No order found for customer email: $fromEmail");
            return false;
        }

        Log::info("✅ Found latest Shopify order for customer", ['order_number' => $order->order_number]);

        // Fetch order details from Shopify
        $shopifyOrder = $this->fetchShopifyOrder($order->order_number);
        if (!$shopifyOrder) {
            Log::error("❌ Could not retrieve Shopify order details for order number: " . $order->order_number);
            return false;
        }

        // Log::info("✅ Shopify order retrieved", ['shopifyOrder' => $shopifyOrder]);

        // Create draft order and invoice
        $invoiceUrl = $this->createDraftOrderAndInvoice($shopifyOrder);
        if (!$invoiceUrl) {
            Log::error("❌ Failed to create draft order and invoice for order number: " . $order->order_number);
            return false;
        }

        Log::info("✅ Draft order and invoice created successfully", ['invoiceUrl' => $invoiceUrl]);

        // Send invoice email
        $emailSent = $this->sendInvoiceEmail($fromEmail, $invoiceUrl);

        return $emailSent; // Return true if email was sent, false otherwise

    } catch (\Exception $e) {
        Log::error("🚨 Error processing labeled email: " . $e->getMessage(), ['messageId' => $messageId]);
        return false;
    }
}


    private function getCustomerEmail($messageId)
    {
        $user = 'me';
    
        try {
            $message = $this->service->users_messages->get($user, $messageId, ['format' => 'full']);
            $headers = $message->getPayload()->getHeaders();
    
            Log::info("🔍 Extracting email for Message ID: $messageId");
    
            // Prioritized email fields
            $emailFields = ['Reply-To', 'Return-Path', 'From'];
            $customerEmail = null;
            $businessEmail = 'info@mycolean.com'; // Replace with your business email
    
            foreach ($emailFields as $field) {
                foreach ($headers as $header) {
                    if ($header->getName() === $field) {
                        preg_match('/<(.+)>/', $header->getValue(), $matches);
                        $extractedEmail = $matches[1] ?? $header->getValue();
    
                        // Ensure it's not the business email and is a valid customer email
                        if ($extractedEmail && stripos($extractedEmail, '@mycolean.com') === false) {
                            Log::info("✅ Found customer email in $field: $extractedEmail");
                            return $extractedEmail;
                        }
                    }
                }
            }
    
            Log::warning("❌ Could not extract valid customer email for Message ID: $messageId");
            return null;
    
        } catch (\Exception $e) {
            Log::error("🚨 Error extracting customer email for Message ID: $messageId - " . $e->getMessage());
            return null;
        }
    }
    
    private function fetchShopifyOrder($orderNumber)
    {  
        $shopifyApiUrl = "https://{$this->shopifyDomain}/admin/api/2024-01/orders.json?number=$orderNumber";
        
            
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->get($shopifyApiUrl);

        Log::info("Shopify API response for order fetch", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
            
        return $response->successful() ? ($response->json()['orders'][0] ?? null) : null;
    }

    private function createDraftOrderAndInvoice($shopifyOrder)
{
    $shopifyApiUrl = "https://{$this->shopifyDomain}/admin/api/2024-01/draft_orders.json";

    Log::info("📦 Creating draft order for customer: " . ($shopifyOrder['email'] ?? "unknown"));

    $payload = [
        "draft_order" => [
            "line_items" => $this->getLineItems($shopifyOrder),
            "customer" => [
                "email" => $shopifyOrder['email'] ?? "no-email@unknown.com"
            ],
            "applied_discount" => [
                "description" => "FORYOU30 Discount",
                "value" => "30",
                "value_type" => "percentage", // Apply as percentage discount
                "amount" => "0.00",           // Shopify calculates this automatically
            ],
            "send_invoice" => true,
            "note" => "Here is your invoice for payment with the FORYOU30 discount applied."
        ]
    ];

    try {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post($shopifyApiUrl, $payload);

        if ($response->successful()) {
            $invoiceUrl = $response->json()['draft_order']['invoice_url'] ?? null;
            Log::info("✅ Draft order created successfully. Invoice URL: $invoiceUrl");
            return $invoiceUrl;
        } else {
            Log::error("🚨 Failed to create draft order: " . $response->body());
            return null;
        }
    } catch (\Exception $e) {
        Log::error("🚨 Error while creating draft order: " . $e->getMessage());
        return null;
    }
}


    private function getLineItems($shopifyOrder)
    {
        return collect($shopifyOrder['line_items'])->map(function ($item) {
            return ["title" => $item['title'], "price" => $item['price'], "quantity" => $item['quantity']];
        })->toArray();
    }

    private function sendInvoiceEmail($to, $invoiceUrl)
    {
        $user = 'me';
    
        Log::info("📩 Preparing to send invoice email to: $to");
    
        try {
            $subject = "Your Invoice for Payment";
    
            // HTML Email Template
            $body = "
            <div style='font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;'>
                <div style='max-width: 600px; margin: auto; background: #ffffff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                    <h2 style='color: #333;'>Hello,</h2>
                    <p style='font-size: 16px; color: #555; line-height: 1.6;'>
                        <strong>We’ve already applied the <span style='color: #d9534f;'>FORYOU30</span> discount.</strong> 
                        It looks like there may be an internal issue causing the 
                        <strong>‘error shipping address’</strong> message.
                    </p>
                    <p style='font-size: 16px; color: #555;'>
                        Please give it another try. If it still doesn’t work, let us know—we’ll process a different invoice for you.
                    </p>
    
                    <!-- Payment Button -->
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='$invoiceUrl' 
                           style='background-color: #28a745; color: white; padding: 12px 24px; border-radius: 5px; text-decoration: none; font-weight: bold;'>
                            Pay Your Invoice
                        </a>
                    </div>
    
                    <p style='font-size: 16px; color: #555;'>
                        Wishing you Clarity and Balance,<br>
                        <strong>The Mycolean Team</strong>
                    </p>
                </div>
            </div>";
    
            // Construct Email Headers
            $rawMessage = "To: $to\r\n";
            $rawMessage .= "Subject: $subject\r\n";
            $rawMessage .= "MIME-Version: 1.0\r\n";
            $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";  // HTML Content-Type
            $rawMessage .= $body;
    
            // Encode message for Gmail API
            $rawMessage = base64_encode($rawMessage);
            $rawMessage = str_replace(['+', '/', '='], ['-', '_', ''], $rawMessage);
    
            // Create Gmail Message Object
            $message = new Message();
            $message->setRaw($rawMessage);
    
            Log::info("📤 Sending invoice email to Gmail API...");
    
            // Send the email
            $this->service->users_messages->send($user, $message);
    
            Log::info("✅ Invoice email successfully sent to: $to");
            return true;
    
        } catch (\Google\Service\Exception $gException) {
            Log::error("🚨 Google API Error sending invoice email: " . $gException->getMessage());
            Log::error("📌 Error Details: " . json_encode($gException->getErrors()));
            return false;
    
        } catch (\Exception $e) {
            Log::error("🚨 General Error sending invoice email: " . $e->getMessage());
            Log::error("📌 Stack Trace: " . $e->getTraceAsString());
            return false;
        }
    }
    


    

    private function applyLabel($messageId, $labelId, $userId = 'me')
    {
        try {
            $modifyReq = new ModifyMessageRequest();
            $modifyReq->setAddLabelIds([$labelId]);
            $modifyReq->setRemoveLabelIds([]);
            $this->service->users_messages->modify($userId, $messageId, $modifyReq);
            Log::info("✅ Applied label (ID: $labelId) to email $messageId.");
        } catch (\Exception $e) {
            Log::error("🚨 Error adding label: " . $e->getMessage());
        }
    }

    private function getLabelId($labelName)
    {
        foreach ($this->service->users_labels->listUsersLabels('me')->getLabels() as $label) {
            if (strtolower($label->getName()) === strtolower($labelName)) {
                return $label->getId();
            }
        }
        return null;
    }

    
}
