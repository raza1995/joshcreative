<?php
namespace App\Services;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use App\Models\ShopifyOrder;
use Google\Service\Gmail\Draft;
use Google\Service\Gmail\ModifyMessageRequest;
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
public function fetchNewEmails()
{
    try {
        $messages = $this->service->users_messages->listUsersMessages('me', [
            'labelIds' => ['INBOX'],
            'maxResults' => 10, // Fetch the latest 10 emails
        ])->getMessages();

        if (!$messages) {
            Log::info("No new messages found.");
            return [];
        }

        return $messages;
    } catch (\Exception $e) {
        Log::error("Error fetching new emails: " . $e->getMessage());
        return [];
    }
}




public function getUserLabelsMap($userId = 'me')
{
    $labelMap = [];
    try {
        $labelsResponse = $this->service->users_labels->listUsersLabels($userId);
        $labels = $labelsResponse->getLabels() ?? [];

        foreach ($labels as $lbl) {
            // Only user-created labels
            if ($lbl->getType() === 'user') {
                $labelMap[strtolower($lbl->getName())] = $lbl->getId();
            }
        }
    } catch (\Exception $e) {
        Log::error("Error fetching labels: " . $e->getMessage());
    }

    return $labelMap;
}

// Return array of user label names in original case
public function getUserLabelNames($userId = 'me')
{
    $labelNames = [];
    try {
        $labelsResponse = $this->service->users_labels->listUsersLabels($userId);
        $labels = $labelsResponse->getLabels() ?? [];

        foreach ($labels as $lbl) {
            if ($lbl->getType() === 'user') {
                $labelNames[] = $lbl->getName();
            }
        }
    } catch (\Exception $e) {
        Log::error("Error fetching label names: " . $e->getMessage());
    }

    return $labelNames;
}

// ---------------------------
// 2) Search messages
// ---------------------------
public function searchMessages($query = "to:info@mycolean.com", $userId = 'me')
{
    $messages = [];
    try {
        // Initial search
        $response = $this->service->users_messages->listUsersMessages($userId, ['q' => $query]);
        $fetched = $response->getMessages() ?? [];
        $messages = array_merge($messages, $fetched);

        // Paginate if there's a nextPageToken
        while ($response->getNextPageToken()) {
            $response = $this->service->users_messages->listUsersMessages($userId, [
                'q' => $query,
                'pageToken' => $response->getNextPageToken()
            ]);
            $fetched = $response->getMessages() ?? [];
            $messages = array_merge($messages, $fetched);
        }
    } catch (\Exception $e) {
        Log::error("Error in searchMessages: " . $e->getMessage());
    }
    return $messages;
}

// ---------------------------
// 3) Get message content
// ---------------------------
public function getMimeMessageContent($msgId, $userId = 'me')
{
    try {
        $message = $this->service->users_messages->get($userId, $msgId, ['format' => 'full']);
        $payload = $message->getPayload();

        // Extract subject/from from headers
        $headers = collect($payload->getHeaders());
        $subject = $headers->where('name', 'Subject')->pluck('value')->first() ?? '';
        $from = $headers->where('name', 'From')->pluck('value')->first() ?? '';

        // Body can be in 'parts' or 'body'
        $body = '';
        $parts = $payload->getParts() ?? [];
        if (isset($payload->getBody()['data'])) {
            // Single part
            $body = base64_decode(strtr($payload->getBody()['data'], '-_', '+/'));
        } else {
            // Multiple parts
            foreach ($parts as $part) {
                if (in_array($part->getMimeType(), ['text/plain','text/html'])) {
                    $data = $part->getBody()->getData();
                    if ($data) {
                        $body = base64_decode(strtr($data, '-_', '+/'));
                        break;
                    }
                }
            }
        }
        return [$subject, $from, $body];
    } catch (\Exception $e) {
        Log::error("Could not retrieve message $msgId: " . $e->getMessage());
        return [null, null, null];
    }
}

// ---------------------------
// 4) Check if message already has a user label
// ---------------------------
public function messageHasUserLabel($msgId, $userLabelIds, $userId = 'me')
{
    try {
        $msg = $this->service->users_messages->get($userId, $msgId, ['format' => 'metadata']);
        $existingLabelIds = $msg->getLabelIds() ?? [];
        // If there's any intersection with user labels, it's already user-labeled
        return (bool) array_intersect($existingLabelIds, $userLabelIds);
    } catch (\Exception $e) {
        Log::error("Error checking labels for message $msgId: " . $e->getMessage());
        return false;
    }
}

// ---------------------------
// 5) Add label to email
// (You already have applyLabel(), but we adapt the fallback logic)
// ---------------------------
public function addLabelToEmail($msgId, $labelName, $labelMap, $fallbackLabel, $userId = 'me')
{
    try {
        // case-insensitive label lookup
        $labelId = $labelMap[strtolower($labelName)] ?? null;

        if (!$labelId) {
            // fallback
            Log::info("Label '$labelName' not found. Fallback to '$fallbackLabel'.");
            $labelId = $labelMap[strtolower($fallbackLabel)] ?? null;
            if (!$labelId) {
                Log::warning("Fallback label '$fallbackLabel' also missing. No labeling applied.");
                return;
            }
            $labelName = $fallbackLabel;
        }

        // Apply label
        $modifyReq = new ModifyMessageRequest();
        $modifyReq->setAddLabelIds([$labelId]);
        $modifyReq->setRemoveLabelIds([]);
        $this->service->users_messages->modify($userId, $msgId, $modifyReq);

        Log::info("Labeled message $msgId with '$labelName' (ID: $labelId)");
    } catch (\Exception $e) {
        Log::error("Error adding label to email $msgId: " . $e->getMessage());
    }
}



            public function classifyEmailWithGpt($emailBody, $existingLabelNames)
            {
                
    $openai = \OpenAI::client(config('services.openai.api_key'));
                // Set up your user prompt (same logic as Python)
                $validLabels = collect($existingLabelNames)->map(fn($l) => "- {$l}")->join("\n");
                $userPrompt = <<<TXT
            You are classifying an incoming support email. 
            Only respond with exactly one label from the list below (no new labels).

            Valid labels:
            $validLabels

            Email content:
            \"\"\"$emailBody\"\"\"

            Which single label from the list is the best fit? Return only the label.
            TXT;

                try {
                    $apiKey = env('OPENAI_API_KEY');
                    
                    $response = $openai->chat()->create([
                        'model' => 'gpt-4o', // Use GPT-4o model
                        'messages' => [
                            ["role" => "system", "content" => "You classify emails using existing labels only."],
                            ["role" => "user", "content" => $userPrompt],
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 200,
                    ]);


                    $classification = trim($response['choices'][0]['message']['content'] ?? '');

                    // If GPT returns a label not in the list, we fallback in the next step
                    return $classification;
                } catch (\Exception $e) {
                    Log::error("OpenAI classification error: " . $e->getMessage());
                    return null;
                }
            }


}
