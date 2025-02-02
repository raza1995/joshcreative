<?php
namespace App\Console\Commands;

use App\Services\GmailAuthService;
use Illuminate\Console\Command;
use Google\Client;
use Illuminate\Support\Facades\Storage;

class GmailAuthenticate extends Command
{
   
    protected $signature = 'gmail:authenticate';
    protected $description = 'Authenticate Gmail API and store access token';

    public function handle()
    {
        try {
            $gmailAuth = new GmailAuthService();
            $this->info("✅ Authentication successful! Access token stored in `storage/app/token.json`.");
        } catch (\Exception $e) {
            $this->error("Error during authentication: " . $e->getMessage());
        }
    }
}
