<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GmailService;
use App\Models\EmailDraft;
use Illuminate\Support\Facades\Log;

class ProcessEmails extends Command
{
    protected $signature = 'emails:process';
    protected $description = 'Automate email fetching, processing, and sending using AI';

    protected $gmailService;

    public function __construct(GmailService $gmailService)
    {
        parent::__construct();
        $this->gmailService = $gmailService;
    }

    public function handle()
    {
        Log::info('🚀 Starting automated email processing...');

        
            // ✅ Step 1: Fetch unread emails from Gmail
            $this->gmailService->fetchUnreadEmailsAndProcess();
            
            // ✅ Step 2: Auto-send approved drafts
            $this->autoSendApprovedDrafts();

            Log::info('✅ Email processing completed successfully.');
        
    }

    /**
     * ✅ Auto-send drafts that meet AI confidence threshold
     */
    private function autoSendApprovedDrafts()
    {
        $drafts = EmailDraft::where('status', 'approved')
            ->where('auto_sent', false)
            ->where('ai_confidence', '>=', 90)
            ->get();

        foreach ($drafts as $draft) {
            $to = $draft->shopifyOrder->email_address ?? null;

            if ($to) {
                $result = $this->gmailService->sendEmail($to, $draft->subject, $draft->body);

                if ($result) {
                    $draft->update(['auto_sent' => true, 'status' => 'sent']);
                    Log::info("✅ Auto-sent email to: $to");

                    // Notify via Slack
                    app(\App\Services\SlackService::class)->sendMessage("📤 *Auto-Sent Email*\nTo: {$to}\nSubject: {$draft->subject}");
                }
            }
        }
    }
}
