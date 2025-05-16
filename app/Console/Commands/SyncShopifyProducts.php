<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyProductSyncService;

class SyncShopifyProducts extends Command
{
    protected $signature = 'shopify:sync-products';
    protected $description = 'Sync products and variants from Shopify to the local database';

    public function handle(ShopifyProductSyncService $syncService)
    {
        $this->info('🚀 Starting Shopify product sync...');
        $success = $syncService->syncAll();

        if ($success) {
            $this->info('✅ Shopify products synced successfully!');
        } else {
            $this->error('❌ Shopify product sync failed.');
        }
    }
}

