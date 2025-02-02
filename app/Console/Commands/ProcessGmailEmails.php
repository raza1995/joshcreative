<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\GmailService;

class ProcessGmailEmails extends Command
{
    protected $signature = 'gmail:process-mycolean {historyId?}';
    protected $description = 'Process only newly pushed Gmail messages.';

    protected $gmailService;

    const FALLBACK_LABEL_NAME = "🟡-medium-2.-📧-general-inquiry"; // Fallback label

    public function __construct(GmailService $gmailService)
    {
        parent::__construct();
        $this->gmailService = $gmailService;
    }

    public function handle()
    {
        $historyId = $this->argument('historyId');

        if (!$historyId) {
            Log::error("No historyId provided. Exiting...");
            $this->error("No historyId provided. This command should be triggered by Gmail webhook.");
            return;
        }

        Log::info("Processing emails from historyId: $historyId");

        // Get the list of emails **ONLY from this historyId**
        $messages = $this->gmailService->getMessagesFromHistory($historyId);
        if (empty($messages)) {
            $this->info("No new messages found in history.");
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
                $this->info("Message $msgId already has a user label. Skipping.");
                continue;
            }

            // Classify with GPT
            $classification = $this->gmailService->classifyEmailWithGpt($body, $originalLabels);

            // If classification doesn't match a user label, fallback
            if (!in_array($classification, $originalLabels)) {
                $this->info("GPT returned '$classification' not in user labels. Falling back to '".self::FALLBACK_LABEL_NAME."'.");
                $classification = self::FALLBACK_LABEL_NAME;
            }

            // Apply the label
            $this->gmailService->addLabelToEmail($msgId, $classification, $labelMap, self::FALLBACK_LABEL_NAME);

            $this->info("Done labeling message $msgId as '$classification'.");
        }

        $this->info("Done processing pushed emails.");
    }
}
