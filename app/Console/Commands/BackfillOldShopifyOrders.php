<?php
namespace App\Console\Commands;
use App\Models\ShopifyOrder;

use App\Services\MixpanelService;
use Illuminate\Console\Command;
use App\Services\MixpanelBackfillService;
class BackfillOldShopifyOrders extends Command
{
    protected $signature = 'mixpanel:backfill-checkouts';
    protected $description = 'Backfill Shopify orders into Mixpanel before 2025-05-11';

    public function handle(MixpanelBackfillService $mixpanelbackfillservice, MixpanelService $mixpanelService)
    {
        $orders = ShopifyOrder::whereDate('order_date', '<', '2025-05-11')->get();
    
        foreach ($orders as $order) {
            try {
                $timestamp = \Carbon\Carbon::parse($order->order_date)->timestamp;
                $distinctId = $order->email_address;
    
                if ($mixpanelbackfillservice->hasAlreadyImported($distinctId, 'checkout_completed', $timestamp)) {
                    $this->info("⏩ Skipped duplicate: $distinctId @ $timestamp");
                    continue;
                }
    
                // Build event payload
                $event = [
                    'event' => 'checkout_completed',
                    'properties' => [
                        'token' => '024e3f4490da943bc64421605ee79cc7',
                        'distinct_id' => $distinctId,
                        'time' => $timestamp,
                        'discountCodesUsed' => $order->coupon ? [$order->coupon] : [],
                        'totalPrice' => (float) $order->paid_amount,
                        'firstItem' => [
                            'title' => $order->product_name,
                            'quantity' => $order->number_of_items,
                            'discount' => [
                                'amount' => (float) $order->discount,
                                'currencyCode' => 'USD'
                            ],
                        ],
                        'paymentTransactions' => [
                            ['amount' => $order->paid_amount, 'paymentGateway' => 'unknown']
                        ],
                        'event' => [ 'clientId' => $order->anon_id ?? null ],
                    ]
                ];
    
                $mixpanelService->importEvent($event);
    
                $mixpanelService->identifyUser($distinctId, [
                    'email' => $distinctId,
                    'Total Spend' => ShopifyOrder::where('email_address', $distinctId)->sum('paid_amount'),
                    'Last Order Date' => $order->order_date,
                    'Used Discount' => !empty($order->coupon),
                ]);
    
                $this->info("✅ Sent: $distinctId @ $timestamp");
    
            } catch (\Throwable $e) {
                \Log::error('❌ Failed to backfill order', [
                    'email' => $order->email_address,
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("❌ Failed: {$order->email_address} - {$e->getMessage()}");
                continue;
            }
        }
    
        $this->info("✅ Backfill complete.");
    }
    
}
