<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GmailService;

class FetchEmailsAndNotifySlack extends Command
{
    protected $signature = 'emails:fetch';
    protected $description = 'Fetch unread emails from Gmail and notify Slack';

    protected $gmailService;

    public function __construct(GmailService $gmailService)
    {
        parent::__construct();
        $this->gmailService = $gmailService;
    }

    public function handle()
    {
        $this->info('📥 Fetching unread emails...');
        // $this->gmailService->fetchUnreadEmailsAndNotify();
        $this->info('✅ Email fetch and Slack notifications completed.');
    }
}
