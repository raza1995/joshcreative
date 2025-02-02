<?php
namespace App\Services;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use App\Models\ShopifyOrder;
use Google\Service\Gmail\Draft;
use Illuminate\Support\Facades\Log;

class GmailService
{
    protected $client;
    protected $tokenPath;

    protected $service;

    public function __construct()
    {
        $this->tokenPath = storage_path('app/token.json');

        $this->client = new Client();
        $this->client->setAuthConfig(storage_path('app/credentials.json'));
        $this->client->addScope(Gmail::MAIL_GOOGLE_COM);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');

        $this->authenticate();
        $this->service = new Gmail($this->client);
    }
    private function authenticate()
    {
        if (file_exists($this->tokenPath)) {
            $accessToken = json_decode(file_get_contents($this->tokenPath), true);
            $this->client->setAccessToken($accessToken);

            // Refresh token if expired
            if ($this->client->isAccessTokenExpired()) {
                Log::warning("Google API token expired. Attempting refresh...");
                
                if ($this->client->getRefreshToken()) {
                    $newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    $this->client->setAccessToken($newAccessToken);
                    file_put_contents($this->tokenPath, json_encode($newAccessToken));
                    Log::info("Google API token refreshed successfully.");
                } else {
                    Log::error("No refresh token available. Re-authentication required.");
                    throw new \Exception("Google API requires re-authentication. Run manually to generate a new token.");
                }
            }
        } else {
            Log::error("Google API token file not found. Please authenticate manually.");
            throw new \Exception("Google API token missing. Authenticate manually.");
        }
    }

    public function fetchEmails()
{
    $user = 'me';
    $mainLabel = 'HIGH'; // Parent category
    $subCategory = '📦 Wheres my Order?';
    // Fetch all Gmail labels
    $labelId = $this->findLabelId($mainLabel, $subCategory);

    if (!$labelId) {
        Log::error("Label '$mainLabel-$subCategory' not found in Gmail.");
        return;
    }

    Log::info("✅ Found label '$mainLabel-$subCategory' (ID: $labelId). Fetching emails...");

    $pageToken = null;
    do {
        $params = [
            'maxResults' => 100, // Limit results per request
            'labelIds' => [$labelId], // Fetch only emails with this label
        ];

        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        $results = $this->service->users_messages->listUsersMessages($user, $params);

        if ($results->getMessages()) {
            foreach ($results->getMessages() as $message) {
                $this->processEmail($message->getId());
            }
        }

        $pageToken = $results->getNextPageToken();

    } while ($pageToken);

    Log::info("✅ All labeled emails fetched successfully.");
}

    
private function findLabelId($mainLabel, $subCategory)
{
    $user = 'me';

    try {
        $labels = $this->service->users_labels->listUsersLabels($user)->getLabels();
        Log::info("Fetched Gmail labels data: " . json_encode($labels));
        foreach ($labels as $label) {
            $labelName = strtolower($label->getName());

            // Check if the main label (e.g., 'high') and sub-category exist in the label name
            if (strpos($labelName, strtolower($mainLabel)) !== false && strpos($labelName, strtolower($subCategory)) !== false) {
                return $label->getId();
            }
        }
    } catch (\Exception $e) {
        Log::error("Error fetching Gmail labels: " . $e->getMessage());
    }

    return null;
}
    
private function processEmail($messageId)
{
    $user = 'me';
    $message = $this->service->users_messages->get($user, $messageId, ['format' => 'full']);
    $headers = $message->getPayload()->getHeaders();

    // Extract sender's email
    $fromEmail = null;
    foreach ($headers as $header) {
        if ($header->getName() === 'From') {
            preg_match('/<(.+)>/', $header->getValue(), $matches);
            $fromEmail = $matches[1] ?? $header->getValue();
            break;
        }
    }

    if (!$fromEmail) {
        Log::warning("Could not extract sender email from message ID: $messageId");
        return;
    }

    // Check if customer email exists in Shopify Orders
    $order = ShopifyOrder::where('email_address', $fromEmail)
        ->whereNotNull('tracking_number')
        ->whereNotNull('tracking_url')
        ->first();

    if ($order) {
        Log::info("✅ Found order for email: $fromEmail, creating draft...");
        $this->generateAndDraftEmail($fromEmail, $order);
    } else {
        Log::info("❌ No tracking info found for email: $fromEmail");
    }
}

    
    private function generateAndDraftEmail($email, $order)
    {
        // Generate AI-powered draft
        $aiResponse = $this->generateEmailContent($order);

        // Save as draft in Gmail
        $this->createDraft($email, $aiResponse);
    }

    private function generateEmailContent($order)
{
    $openai = \OpenAI::client(config('services.openai.api_key'));

    $user_prompt = "A customer’s order was delayed due to a fire in Los Angeles, but it has now been shipped. 
    The order details are make a link of tracking URL and Tracking number in seperate line:
    - Tracking Number: {$order->tracking_number}
    - Tracking URL: {$order->tracking_url}
    - Customer Name: {$order->customer_name}
    
    Write a professional and friendly apology email to inform them of the delay and provide the tracking details.";

    $response = $openai->chat()->create([
        'model' => 'gpt-4o', // Use GPT-4o model
        'messages' => [
            ["role" => "system", "content" => "You write professional and friendly apology emails based on order details."],
            ["role" => "user", "content" => $user_prompt],
        ],
        'temperature' => 0.7,
        'max_tokens' => 200,
    ]);

    return $response->choices[0]->message->content ?? "Dear customer, your order has been dispatched. Your tracking number is {$order->tracking_number}.";
}


private function createDraft($to, $messageContent)
{
    $user = 'me';

    // Construct the raw email message
    $rawMessage = "To: $to\r\n";
    $rawMessage .= "Subject: Your Order Has Been Dispatched\r\n";
    $rawMessage .= "MIME-Version: 1.0\r\n";
    $rawMessage .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $rawMessage .= $messageContent;

    // Encode the message
    $rawMessage = base64_encode($rawMessage);
    $rawMessage = str_replace(['+', '/', '='], ['-', '_', ''], $rawMessage);

    // Create the Gmail Message object
    $message = new Message();
    $message->setRaw($rawMessage);

    // **Wrap the message inside a Draft object**
    $draft = new Draft();
    $draft->setMessage($message);

    // Create the draft in Gmail
    $this->service->users_drafts->create($user, $draft);

    Log::info("✅ Draft email created for: $to");
}



public function applyLabel($messageId, $labelName)
{
    $labelId = $this->getLabelId($labelName);

    if (!$labelId) {
        Log::warning("Label '$labelName' not found, skipping labeling.");
        return;
    }

    $this->service->users_messages->modify('me', $messageId, ['addLabelIds' => [$labelId]]);
    Log::info("Applied label '$labelName' to email $messageId.");
}

private function getLabelId($labelName)
{
    $labels = $this->service->users_labels->listUsersLabels('me')->getLabels();

    foreach ($labels as $label) {
        if (strtolower($label->getName()) === strtolower($labelName)) {
            return $label->getId();
        }
    }

    return null;
}
public function startWatch()
{
    try {
        $watchRequest = new \Google\Service\Gmail\WatchRequest([
            'labelIds' => ['INBOX'], // Watch only Inbox
            'topicName' => 'projects/gmail-api-449711/topics/gmail-webhook-topic' // Use your actual topic name
        ]);

        $response = $this->service->users->watch('me', $watchRequest);
        Log::info("Gmail Watch started. Expiration: " . $response->expiration);
    } catch (\Exception $e) {
        Log::error("Error starting Gmail Watch: " . $e->getMessage());
    }
}


}
