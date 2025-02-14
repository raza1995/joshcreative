<?php

namespace App\Console\Commands;

use App\Services\ShopifyService;
use Illuminate\Console\Command;

class RegisterShopifyWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:webhook-register';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register Shopify order creation webhook';
    /**
     * Execute the console command.
     */

     public function __construct()
     {
         parent::__construct();
     }
    public function handle()
    {
        $shopifyService = new ShopifyService();
        $message = $shopifyService->registerWebhook();
        
        $this->info($message);
    }
}
