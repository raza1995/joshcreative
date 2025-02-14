<?php
namespace App\Services;

use App\Models\EmailDraft;
use App\Models\ProcessedEmail;
use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use App\Models\ShopifyOrder;
use Google\Service\Gmail\Draft;
use Google\Service\Gmail\ModifyMessageRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SlackService;
use App\Services\ShopifyService;

class GmailService
{
    protected $client;
    protected $tokenPath;
    protected $slackService;
    protected $service;
    protected $openAIService;
    protected $shopifyService;

    public function __construct(SlackService $slackService, OpenAIService $openAIService, ShopifyService $shopifyService)
    {
        $this->tokenPath = storage_path('app/token.json');
        $this->slackService = $slackService;
        $this->client = new Client();
        $this->client->setAuthConfig(storage_path('app/credentials.json'));
        $this->client->addScope(Gmail::MAIL_GOOGLE_COM);
        $this->client->addScope(Gmail::GMAIL_READONLY);

        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
        $this->client->addScope(Gmail::GMAIL_READONLY);
        // OR for full access:
        $this->client->addScope(Gmail::GMAIL_READONLY);  // Read-only access
        $this->client->addScope(Gmail::GMAIL_MODIFY);    // If you want to mark emails as read, etc.
        $this->openAIService = $openAIService;
        $this->shopifyService = $shopifyService;

        $this->authenticate();
        $this->service = new Gmail($this->client);
    }
    private function authenticate()
    {
        if (file_exists($this->tokenPath)) {
            $accessToken = json_decode(file_get_contents($this->tokenPath), true);
        
            if (json_last_error() !== JSON_ERROR_NONE || !isset($accessToken['access_token'])) {
                Log::error("❌ Invalid token format detected. Re-authenticating...");
                $this->generateNewToken();
                return;
            }
        
            $this->client->setAccessToken($accessToken);
            try {
                $newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            
                if (isset($newAccessToken['error'])) {
                    throw new \Exception($newAccessToken['error_description']);
                }
            
                if (!isset($newAccessToken['refresh_token'])) {
                    $newAccessToken['refresh_token'] = $this->client->getRefreshToken();
                }
            
                $this->client->setAccessToken($newAccessToken);
                file_put_contents($this->tokenPath, json_encode($newAccessToken));
                Log::info("✅ Google API token refreshed successfully.");
            } catch (\Exception $e) {
                Log::error("❌ Token refresh failed: " . $e->getMessage());
                $this->generateNewToken();
            }
            
        } else {
            Log::warning("⚠️ Google API token file not found. Generating a new token...");
            $this->generateNewToken();
        }
        
    }
    
    /**
     * Generate a new Google API token if `token.json` is missing.
     */
    private function generateNewToken()
    {
        $authUrl = $this->client->createAuthUrl();
        echo "🔗 Open this URL in your browser and authenticate:\n$authUrl\n";
        echo "📥 Paste the authentication code here: ";
    
        $authCode = trim(fgets(STDIN));
    
        if (empty($authCode)) {
            throw new \InvalidArgumentException("❌ Invalid code: The authentication code cannot be empty.");
        }
    
        $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
    
        if (isset($accessToken['error'])) {
            throw new \Exception("❌ Google OAuth authentication failed: " . $accessToken['error']);
        }
    
        file_put_contents($this->tokenPath, json_encode($accessToken));
        $this->client->setAccessToken($accessToken);
    
        Log::info("✅ Google API authentication successful. Token stored.");
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
        // Prepare the watch request with the desired labels and Pub/Sub topic.
        $watchRequest = new \Google\Service\Gmail\WatchRequest([
            'labelIds'  => ['INBOX'],
            'topicName' => 'projects/gmail-api-449711/topics/gmail-notification',
        ]);

        // "me" refers to the authenticated user's mailbox.
        $response = $this->service->users->watch('me', $watchRequest);

        Log::info("✅ Gmail Watch started successfully.", ['response' => $response]);
        return $response;
    } catch (\Google\Service\Exception $gException) {
        Log::error("🚨 Google API Error: " . $gException->getMessage());
        $errorDetails = $gException->getErrors();

        // Handle forbidden errors or other errors if needed.
        if (isset($errorDetails[0]['reason']) && $errorDetails[0]['reason'] === 'forbidden') {
            Log::warning("⚠️ Forbidden error detected. Check permissions or token scopes.");
            // You might want to trigger a re-authentication or notify someone.
        }

        throw $gException;
    } catch (\Exception $e) {
        Log::error("🚨 Error starting Gmail Watch: " . $e->getMessage());
        throw $e;
    }
}

