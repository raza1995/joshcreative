<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ShopifyOrder;

class BackfillShopifyOrderAdIds extends Command
{
    protected $signature = 'shopify:backfill-ad-ids';
    protected $description = 'Backfill ad_id from raw_json landing_site for ShopifyOrders where ad_id is null';

    public function handle()
    {
        $orders = ShopifyOrder::whereNull('ad_id')->get();
        $updated = 0;
        $skipped = 0;
    
        foreach ($orders as $order) {
            $data = json_decode($order->raw_json, true);
    
            if (!isset($data['landing_site'])) {
                $this->warn("⛔ Skipped #{$order->order_number} — No landing_site in raw_json.");
                $skipped++;
                continue;
            }
    
            $query = parse_url($data['landing_site'], PHP_URL_QUERY);
            parse_str($query, $utm);
    
            $parsedAdId = null;
    
            if (!empty($utm['ad_id']) && preg_match('/^\d{10,30}$/', $utm['ad_id'])) {
                $parsedAdId = $utm['ad_id'];
            } elseif (!empty($utm['utm_content']) && preg_match('/^\d{10,30}$/', $utm['utm_content'])) {
                $parsedAdId = $utm['utm_content'];
            }
    
            if ($parsedAdId) {
                $order->ad_id = $parsedAdId;
                $order->save();
    
                $this->info("✅ Updated order #{$order->order_number} with ad_id: {$parsedAdId}");
                $updated++;
            } else {
                $this->warn("⚠️ Skipped #{$order->order_number} — No valid ad_id or utm_content found.");
                $skipped++;
            }
        }
    
        $this->line("---------------------------------------------------");
        $this->info("🎯 Total orders processed: " . $orders->count());
        $this->info("✅ Orders updated: {$updated}");
        $this->warn("⛔ Orders skipped: {$skipped}");
    }
    
}
