<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ShopifyOrder;

class ExtractAdIdFromShopifyOrders extends Command
{
    protected $signature = 'shopify:extract-ad-id';
    protected $description = 'Extract ad_id from raw_json and update ShopifyOrder records';

    public function handle()
    {
        $orders = ShopifyOrder::whereNull('ad_id')->get();
        $updated = 0;

        foreach ($orders as $order) {
            try {
                $raw = json_decode($order->raw_json, true);

                if (!isset($raw['landing_site'])) {
                    continue;
                }

                $queryString = parse_url($raw['landing_site'], PHP_URL_QUERY);
                parse_str($queryString, $params);

                $adId = $params['ad_id'] ?? null;

                if ($adId) {
                    $order->ad_id = $adId;
                    $order->save();
                    $updated++;
                }
            } catch (\Exception $e) {
                $this->error("Failed on Order #{$order->id}: " . $e->getMessage());
            }
        }

        $this->info("✅ Updated $updated orders with ad_id.");
    }
}