public function startGmailWatch()
{
    try {
        $response = $this->startWatch();
        return response()->json($response);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
public function fetchNewEmails()
{
    try {
        $messages = $this->service->users_messages->listUsersMessages('me', [
            'labelIds' => ['INBOX'],
            'maxResults' => 20, // Fetch the latest 10 emails
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

            public function getMessagesFromHistory($historyId, $userId = 'me')
{
    try {
        $response = $this->service->users_history->listUsersHistory($userId, [
            'startHistoryId' => $historyId
        ]);

        $historyRecords = $response->getHistory();
        $messages = [];

        if (!$historyRecords) {
            return [];
        }

        foreach ($historyRecords as $record) {
            if ($record->getMessagesAdded()) {
                foreach ($record->getMessagesAdded() as $addedMessage) {
                    $messages[] = $addedMessage->getMessage();
                }
            }
        }

        return $messages;
    } catch (\Exception $e) {
        Log::error("Error fetching history: " . $e->getMessage());
        return [];
    }
}


public function searchMessages($query, $userId = 'me')
{
    $messages = [];
    try {
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
        Log::error("🚨 Error in searchMessages: " . $e->getMessage());
    }
    return $messages;
}


// public function fetchUnreadEmailsAndNotify()
// {
//     $user = 'me';
//     $messages = $this->service->users_messages->listUsersMessages($user, [
//         'q' => 'is:unread',
//         'maxResults' => 20,
//     ])->getMessages();

//     if (!$messages) {
//         Log::info('✅ No new unread emails found.');
//         return;
//     }

//     foreach ($messages as $message) {
//         $messageId = $message->getId();
//         Log::info("📩 Processing email with Message ID: $messageId");

//         // Check if the email has already been processed
//         $alreadyProcessed = ProcessedEmail::where('message_id', $messageId)->exists();
//         Log::info("🔍 Check if Message ID $messageId already processed: " . ($alreadyProcessed ? 'YES' : 'NO'));

//         if ($alreadyProcessed) {
//             Log::info("⏩ Skipping already processed email (Message ID: $messageId)");
//             continue;
//         }

//         $emailData = $this->parseEmail($messageId);
//         if ($emailData) {
//             $this->notifySlack($emailData);

//             // Save the message ID after sending notification
//             try {
//                 ProcessedEmail::create(['message_id' => $messageId]);
//                 Log::info("✅ Message ID $messageId saved as processed.");
//             } catch (\Exception $e) {
//                 Log::error("🚨 Failed to save Message ID $messageId: " . $e->getMessage());
//             }
//         }
//     }
// }



/**
 * Parse email content from Gmail message.
 */

/**
 * Send Slack notification.
 */
private function notifySlack($emailData)
{
    $summary = app(OpenAIService::class)->generateSummary($emailData['body']);

    $message = "📧 *New Customer Email*\n"
             . "*From:* {$emailData['from']}\n"
             . "*Subject:* {$emailData['subject']}\n"
             . "*Summary:* $summary";

    $this->slackService->sendMessage($message);
}

public function fetchUnreadEmails()
{
    $user = 'me';
    $messages = $this->service->users_messages->listUsersMessages($user, [
        'q' => 'is:unread in:inbox',
        'maxResults' => 250,
    ])->getMessages();

    return $messages ?? [];
}

public function getEmailBySender($emailAddress)
{
    $messages = $this->searchMessages("from:{$emailAddress}");

    if (empty($messages)) {
        return "❌ No emails found from {$emailAddress}.";
    }

    $latestMessage = $messages[0];
    list($subject, $from, $body) = $this->getMimeMessageContent($latestMessage->getId());

    return "📬 *Subject:* $subject\n*From:* $from\n\n$body";
}
private function getHeader($headers, $name)
{
    foreach ($headers as $header) {
        if ($header->getName() === $name) {
            return $header->getValue();
        }
    }
    return null;
}
public function getEmailsSinceHistoryId($historyId)
{
    $history = $this->service->users_history->listUsersHistory('me', [
        'startHistoryId' => $historyId,
        'historyTypes'   => ['messageAdded'],
    ]);

    $emails = [];

    foreach ($history->getHistory() as $h) {
        foreach ($h->getMessages() as $message) {
            $msg = $this->service->users_messages->get('me', $message->getId(), ['format' => 'full']);
            $emails[] = [
                'id'      => $message->getId(),
                'snippet' => $msg->getSnippet(),
                'from'    => $this->getHeader($msg->getPayload()->getHeaders(), 'From'),
                'subject' => $this->getHeader($msg->getPayload()->getHeaders(), 'Subject'),
            ];
        }
    }

    return $emails;
}
private function parseEmail($messageId)
{
    $message = $this->service->users_messages->get('me', $messageId, ['format' => 'full']);
    $headers = $message->getPayload()->getHeaders();

    $from = '';
    $subject = '';
    $replyTo = '';
    $allHeaders = [];

    // Extract key headers and store all headers
    foreach ($headers as $header) {
        $headerName = $header->getName();
        $headerValue = $header->getValue();
        $allHeaders[$headerName] = $headerValue;

        if ($headerName === 'From') {
            $from = $headerValue;
        }
        if ($headerName === 'Subject') {
            $subject = $headerValue;
        }
        if ($headerName === 'Reply-To') {
            $replyTo = $headerValue;
        }
    }

    // Fetch the latest reply in the thread
    $threadId = $message->getThreadId();
    $thread = $this->service->users_threads->get('me', $threadId);
    $messages = $thread->getMessages();
    $latestMessage = end($messages); // Get the latest message

    // Extract body from latest message
    $body = '';
    $parts = $latestMessage->getPayload()->getParts();
    if ($parts) {
        foreach ($parts as $part) {
            if ($part->getMimeType() === 'text/plain') {
                $body = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                break;
            }
        }
    } else {
        $body = base64_decode(strtr($latestMessage->getPayload()->getBody()->getData(), '-_', '+/'));
    }

    return [
        'from'        => $from,
        'reply_to'    => $replyTo,
        'subject'     => $subject,
        'body'        => substr($body, 0, 300) . '...',
        'received_at' => date('Y-m-d H:i:s', $message->getInternalDate() / 1000),
        'all_headers' => $allHeaders, // Store all headers for analysis
    ];
}


public function fetchUnreadEmailsAndNotify()
{
    Log::info('Starting fetchUnreadEmailsAndNotify process.');
    $user = 'me';

    // Keywords to process emails
    $keywords = ['mycolean', 'order', 'orders', 'refund', 'issue', 'shipping'];
    Log::info('Keywords for processing: ' . implode(', ', $keywords));

    // Keywords to exclude emails from processing (with higher priority)
    $excludeKeywords = ['TripleWhale', 'promotion', 'shopify'];
    Log::info('Keywords for exclusion: ' . implode(', ', $excludeKeywords));

    // Get the timestamp of the latest processed email
    $latestProcessedEmail = ProcessedEmail::latest('received_at')->first();
    $afterTimestamp = $latestProcessedEmail ? strtotime($latestProcessedEmail->received_at) : 0;
    Log::info('Latest processed email timestamp: ' . ($afterTimestamp ? date('Y-m-d H:i:s', $afterTimestamp) : 'None'));

    // Build the Gmail search query
    $query = 'is:unread in:inbox'; // Focus on primary inbox
    Log::info('Initial Gmail search query: ' . $query);

    if ($afterTimestamp) {
        $query .= ' newer_than:1d';  // Fetch emails newer than 1 day if needed
        Log::info('Updated Gmail search query with timestamp: ' . $query);
    }

    // Fetch unread emails after the last processed timestamp
    Log::info('Fetching unread emails with query: ' . $query);
    $messages = $this->service->users_messages->listUsersMessages($user, [
        'q' => $query,
        'maxResults' => 10,  // Increased limit to get more emails
    ])->getMessages();

    if (!$messages) {
        Log::info('✅ No new unread emails found.');
        return;
    }

    Log::info('Found ' . count($messages) . ' unread emails.');

    foreach ($messages as $message) {
        $messageId = $message->getId();
        Log::info('Processing email with message ID: ' . $messageId);

        // Check if the email has already been processed
        if (ProcessedEmail::where('message_id', $messageId)->exists()) {
            Log::info('Email with message ID ' . $messageId . ' has already been processed. Skipping.');
            continue;
        }

        // Extract full email data
        $emailData = $this->parseEmail($messageId);

        if ($emailData) {
            Log::info('Processing email from: ' . $emailData['from'] . ' | Subject: ' . $emailData['subject']);

            // Check if the email contains any relevant keywords
            $emailContent = strtolower($emailData['subject'] . ' ' . $emailData['body']);
            $matchesKeyword = false;
            $excludedKeyword = false;

            // ✅ Check for excluded keywords first (Priority)
            foreach ($excludeKeywords as $exclude) {
                if (strpos($emailContent, strtolower($exclude)) !== false) {
                    $excludedKeyword = true;
                    Log::info('🚫 Email contains excluded keyword: ' . $exclude . '. Skipping.');
                    break;
                }
            }

            // If no excluded keywords, check for important keywords
            if (!$excludedKeyword) {
                foreach ($keywords as $keyword) {
                    if (strpos($emailContent, strtolower($keyword)) !== false) {
                        $matchesKeyword = true;
                        Log::info('Email contains keyword: ' . $keyword);
                        break;
                    }
                }
            }

            // Process emails that match important keywords and are not excluded
            if ($matchesKeyword && !$excludedKeyword) {
                Log::info('✅ Email matches keywords. Passing to next process.');
                $this->notifySlack($emailData);

                // Save the processed email with the received timestamp
                ProcessedEmail::create([
                    'message_id'   => $messageId,
                    'sender_email' => $emailData['from'],
                    'subject'      => $emailData['subject'],
                    'snippet'      => $emailData['body'],
                    'received_at'  => $emailData['received_at'],
                ]);
                Log::info('📨 Email with message ID ' . $messageId . ' has been processed and saved.');
            } elseif (!$matchesKeyword && !$excludedKeyword) {
                Log::info('❌ Email did not match any processing keywords. Ignoring.');
            }
        }
    }
    Log::info('Completed fetchUnreadEmailsAndNotify process.');
}

public function getLatestEmailBySender($emailAddress)
{
    $messages = $this->service->users_messages->listUsersMessages('me', [
        'q' => 'from:' . $emailAddress,
        'maxResults' => 1, // Get the latest email
    ])->getMessages();

    if (empty($messages)) {
        return "❌ No emails found from {$emailAddress}.";
    }

    $latestMessage = $messages[0];
    $emailData = $this->parseEmail($latestMessage->getId());

    if ($emailData) {
        // Save in conversation log
        $this->saveToConversationLog($emailAddress, $emailData);

        // Send to Slack
        app(SlackService::class)->sendMessage("📧 *Email from:* {$emailAddress}\n*Subject:* {$emailData['subject']}\n\n{$emailData['body']}");

        return $emailData['body'];
    }

    return "⚠️ Failed to retrieve email content.";
}

private function saveToConversationLog($emailAddress, $emailData)
{
    \App\Models\Conversation::create([
        'user_identifier' => $emailAddress,
        'conversation_data' => [
            'from' => $emailData['from'],
            'subject' => $emailData['subject'],
            'body' => $emailData['body'],
            'received_at' => $emailData['received_at'],
        ],
    ]);
}
public function getLatestEmail()
{
    $messages = $this->service->users_messages->listUsersMessages('me', [
        'q' => 'is:inbox', // Fetch emails from inbox
        'maxResults' => 5,  // Only the latest email
    ])->getMessages();

    if (empty($messages)) {
        return "❌ No emails found in the inbox.";
    }

    $latestMessage = $messages[0];
    $emailData = $this->parseEmail($latestMessage->getId());

    if ($emailData) {
        // Save to conversation log
        $this->saveToConversationLog($emailData['from'], $emailData);

        // Send to Slack
        app(SlackService::class)->sendMessage(
            "📧 *New Email*\n*From:* {$emailData['from']}\n*Subject:* {$emailData['subject']}\n\n{$emailData['body']}"
        );

        return $emailData['body'];
    }

    return "⚠️ Failed to retrieve the latest email.";
}

public function processIncomingEmails()
{
    $messages = $this->service->users_messages->listUsersMessages('me', [
        'labelIds' => ['INBOX'],
        'maxResults' => 20,
    ])->getMessages();

    foreach ($messages as $message) {
        $this->handleEmail($message->getId());
    }
}

private function handleEmail($messageId)
{
    $message = $this->service->users_messages->get('me', $messageId, ['format' => 'full']);
    $headers = $message->getPayload()->getHeaders();

    $subject = $this->getHeader($headers, 'Subject');
    $fromEmail = $this->getHeader($headers, 'From');
    $body = $this->extractEmailBody($message);

    // Check for specific conditions (e.g., tracking inquiry)
    if ($this->isTrackingInquiry($subject, $body)) {
        $shopifyOrder = ShopifyOrder::where('email_address', $fromEmail)->first();
        $aiDraft = $this->openAIService->generateEmailDraft($body, $shopifyOrder);

        EmailDraft::create([
            'email_id' => $messageId,
            'subject' => $subject,
            'body' => $aiDraft,
            'status' => 'pending',
            'shopify_order_id' => $shopifyOrder->id ?? null,
        ]);

        Log::info("✅ Draft created for email: $fromEmail");
    }
}

private function extractEmailBody(Message $message)
{
    $body = '';
    $parts = $message->getPayload()->getParts();

    foreach ($parts as $part) {
        if ($part->getMimeType() === 'text/plain') {
            $body = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
            break;
        }
    }
    return $body;
}

private function isTrackingInquiry($subject, $body)
{
    $keywords = ['where is my order', 'tracking number', 'order status', 'shipment', 'delivery'];
    foreach ($keywords as $keyword) {
        if (stripos($subject, $keyword) !== false || stripos($body, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

public function fetchUnreadEmailsAndProcess()
{
    Log::info('🔍 Starting fetchUnreadEmailsAndProcess method.');

    $user = 'me';
    $keywords = ['order', 'refund', 'issue', 'shipping', 'mycolean', 'where my order is', 'taking so long', 'ETA for my order'];
    $excludeKeywords = ['promotion', 'TripleWhale', '
Thank you for your purchase'];

    $latestProcessedEmail = ProcessedEmail::latest('received_at')->first();
    $afterTimestamp = $latestProcessedEmail ? strtotime($latestProcessedEmail->received_at) : null;

    Log::info('📅 Latest processed email timestamp: ' . ($afterTimestamp ? date('Y-m-d H:i:s', $afterTimestamp) : 'None'));

    $query = 'is:unread in:inbox';
    if ($afterTimestamp) {
        $query .= ' newer_than:1d';
        Log::info('🔍 Updated Gmail search query with timestamp: ' . $query);
    }

    Log::info('📧 Fetching unread emails with query: ' . $query);
    $messages = $this->service->users_messages->listUsersMessages($user, [
        'q' => $query,
        'maxResults' => 10,
    ])->getMessages();

    if (!$messages) {
        Log::info('✅ No new unread emails found.');
        return;
    }

    Log::info('📨 Found ' . count($messages) . ' unread emails.');
    foreach ($messages as $message) {
        $messageId = $message->getId();
        Log::info('🔍 Processing email with message ID: ' . $messageId);

        if (ProcessedEmail::where('message_id', $messageId)->exists()) {
            Log::info('⏩ Email with message ID ' . $messageId . ' has already been processed. Skipping.');
            continue;
        }

        Log::info('🔍 Parsing email with message ID: ' . $messageId);
        $emailData = $this->parseEmail($messageId);
        Log::info('🔍 Parsed email data: ' . json_encode($emailData));

        if ($emailData) {
            $emailContent = strtolower($emailData['subject'] . ' ' . $emailData['body']);
            Log::info('🔍 Checking keywords for email content.');
            $matchesKeyword = $this->checkKeywords($emailContent, $keywords, $excludeKeywords);
            Log::info('🔍 Keyword check result: ' . ($matchesKeyword ? 'Matched' : 'Not Matched'));

            if ($matchesKeyword) {
                Log::info('🔑 Email matches keywords. Generating and saving draft.');
                $draft = $this->generateAndSaveDraft($emailData, $messageId);

                // ✅ Notify Slack
                $this->notifySlackForReview($emailData, $draft);

                // ✅ Save Processed Email
                ProcessedEmail::create([
                    'message_id' => $messageId,
                    'sender_email' => $emailData['from'],
                    'subject' => $emailData['subject'],
                    'snippet' => $emailData['body'],
                    'received_at' => $emailData['received_at'],
                ]);
                Log::info('✅ Email with message ID ' . $messageId . ' processed and saved.');
            } else {
                Log::info('❌ Email does not match any keywords. Skipping.');
            }
        } else {
            Log::error('🚨 Failed to parse email with message ID: ' . $messageId);
        }
    }

    Log::info('🏁 Completed fetchUnreadEmailsAndProcess method.');
}

private function notifySlackForReview($emailData, $draft)
{
    try {
        $message = "📬 *New Email Draft Created!*\n"
            . "*From:* {$emailData['from']}\n"
            . "*Subject:* {$emailData['subject']}\n"
            . "📝 *Draft Status:* Pending Review\n"
            . "🔗 *[Edit Draft](https://joshcreative.co/email-draft/{$draft->id}/edit)*";

        app(SlackService::class)->sendMessage($message);
        Log::info("✅ Slack notified for draft review of email from: {$emailData['from']}");
    } catch (\Exception $e) {
        Log::error("🚨 Failed to notify Slack: " . $e->getMessage());
    }
}






private function checkKeywords($content, $keywords, $excludeKeywords)
{
    foreach ($excludeKeywords as $exclude) {
        if (strpos($content, strtolower($exclude)) !== false) {
            return false;
        }
    }

    foreach ($keywords as $keyword) {
        if (strpos($content, strtolower($keyword)) !== false) {
            return true;
        }
    }
    return false;
}

private function generateAndSaveDraft($emailData, $messageId)
{

    try {
    $emailData = json_decode(json_encode($emailData), true);
        Log::info("Email Data: WTF" . json_encode($emailData));
     // Ensure $emailData is an array

// ✅ Check if email is from Shopify, use 'Reply-To' if applicable
if (
    (isset($emailData['from']) && 
    (preg_match('/mailer@shopify\.com/i', $emailData['from'], $matchesShopify) || 
    stripos($emailData['from'], 'mailer@shopify.com') !== false)) && 
    !empty($emailData['reply_to'])
) {
    Log::info("Detected Shopify email from header: {$emailData['from']}");
    Log::info("Found Shopify reply-to address: {$emailData['reply_to']}");

    $email = $emailData['reply_to']; // Use reply-to from Shopify emails
    Log::info("Using 'Reply-To' address for Shopify email: {$email}");
} else {
    Log::info("Email is not from Shopify or 'reply_to' is empty. Proceeding to extract from 'From' header.");
    
    // Improved regex to extract email from 'From' header
    if (preg_match('/<([^>]+)>/', $emailData['from'], $matchesFrom)) {
        Log::info("Regex match found in 'From' header: " . json_encode($matchesFrom));
        $email = trim($matchesFrom[1]);
    } else {
        Log::info("No angle-bracketed email found. Attempting to validate plain email.");
        // Fallback if no angle brackets are found
        $email = filter_var($emailData['from'], FILTER_VALIDATE_EMAIL) ? $emailData['from'] : null;
    }

    if ($email) {
        Log::info("Extracted email address from 'From': {$email}");
    } else {
        Log::warning("Failed to extract email from 'From' header.");
    }
}

// Final Log for Debugging
if ($email) {
    Log::info("Final extracted email: {$email}");
} else {
    Log::error("No valid email could be extracted.");
}

        // $shopifyOrder = ShopifyOrder::where('email_address', $email)->first();
        
        $shopifyOrders = $this->shopifyService->getOrdersByEmail($email);
        
        $shopifyOrder = $this->extractShopifyOrderDetails($shopifyOrders);
            
        $aiDraft = $this->openAIService->generateEmailDraft($emailData['body'], $shopifyOrder);

        // ✅ AI Multi-Layer Analysis
        $aiAnalysis = $this->openAIService->analyzeDraft($emailData['body'], $shopifyOrder);

        // 🔍 Check if AI analysis returned valid data
        if (!$aiAnalysis || !is_array($aiAnalysis)) {
            Log::error('🚨 AI Analysis failed or returned null.', ['email' => $emailData, 'response' => $aiAnalysis]);

            // Handle the case when analysis fails (default to pending)
            $aiAnalysis = [
                'tone_check' => 'Fail',
                'content_check' => 'Fail',
                'risk_assessment' => 'High',
                'policy_compliance' => 'Fail',
                'confidence_score' => 0,
                'ai_decision_reason' => 'AI analysis failed, requires human review.'
            ];
        }

        // ✅ Auto-send Logic
        $autoSend = (
            $aiAnalysis['tone_check'] === 'Pass' &&
            $aiAnalysis['content_check'] === 'Pass' &&
            $aiAnalysis['risk_assessment'] === 'Low' &&
            $aiAnalysis['policy_compliance'] === 'Pass' &&
            $aiAnalysis['confidence_score'] >= 90
        );
   
        $draft = EmailDraft::create([
            'email_id'           => $messageId,
            'subject'            => $emailData['subject'],
            'original_email'     => $emailData['body'],  // ✅ Store original email body
            'body'               => $aiDraft,
            'status'             => $autoSend ? 'approved' : 'pending',
            'shopify_order_id'   => $shopifyOrder['order_id'] ?? null,
            'auto_sent'          => $autoSend,
            'ai_confidence'      => $aiAnalysis['confidence_score'] ?? 0,
            'ai_decision_reason' => $autoSend 
                                    ? 'Auto-sent based on high AI confidence' 
                                    : ($aiAnalysis['ai_decision_reason'] ?? 'Requires human review'),
            'tone_check'         => $aiAnalysis['tone_check'] ?? 'Fail',
            'content_check'      => $aiAnalysis['content_check'] ?? 'Fail',
            'risk_assessment'    => $aiAnalysis['risk_assessment'] ?? 'High',
            'policy_compliance'  => $aiAnalysis['policy_compliance'] ?? 'Fail',
        ]);

        if ($autoSend) {
            $this->sendEmail($email, $emailData['subject'], $aiDraft);
            Log::info("✅ Auto-sent email to {$email} with subject: {$emailData['subject']}");
        } else {
            Log::info("📥 Draft saved for manual review for email: {$email}");
        }

        return $draft;

    } catch (\Exception $e) {
        Log::error("🚨 Error generating draft: " . $e->getMessage(), [
            'email'       => $emailData['from'],
            'message_id'  => $messageId,
            'stacktrace'  => $e->getTraceAsString(),
        ]);
        return null;
    }
}




    /**
     * ✅ Send a single email via Gmail API
     */
    public function sendEmail($to, $subject, $body)
    {
        try {
            $rawMessage = $this->createRawEmail($to, $subject, $body);

            $message = new Message();
            $message->setRaw($rawMessage);

            $sentMessage = $this->service->users_messages->send('me', $message);

            Log::info("✅ Email sent successfully to: $to");
            Log::info("✅ Email sent Data:", ['sentMessage' => $sentMessage]);

            return $sentMessage;
        } catch (\Exception $e) {
            Log::error("🚨 Failed to send email to $to: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Send bulk emails from email drafts
     */
    public function sendBulkEmails($draftIds)
{
    $drafts = EmailDraft::with('shopifyOrder')->whereIn('id', $draftIds)->where('status', 'pending')->get();

    if ($drafts->isEmpty()) {
        return ['message' => '❌ No pending drafts found.'];
    }

    $sentCount = 0;
    foreach ($drafts as $draft) {
        $to = $draft->shopifyOrder->email_address ?? null;

        if (!$to) {
            continue;
        }

        $result = $this->sendEmail($to, $draft->subject, $draft->body);
        Log::info("Email send result:", ['result' => $result]);

        if ($result) {
            $draft->update(['status' => 'sent']);
            $sentCount++;
        }
    }

    return ['message' => "✅ Successfully sent $sentCount emails."];
}


    /**
     * ✅ Create raw email message
     */
    private function createRawEmail($to, $subject, $body)
    {
        $rawMessage = "To: $to\r\n";
        $rawMessage .= "Subject: $subject\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $rawMessage .= $body;

        return base64_encode($rawMessage);
    }

 /**
 * ✅ Extract Important Shopify Order Details
 */
private function extractShopifyOrderDetails($shopifyOrders)
{
    if (empty($shopifyOrders)) {
        return [];
    }

    // Use the latest order
    $order = $shopifyOrders[0];

    // Collect Important Details
    $importantDetails = [
        'order_id'        => $order['id'] ?? null,
        'order_number'    => $order['order_number'] ?? null,
        'order_date'      => $order['created_at'] ?? null,
        'financial_status'=> $order['financial_status'] ?? null,
        'fulfillment_status' => $order['fulfillment_status'] ?? null,
        'total_price'     => $order['total_price'] ?? null,
        'subtotal_price'  => $order['subtotal_price'] ?? null,
        'total_discounts' => $order['total_discounts'] ?? null,
        'currency'        => $order['currency'] ?? null,
        'coupon_code'     => $order['discount_codes'][0]['code'] ?? 'N/A',
        'discount_amount' => $order['discount_codes'][0]['amount'] ?? '0.00',
        'number_of_items' => count($order['line_items'] ?? []),
        'customer_name'   => $order['customer']['first_name'] . ' ' . $order['customer']['last_name'] ?? 'N/A',
        'customer_email'  => $order['email'] ?? 'N/A',
        'shipping_address'=> $order['shipping_address'] ?? [],
        'shipping_method' => $order['shipping_lines'][0]['title'] ?? 'N/A',
        'tracking_number' => $order['fulfillments'][0]['tracking_number'] ?? 'N/A',
        'tracking_url'    => $order['fulfillments'][0]['tracking_url'] ?? 'N/A',
    ];

    // Log Important Order Details
    Log::info("Extracted Important Shopify Order Details: " . json_encode($importantDetails));

    return $importantDetails;
}

    
}
