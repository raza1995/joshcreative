<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\ShopifyOrder;

class SendJuneOrdersToFunnelytics extends Command
{
    protected $signature = 'orders:send-june-funnelytics';
    protected $description = 'Send June Shopify order data to Funnelytics with historical timestamps';

    public function handle()
    {
        $this->info('Sending June Shopify orders to Funnelytics...');

        $orders = ShopifyOrder::whereBetween('order_date', ['2025-06-01 00:00:00', '2025-06-30 23:59:59'])->get();

        $endpoint = 'https://events.funnelytics.io/api/v1/webhook/__commerce_action__';
       

        $headers = [
            'Content-Type' => 'application/json',
            'X-PROJECT-ID' => '07ac73bb-23ab-4f87-a538-a1f67c28af43',
            'X-API-KEY' => 'Basic MDdhYzczYmItMjNhYi00Zjg3LWE1MzgtYTFmNjdjMjhhZjQzOjU1YjE1MWQ3YzIzM2E3YWMxZDdiNTk2NGJmMTlhYzZmMmQ2YTRhNmJiZGExNWZiOTI2OGRmZGRjMWI0NmJhMTk=',
        ];

        foreach ($orders as $order) {
            $data = json_decode($order->raw_json, true);
            $lineItem = $data['line_items'][0] ?? null;

            if (!$lineItem) {
                $this->warn("Skipping order #{$data['name']} due to missing line item.");
                continue;
            }

            $payload = [
                'email' => $data['email'],
                'dateTime' => \Carbon\Carbon::parse($data['created_at'])->toIso8601String(),
                'purchase_data' => [[
                    '__sku__' => $lineItem['sku'] ?? 'unknown',
                    '__label__' => $lineItem['title'],
                    '__total_in_cents__' => (int) (floatval($data['total_price']) * 100),
                    '__order__' => $data['name'],
                    '__currency__' => $data['currency'] ?? 'USD',
                ]]
            ];

            $response = Http::withHeaders($headers)->post($endpoint, $payload);

            if ($response->successful()) {
                $this->info("✅ Sent order {$data['name']} at {$payload['dateTime']}");
            } else {
                $this->error("❌ Failed for order {$data['name']}: " . $response->body());
            }

            usleep(500000); // 0.5s delay to avoid rate limits
        }

        $this->info('Done sending all June orders.');
    }
}
