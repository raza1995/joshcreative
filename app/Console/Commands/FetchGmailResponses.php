<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GmailService;

class FetchGmailResponses extends Command
{
    protected $signature = 'emails:fetch-unread';
    protected $description = 'Fetch unread emails and process them.';

    protected $gmailService;

    public function __construct(GmailService $gmailService)
    {
        parent::__construct();
        $this->gmailService = $gmailService;
    }

    public function handle()
    {
        $this->info('🚀 Fetching unread emails...');
        $this->gmailService->fetchUnreadEmailsAndProcess();
        $this->info('✅ Email processing completed.');
    }
}
