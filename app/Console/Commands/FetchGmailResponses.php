<?php

namespace App\Console\Commands;

use App\Services\GmailService;
use Illuminate\Console\Command;

class FetchGmailResponses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gmail:fetch-responses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch customer emails and create AI-powered draft responses';

    /**
     * Execute the console command.
     */
    public function handle(GmailService $gmailService)
    {
        $this->info('Fetching Gmail responses...');
        $gmailService->fetchEmails();
        $this->info('Draft emails created successfully!');
    }

}
