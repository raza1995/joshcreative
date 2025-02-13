<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\GmailService;

class ProcessGmailEmails extends Command
{
    protected $signature = 'gmail:process-emails-updated';
    protected $description = 'Fetch and label emails from the last 3 hours.';

    protected $gmailService;

    const FALLBACK_LABEL_NAME = "🟡-medium-2.-📧-general-inquiry"; // Fallback label

    public function __construct(GmailService $gmailService)
    {
        parent::__construct();
        $this->gmailService = $gmailService;
    }

    public function handle()
    {
        Log::info("🚀 Starting email processing for the last 3 hours...");

        // Calculate the time 3 hours ago
        $threeHoursAgo = now()->subHours(3)->format('Y-m-d H:i:s');

        // Gmail format requires RFC 3339 format
        $rfc3339Time = date(DATE_RFC3339, strtotime($threeHoursAgo));

        // Search for emails received in the last 3 hours
        $threeHoursAgo = now()->subHours(3)->timestamp; // Get UNIX timestamp
$query = "after:$threeHoursAgo "; // Only fetch unread emails

        Log::info("🔍 Searching for emails with query: $query");

        $messages = $this->gmailService->searchMessages($query);
        if (empty($messages)) {
            $this->info("✅ No new messages found.");
            return;
        }

        // Get user labels for classification
        $labelMap = $this->gmailService->getUserLabelsMap();
        $originalLabels = $this->gmailService->getUserLabelNames();
        $userLabelIds = array_values($labelMap); // For checking existing labels

        // Process each new email
        foreach ($messages as $msg) {
            $msgId = $msg->getId();
            [$subject, $fromEmail, $body] = $this->gmailService->getMimeMessageContent($msgId);

            if (!$body) {
                continue;
            }

            // Check if the email is already labeled
            if ($this->gmailService->messageHasUserLabel($msgId, $userLabelIds)) {
                $this->info("✅ Message $msgId already has a user label. Skipping.");
                continue;
            }

            // Classify with GPT
            $classification = $this->gmailService->classifyEmailWithGpt($body, $originalLabels);

            // If classification doesn't match a user label, fallback
            if (!in_array($classification, $originalLabels)) {
                $this->info("⚠️ GPT returned '$classification' not in user labels. Falling back to '".self::FALLBACK_LABEL_NAME."'.");
                $classification = self::FALLBACK_LABEL_NAME;
            }

            // Apply the label
            $this->gmailService->addLabelToEmail($msgId, $classification, $labelMap, self::FALLBACK_LABEL_NAME);

            $this->info("✅ Done labeling message $msgId as '$classification'.");
        }

        $this->info("🚀 Done processing emails.");
    }
}
