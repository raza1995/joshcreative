<?php

namespace App\Console\Commands;
use App\Services\ShopifyService;
use Illuminate\Console\Command;

class FetchShopifyOrders extends Command
{
   

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:fetch-orders';
    protected $description = 'Fetch orders from Shopify and store in the database';

    /**
     * Execute the console command.
     */
   
    public function handle(ShopifyService $shopifyService)
    {
        $this->info('Fetching orders...');
        $message = $shopifyService->fetchOrders();
        $this->info($message);
    }
}
