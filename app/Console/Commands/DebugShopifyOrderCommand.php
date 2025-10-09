<?php

namespace App\Console\Commands;

use App\Models\ShopifyOrder;
use Illuminate\Console\Command;

class DebugShopifyOrderCommand extends Command
{
    protected $signature = 'shipstation:debug-order {order_number}';
    protected $description = 'Debug why an order is failing validation';

    public function handle()
    {
        $orderNumber = $this->argument('order_number');
        
        $order = ShopifyOrder::where('order_number', $orderNumber)->first();
        
        if (!$order) {
            $this->error("❌ Order {$orderNumber} not found in database");
            return Command::FAILURE;
        }

        $this->info("📦 Debugging Order: {$orderNumber}");
        $this->newLine();

        // Parse raw JSON
        $rawJson = is_string($order->raw_json) 
            ? json_decode($order->raw_json, true) 
            : $order->raw_json;

        if (!$rawJson || !is_array($rawJson)) {
            $this->error("❌ Invalid or missing raw_json data");
            return Command::FAILURE;
        }

        // Check shipping address
        $this->info("🚚 SHIPPING ADDRESS:");
        $shipping = $rawJson['shipping_address'] ?? null;
        
        if (!$shipping) {
            $this->error("   ❌ Missing shipping_address entirely");
        } else {
            $this->table(
                ['Field', 'Value', 'Status'],
                [
                    ['address1', $shipping['address1'] ?? 'MISSING', !empty($shipping['address1']) ? '✅' : '❌ REQUIRED'],
                    ['address2', $shipping['address2'] ?? 'null', '✅'],
                    ['city', $shipping['city'] ?? 'MISSING', !empty($shipping['city']) ? '✅' : '❌ REQUIRED'],
                    ['province', $shipping['province'] ?? 'null', '✅'],
                    ['zip', $shipping['zip'] ?? 'null', '✅'],
                    ['country', $shipping['country'] ?? 'null', '✅'],
                ]
            );
        }

        $this->newLine();

        // Check line items
        $this->info("📋 LINE ITEMS:");
        $lineItems = $rawJson['line_items'] ?? [];
        
        if (empty($lineItems)) {
            $this->error("   ❌ No line items found");
        } else {
            $this->info("   ✅ Found " . count($lineItems) . " line item(s)");
            $this->table(
                ['#', 'SKU', 'Title', 'Quantity', 'Price'],
                array_map(function($item, $index) {
                    return [
                        $index + 1,
                        $item['sku'] ?? 'NO-SKU',
                        $item['title'] ?? 'Unknown',
                        $item['quantity'] ?? 0,
                        '$' . number_format($item['price'] ?? 0, 2),
                    ];
                }, $lineItems, array_keys($lineItems))
            );
        }

        $this->newLine();

        // Check totals
        $this->info("💰 ORDER TOTALS:");
        $this->table(
            ['Field', 'Value'],
            [
                ['Total Price', '$' . number_format($rawJson['total_price'] ?? 0, 2)],
                ['Subtotal', '$' . number_format($rawJson['subtotal_price'] ?? 0, 2)],
                ['Tax', '$' . number_format($rawJson['total_tax'] ?? 0, 2)],
                ['Discount', '$' . number_format($rawJson['total_discounts'] ?? 0, 2)],
            ]
        );

        $this->newLine();

        // Validation summary
        $this->info("🔍 VALIDATION SUMMARY:");
        
        $validationConfig = config('shipstation.validation');
        $issues = [];

        if ($validationConfig['require_shipping_address'] ?? true) {
            if (!$shipping || empty($shipping['address1']) || empty($shipping['city'])) {
                $issues[] = '❌ Shipping address incomplete (address1 or city missing)';
            } else {
                $this->line("   ✅ Shipping address valid");
            }
        }

        if ($validationConfig['require_line_items'] ?? true) {
            if (empty($lineItems)) {
                $issues[] = '❌ No line items found';
            } else {
                $this->line("   ✅ Line items present");
            }
        }

        $minTotal = $validationConfig['minimum_order_total'] ?? 0;
        if ($minTotal > 0) {
            $total = floatval($rawJson['total_price'] ?? 0);
            if ($total < $minTotal) {
                $issues[] = "❌ Order total \${$total} is below minimum \${$minTotal}";
            } else {
                $this->line("   ✅ Order total meets minimum");
            }
        }

        $this->newLine();

        if (empty($issues)) {
            $this->info("✅ Order should pass validation!");
            $this->newLine();
            $this->info("Try running:");
            $this->line("   php artisan shipstation:push-orders --order-number={$orderNumber} --sync");
        } else {
            $this->error("❌ VALIDATION ISSUES FOUND:");
            foreach ($issues as $issue) {
                $this->line("   {$issue}");
            }
            $this->newLine();
            $this->info("💡 SOLUTIONS:");
            $this->line("   1. Check if order has shipping address in Shopify");
            $this->line("   2. Disable validation: Set config in shipstation.php");
            $this->line("   3. Update order in Shopify and re-sync");
        }

        return Command::SUCCESS;
    }
}

