<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ShopifyOrder;
use Carbon\Carbon;

class ImportShopifyOrders extends Command
{
    protected $signature = 'shopify:import-orders {--since=2023-01-01T00:00:00-00:00}';
    protected $description = 'Import historical Shopify orders and save to database (no Mixpanel)';

    public function handle()
    {
        $this->info('⏳ Starting historical order import...');

        $since = $this->option('since');
        $storeDomain = env('SHOPIFY_STORE_DOMAIN');
        $accessToken = env('SHOPIFY_ACCESS_TOKEN');

        $baseUrl = "https://$storeDomain/admin/api/2023-07/orders.json";
        $url = "$baseUrl?status=any&limit=250&created_at_min=$since";

        do {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Accept' => 'application/json',
            ])->get($url);

            if (!$response->successful()) {
                $this->error('❌ Failed to fetch orders: ' . $response->body());
                return;
            }

            $orders = $response->json('orders');
            if (empty($orders)) {
                $this->info('✅ No more orders found.');
                break;
            }

            foreach ($orders as $orderData) {
                $this->processOrder($orderData);
                $this->info("✔️ Processed order ID: {$orderData['id']}");
            }

            $url = $this->getNextPageUrl($response->header('Link'));
        } while ($url);

        $this->info('✅ Import complete.');
    }

    private function getNextPageUrl($linkHeader)
    {
        if (!$linkHeader) return null;
        if (preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function processOrder(array $orderData)
    {
        $anonId = $orderData['attributes']['_anon_id'] ?? null;
        if (!$anonId && isset($orderData['note_attributes'])) {
            foreach ($orderData['note_attributes'] as $attr) {
                if ($attr['name'] === '_anon_id') {
                    $anonId = $attr['value'];
                    break;
                }
            }
        }

        $customerEmail = $orderData['email'] ?? null;
        $customerName = trim(($orderData['customer']['first_name'] ?? '') . ' ' . ($orderData['customer']['last_name'] ?? ''));
        $createdAt = Carbon::parse($orderData['created_at'])->toDateTimeString();
        $couponCode = $orderData['discount_codes'][0]['code'] ?? null;

        $rawPayload = json_encode($orderData);

        $landingSite = $orderData['landing_site'] ?? '';
        $parsedAdId = null;
        if ($landingSite) {
            $query = parse_url($landingSite, PHP_URL_QUERY);
            parse_str($query, $utm);
            $parsedAdId = $utm['ad_id'] ?? $utm['utm_content'] ?? $utm['utm_term'] ?? null;
        }

        foreach ($orderData['line_items'] as $item) {
            ShopifyOrder::updateOrCreate(
                ['order_number' => $orderData['id']],
                [
                    'order_date' => $createdAt,
                    'product_name' => $item['title'],
                    'customer_name' => $customerName,
                    'email_address' => $customerEmail,
                    'anon_id' => $anonId,
                    'raw_json' => $rawPayload,
                    'tracking_number' => $orderData['fulfillments'][0]['tracking_number'] ?? null,
                    'tracking_url' => $orderData['fulfillments'][0]['tracking_url'] ?? null,
                    'coupon' => $couponCode,
                    'paid_amount' => (float) $orderData['total_price'],
                    'discount' => $orderData['total_discounts'] ?? 0.00,
                    'number_of_items' => count($orderData['line_items']),
                    'ad_id' => $parsedAdId,
                ]
            );
        }
    }
}
