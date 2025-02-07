<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GmailService;
use Illuminate\Support\Facades\Log;

class StartGmailWatch extends Command
{
    protected $signature = 'gmail:watch';
    protected $description = 'Start Gmail watch for new emails.';
    
    protected $gmailService;

    public function __construct(GmailService $gmailService)
    {
        parent::__construct();
        $this->gmailService = $gmailService;
    }

    public function handle()
    {
        Log::info("Starting Gmail Watch...");
        $this->gmailService->startGmailWatch();
        Log::info("Gmail Watch command executed.");
    }
}
