<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GmailShopifyInvoiceService;
use Illuminate\Support\Facades\Log;

class CheckGmailForInvoices extends Command
{
    // Command signature
    protected $signature = 'gmail:check-invoices';

    // Command description
    protected $description = 'Check Gmail for labeled emails and process Shopify invoices.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        Log::info("⏰ Starting Gmail Invoice Check...");

        try {
            // Call the service to process labeled emails
            $invoiceService = new GmailShopifyInvoiceService();
            $invoiceService->processLabeledEmails();

            Log::info("✅ Gmail Invoice Check completed successfully.");
        } catch (\Exception $e) {
            Log::error("🚨 Error during Gmail Invoice Check: " . $e->getMessage());
        }
    }
}
