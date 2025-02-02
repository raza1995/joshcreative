<?php

namespace App\Console\Commands;

use Google\Service\Gmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\GmailService;

class ProcessGmailEmails extends Command
{
    protected $signature = 'gmail:process-emails {historyId}';
    protected $description = 'Fetch and classify new Gmail emails.';

    protected $gmailService;

    public function __construct(GmailService $gmailService)
    {
        parent::__construct();
        $this->gmailService = $gmailService;
    }

    public function handle()
    {
        $historyId = $this->argument('historyId');
        Log::info("Fetching new emails for historyId: $historyId");

        try {
            $messages = $this->gmailService->fetchEmails();
            if (!$messages) {
                Log::info("No new messages.");
                return;
            }

            foreach ($messages as $message) {
                $this->processEmail($message->getId());
            }
        } catch (\Exception $e) {
            Log::error("Error fetching emails: " . $e->getMessage());
        }
    }

    private function processEmail($messageId)
    {
        $email = $this->gmailService->getEmail($messageId);
        $classification = $this->classifyEmail($email['body']);
        
        Log::info("Email classified as: $classification");

        $this->gmailService->applyLabel($messageId, $classification);
    }

    private function classifyEmail($emailBody)
    {
        $apiKey = env('OPENAI_API_KEY');
        $response = Http::withHeaders(['Authorization' => "Bearer $apiKey"])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'Classify the email into one of the following labels: Support, Sales, Spam, Inquiry'],
                    ['role' => 'user', 'content' => $emailBody]
                ],
                'max_tokens' => 10,
                'temperature' => 0.0
            ]);

        return $response->json()['choices'][0]['message']['content'] ?? 'Inquiry';
    }
}

