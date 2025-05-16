<?php

namespace App\Console\Commands;

use App\Services\ShopifyOrderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchShopifyMycoleanOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:fetch-orders-mycolean';
    protected $description = 'Fetch last 30 days of Shopify orders and save them in DB';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🛒 Fetching Shopify orders...');
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $service = new ShopifyOrderService();
        $service->fetchAndSaveOrders($startDate, $endDate);

        $this->info('✅ Shopify orders synced successfully.');
    }
}
