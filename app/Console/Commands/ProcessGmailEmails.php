<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\GmailService;
use App\Helpers\ExcelHelper;

class ProcessGmailEmails extends Command
{
    protected $signature = 'gmail:process-mycolean';
    protected $description = 'Search emails sent to info@mycolean.com, classify them, label them, and record in Excel.';

    protected $gmailService;

    // Fallback label if GPT suggests a non-existent label
    const FALLBACK_LABEL_NAME = "🟡-medium-2.-📧-general-inquiry"; 

    // Path to your Excel file

    public function __construct(GmailService $gmailService)
    {
        parent::__construct();
        $this->gmailService = $gmailService;
    }

    public function handle()
    {
        Log::info("Starting MyColean email processing...");


        // 2) Get user labels (to build label map)
        $labelMap = $this->gmailService->getUserLabelsMap();
        $originalLabels = $this->gmailService->getUserLabelNames();

        // 3) Search for messages with "to:info@mycolean.com"
        $messages = $this->gmailService->searchMessages('to:info@mycolean.com');
        if (empty($messages)) {
            $this->info("No new messages found.");
            return;
        }

        // Collect user label IDs for "already-labeled" check
        $userLabelIds = array_values($labelMap); // array of label IDs

        // 4) Process each message
        foreach ($messages as $msg) {
            $msgId = $msg->getId();

            // Get subject, from, body
            [$subject, $fromEmail, $body] = $this->gmailService->getMimeMessageContent($msgId);

            // If no body, skip
            if (!$body) {
                continue;
            }

            // Check if already has a user label
            if ($this->gmailService->messageHasUserLabel($msgId, $userLabelIds)) {
                $this->info("Message $msgId already has a user label. Skipping.");
                continue;
            }

            // 5) Classify with GPT
            $classification = $this->gmailService->classifyEmailWithGpt($body, $originalLabels);

            // If GPT returned something not in the list, fallback
            if (!in_array($classification, $originalLabels)) {
                $this->info("GPT returned '$classification' not in user labels. Fallback to '".self::FALLBACK_LABEL_NAME."'.");
                $classification = self::FALLBACK_LABEL_NAME;
            }

            // 6) Add label to the email
            $this->gmailService->addLabelToEmail($msgId, $classification, $labelMap, self::FALLBACK_LABEL_NAME);

            // 7) Record in Excel
            // Clean up from_email (extract just the email if it has <...>)
            if (preg_match('/<(.*?)>/', $fromEmail, $matches)) {
                $fromEmailCleaned = $matches[1];
            } else {
                $fromEmailCleaned = $fromEmail;
            }


            $this->info("Done labeling message $msgId as '$classification'.");
        }

        $this->info("Done processing all MyColean messages.");
    }
}
