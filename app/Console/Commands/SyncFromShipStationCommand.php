<?php

namespace App\Console\Commands;

use App\Models\ShipStationProcessedOrder;
use App\Services\ShipStationPullService;
use App\Services\ShipStationApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncFromShipStationCommand extends Command
{
    protected $signature = 'shipstation:sync-from-api
                            {--minutes=60 : Pull orders modified in last X minutes}
                            {--order-id= : Process a specific order by ShipStation order ID}
                            {--force : Reprocess already processed orders}
                            {--dry-run : Show what would be done without actually doing it}';

    protected $description = 'Pull orders from ShipStation and consolidate duplicate SKUs';

    protected $pullService;
    protected $apiService;

    public function __construct(
        ShipStationPullService $pullService,
        ShipStationApiService $apiService
    ) {
        parent::__construct();
        $this->pullService = $pullService;
        $this->apiService = $apiService;
    }

    public function handle()
    {
        $this->info('🔄 Syncing orders from ShipStation...');
        $this->newLine();

        $minutes = (int)$this->option('minutes');
        $orderId = $this->option('order-id');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Pull orders from ShipStation
        if ($orderId) {
            $this->info("🎯 Fetching specific order: {$orderId}");
            $orders = $this->pullService->pullSpecificOrder($orderId);
        } else {
            $orders = $this->pullService->pullNewOrders($minutes);
        }

        if (empty($orders)) {
            if ($orderId) {
                $this->warn("Order {$orderId} not found in ShipStation");
            } else {
                $this->warn('No orders found in ShipStation from last ' . $minutes . ' minutes');
            }
            return Command::SUCCESS;
        }

        $this->info("📦 Found " . count($orders) . " order(s) from ShipStation");
        $this->newLine();

        $stats = [
            'total' => count($orders),
            'consolidated' => 0,
            'skipped_no_duplicates' => 0,
            'skipped_already_processed' => 0,
            'skipped_shipped' => 0,
            'failed' => 0,
        ];

        $bar = $this->output->createProgressBar(count($orders));
        $bar->start();

        foreach ($orders as $order) {
            $bar->advance();

            $shipstationOrderId = (string)$order['orderId'];
            $orderNumber = $order['orderNumber'] ?? 'N/A';
            $orderKey = $order['orderKey'] ?? 'N/A';
            $orderStatus = $order['orderStatus'] ?? 'unknown';

            try {
                // Check if already processed
                if (!$force && ShipStationProcessedOrder::wasProcessed($shipstationOrderId)) {
                    $stats['skipped_already_processed']++;
                    continue;
                }

                // Check if order can be updated
                if (in_array($orderStatus, ['shipped', 'cancelled'])) {
                    if (!$dryRun) {
                        ShipStationProcessedOrder::markAsProcessed(
                            $shipstationOrderId,
                            $orderNumber,
                            $orderKey,
                            'skipped_shipped',
                            count($order['items'] ?? []),
                            count($order['items'] ?? []),
                            "Order status: {$orderStatus} - cannot be updated"
                        );
                    }
                    $stats['skipped_shipped']++;
                    continue;
                }

                // Check if has duplicate SKUs, needs image URL updates, has bundle SKUs to remove, or needs pricing updates
                $needsImageUpdate = $this->needsImageUpdate($order);
                $hasBundleSkus = $this->hasBundleSkus($order);
                $needsPricingUpdate = $this->needsPricingUpdate($order);
                
                if (!$this->pullService->hasDuplicateSKUs($order) && !$needsImageUpdate && !$hasBundleSkus && !$needsPricingUpdate) {
                    if (!$dryRun) {
                        ShipStationProcessedOrder::markAsProcessed(
                            $shipstationOrderId,
                            $orderNumber,
                            $orderKey,
                            'skipped_no_duplicates',
                            count($order['items'] ?? []),
                            count($order['items'] ?? []),
                            'No duplicate SKUs, image updates, bundle SKUs to remove, or pricing updates needed'
                        );
                    }
                    $stats['skipped_no_duplicates']++;
                    continue;
                }

                // Consolidate items
                $originalItems = $order['items'] ?? [];
                $consolidatedItems = $this->pullService->consolidateItems(
                    $originalItems,
                    $shipstationOrderId,
                    $orderNumber
                );

                Log::info('Consolidating order from ShipStation', [
                    'order_id' => $shipstationOrderId,
                    'order_number' => $orderNumber,
                    'items_before' => count($originalItems),
                    'items_after' => count($consolidatedItems),
                ]);

                if ($dryRun) {
                    $this->newLine();
                    $this->line("Would consolidate order {$orderNumber}: " . count($originalItems) . " → " . count($consolidatedItems) . " items");
                    $stats['consolidated']++;
                    continue;
                }

                // Apply pricing calculations to consolidated items
                $itemsWithPricing = $this->applyPricingToItems($consolidatedItems, $order);
                
                // Update order in ShipStation with consolidated items and pricing
                $updatedOrder = array_merge($order, [
                    'items' => $itemsWithPricing,
                ]);

                $response = $this->updateOrderInShipStation($updatedOrder);

                if ($response['success']) {
                    ShipStationProcessedOrder::markAsProcessed(
                        $shipstationOrderId,
                        $orderNumber,
                        $orderKey,
                        'consolidated',
                        count($originalItems),
                        count($consolidatedItems),
                        'Successfully consolidated and updated',
                        [
                            'reduction' => count($originalItems) - count($consolidatedItems),
                        ]
                    );
                    $stats['consolidated']++;
                } else {
                    ShipStationProcessedOrder::markAsProcessed(
                        $shipstationOrderId,
                        $orderNumber,
                        $orderKey,
                        'failed',
                        count($originalItems),
                        null,
                        'Update failed: ' . ($response['error'] ?? 'Unknown error')
                    );
                    $stats['failed']++;
                }

            } catch (\Exception $e) {
                Log::error('Error processing order from ShipStation', [
                    'order_id' => $shipstationOrderId,
                    'error' => $e->getMessage(),
                ]);
                $stats['failed']++;
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Display summary
        $this->info('✅ Sync completed!');
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Orders', $stats['total']],
                ['Consolidated ✅', $stats['consolidated']],
                ['No Duplicates', $stats['skipped_no_duplicates']],
                ['Already Processed', $stats['skipped_already_processed']],
                ['Already Shipped', $stats['skipped_shipped']],
                ['Failed', $stats['failed']],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Check if order has items that need image URL updates
     */
    protected function needsImageUpdate(array $order): bool
    {
        $imageOverrides = config('shipstation.sku_image_overrides', []);
        
        if (empty($imageOverrides)) {
            return false;
        }

        $items = $order['items'] ?? [];
        
        foreach ($items as $item) {
            $sku = $item['sku'] ?? null;
            if ($sku && isset($imageOverrides[$sku])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if order has bundle SKUs that need to be removed
     */
    protected function hasBundleSkus(array $order): bool
    {
        $items = $order['items'] ?? [];
        
        foreach ($items as $item) {
            $sku = $item['sku'] ?? '';
            if (str_starts_with($sku, 'BUND-')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if order needs pricing updates (has items with zero pricing)
     */
    protected function needsPricingUpdate(array $order): bool
    {
        $items = $order['items'] ?? [];
        
        foreach ($items as $item) {
            $sku = $item['sku'] ?? '';
            $price = (float)($item['unitPrice'] ?? 0);
            
            // If item has zero price and is not a bundle, it needs pricing update
            if ($price == 0 && !str_starts_with($sku, 'BUND-')) {
                return true;
            }
        }
        
        return false;
    }

    protected function updateOrderInShipStation(array $order): array
    {
        try {
            // Log the items being sent with pricing details
            $itemsSummary = [];
            foreach ($order['items'] ?? [] as $item) {
                $itemsSummary[] = [
                    'sku' => $item['sku'] ?? 'N/A',
                    'name' => $item['name'] ?? 'N/A',
                    'quantity' => $item['quantity'] ?? 0,
                    'unitPrice' => $item['unitPrice'] ?? 0,
                    'total' => ($item['quantity'] ?? 0) * ($item['unitPrice'] ?? 0),
                ];
            }
            
            Log::channel('pricing')->info('SHIPSTATION API REQUEST', [
                'timestamp' => now()->toDateTimeString(),
                'order_id' => $order['orderId'] ?? 'MISSING',
                'order_number' => $order['orderNumber'] ?? 'Unknown',
                'order_key' => $order['orderKey'] ?? 'Unknown',
                'order_status' => $order['orderStatus'] ?? 'Unknown',
                'items_count' => count($order['items'] ?? []),
                'items_detail' => $itemsSummary,
                'endpoint' => config('shipstation.base_url') . '/orders/createorder',
                'note' => 'orderId field is REQUIRED to update existing orders',
            ]);
            
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode(config('shipstation.api_key') . ':' . config('shipstation.api_secret')),
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post(config('shipstation.base_url') . '/orders/createorder', $order);

            if ($response->successful()) {
                Log::channel('pricing')->info('SHIPSTATION API SUCCESS', [
                    'timestamp' => now()->toDateTimeString(),
                    'order_number' => $order['orderNumber'] ?? 'Unknown',
                    'status' => 'success',
                ]);
                
                return ['success' => true];
            }

            Log::channel('pricing')->error('SHIPSTATION API FAILED', [
                'timestamp' => now()->toDateTimeString(),
                'order_number' => $order['orderNumber'] ?? 'Unknown',
                'status' => 'failed',
                'error' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::channel('pricing')->error('SHIPSTATION API EXCEPTION', [
                'timestamp' => now()->toDateTimeString(),
                'order_number' => $order['orderNumber'] ?? 'Unknown',
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Apply pricing calculations to items using Shopify product data with discount handling
     */
    protected function applyPricingToItems(array $items, array $order): array
    {
        $customerEmail = $order['customerEmail'] ?? null;
        $orderNumber = $order['orderNumber'] ?? 'Unknown';
        $orderTotal = (float)($order['orderTotal'] ?? 0);
        $amountPaid = (float)($order['amountPaid'] ?? 0);
        
        // Smart pricing: detect bundle context and apply appropriate pricing
        $shopifyOrderData = $this->getShopifyOrderData($orderNumber);
        
        Log::channel('pricing')->info('🚀 APPLYING SMART PRICING TO ITEMS', [
            'order_number' => $orderNumber,
            'customer_email' => $customerEmail,
            'total_items' => count($items),
            'pricing_method' => 'smart_bundle_vs_individual_pricing',
            'has_shopify_data' => !is_null($shopifyOrderData),
            'timestamp' => now()->toDateTimeString(),
        ]);
        
        $updatedItems = [];
        $pricingStats = [
            'total_items' => count($items),
            'zero_price_items' => 0,
            'bundle_items' => 0,
            'pricing_attempted' => 0,
            'pricing_successful' => 0,
            'pricing_failed' => 0,
        ];
        
        foreach ($items as $index => $item) {
            $sku = $item['sku'] ?? '';
            $originalPrice = (float)($item['unitPrice'] ?? 0);
            $itemName = $item['name'] ?? 'Unknown';
            $quantity = $item['quantity'] ?? 1;
            
            Log::channel('pricing')->info("📦 PROCESSING ITEM #" . ($index + 1), [
                'sku' => $sku,
                'name' => $itemName,
                'original_price' => $originalPrice,
                'quantity' => $quantity,
                'is_bundle' => str_starts_with($sku, 'BUND-'),
                'needs_pricing' => $originalPrice == 0 && !str_starts_with($sku, 'BUND-'),
            ]);
            
            // Count statistics
            if ($originalPrice == 0) {
                $pricingStats['zero_price_items']++;
            }
            if (str_starts_with($sku, 'BUND-')) {
                $pricingStats['bundle_items']++;
            }
            
            // If item has zero price and is not a bundle, get price from Shopify
            if ($originalPrice == 0 && !str_starts_with($sku, 'BUND-')) {
                $pricingStats['pricing_attempted']++;
                
                Log::channel('pricing')->info('💰 ATTEMPTING SHOPIFY PRICING', [
                    'sku' => $sku,
                    'reason' => 'zero_price_non_bundle_item',
                    'original_price' => $originalPrice,
                ]);
                
                // Get the appropriate price based on order context
                $finalPrice = $this->getAppropriatePrice($sku, $shopifyOrderData);
                
                if ($finalPrice > 0) {
                    $item['unitPrice'] = $finalPrice;
                    $pricingStats['pricing_successful']++;
                    
                    Log::channel('pricing')->info('✅ SMART PRICING SUCCESS', [
                        'timestamp' => now()->toDateTimeString(),
                        'order_number' => $orderNumber,
                        'sku' => $sku,
                        'item_name' => $itemName,
                        'quantity' => $quantity,
                        'original_unit_price' => $originalPrice,
                        'final_price' => $finalPrice,
                        'price_change' => $finalPrice - $originalPrice,
                        'total_value_change' => ($finalPrice - $originalPrice) * $quantity,
                        'pricing_method' => $this->getPricingMethod($sku, $shopifyOrderData),
                        'customer_email' => $customerEmail,
                        'status' => 'SUCCESS',
                    ]);
                    
                    Log::info('Applied smart pricing', [
                        'sku' => $sku,
                        'original_price' => $originalPrice,
                        'final_price' => $finalPrice,
                        'method' => $this->getPricingMethod($sku, $shopifyOrderData),
                        'change' => '+$' . number_format($finalPrice - $originalPrice, 2),
                    ]);
                } else {
                    $pricingStats['pricing_failed']++;
                    
                    Log::channel('pricing')->warning('❌ SHOPIFY PRICING FAILED', [
                        'timestamp' => now()->toDateTimeString(),
                        'order_number' => $orderNumber,
                        'sku' => $sku,
                        'item_name' => $itemName,
                        'original_price' => $originalPrice,
                        'shopify_price' => $shopifyPrice,
                        'reason' => 'product_not_found_in_shopify',
                        'customer_email' => $customerEmail,
                        'status' => 'FAILED',
                    ]);
                }
            } else {
                Log::channel('pricing')->info('⏭️ SKIPPING PRICING', [
                    'sku' => $sku,
                    'reason' => $originalPrice > 0 ? 'price_already_set' : 'is_bundle_item',
                    'original_price' => $originalPrice,
                ]);
            }
            
            $updatedItems[] = $item;
        }
        
        Log::channel('pricing')->info('📊 PRICING STATISTICS', [
            'order_number' => $orderNumber,
            'customer_email' => $customerEmail,
            'statistics' => $pricingStats,
            'success_rate' => $pricingStats['pricing_attempted'] > 0 
                ? round(($pricingStats['pricing_successful'] / $pricingStats['pricing_attempted']) * 100, 2) . '%'
                : 'N/A',
            'timestamp' => now()->toDateTimeString(),
        ]);
        
        return $updatedItems;
    }

    /**
     * Get product price from Shopify database
     */
    protected function getShopifyProductPrice(string $sku): float
    {
        try {
            $product = \App\Models\ShopifyProduct::findBySku($sku);
            
            if (!$product) {
                Log::channel('pricing')->warning('Product not found in Shopify database', [
                    'sku' => $sku,
                    'reason' => 'product_not_synced',
                ]);
                return 0.0;
            }
            
            $price = $product->getEffectivePrice();
            
            Log::channel('pricing')->info('Found Shopify product price', [
                'sku' => $sku,
                'product_title' => $product->title,
                'price' => $price,
                'compare_at_price' => $product->compare_at_price,
                'last_synced' => $product->last_synced_at,
            ]);
            
            return $price;
            
        } catch (\Exception $e) {
            Log::channel('pricing')->error('Error getting Shopify product price', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    /**
     * Get appropriate price based on order context (bundle vs individual)
     */
    protected function getAppropriatePrice(string $sku, ?array $shopifyOrderData): float
    {
        // First, check if this is a bundle order
        if ($shopifyOrderData && $this->isBundleOrder($shopifyOrderData)) {
            $bundlePrice = $this->getBundleComponentPrice($sku, $shopifyOrderData);
            if ($bundlePrice > 0) {
                Log::channel('pricing')->info('Using bundle component pricing', [
                    'sku' => $sku,
                    'bundle_price' => $bundlePrice,
                    'reason' => 'bundle_order_detected',
                ]);
                return $bundlePrice;
            }
        }
        
        // Fallback to individual product price
        $individualPrice = $this->getShopifyProductPrice($sku);
        Log::channel('pricing')->info('Using individual product pricing', [
            'sku' => $sku,
            'individual_price' => $individualPrice,
            'reason' => 'individual_order_or_bundle_not_found',
        ]);
        
        return $individualPrice;
    }

    /**
     * Get pricing method for logging
     */
    protected function getPricingMethod(string $sku, ?array $shopifyOrderData): string
    {
        if ($shopifyOrderData && $this->isBundleOrder($shopifyOrderData)) {
            return 'bundle_component_pricing';
        }
        return 'individual_product_pricing';
    }

    /**
     * Check if order is a bundle order
     */
    protected function isBundleOrder(array $shopifyOrderData): bool
    {
        $lineItems = $shopifyOrderData['line_items'] ?? [];
        $bundleComponents = [];
        
        // Check if we have REG-* SKUs that are part of bundles
        foreach ($lineItems as $item) {
            $sku = $item['sku'] ?? '';
            if (str_starts_with($sku, 'REG-')) {
                $bundleComponents[] = $sku;
            }
        }
        
        // If we have 4 REG-* components, it's likely a bundle
        if (count($bundleComponents) >= 4) {
            Log::channel('pricing')->info('Bundle order detected', [
                'component_count' => count($bundleComponents),
                'components' => $bundleComponents,
            ]);
            return true;
        }
        
        return false;
    }

    /**
     * Get bundle component price
     */
    protected function getBundleComponentPrice(string $sku, array $shopifyOrderData): float
    {
        $lineItems = $shopifyOrderData['line_items'] ?? [];
        $bundleComponents = [];
        $bundleTotal = 0;
        
        // Find all REG-* components and their total
        foreach ($lineItems as $item) {
            $itemSku = $item['sku'] ?? '';
            if (str_starts_with($itemSku, 'REG-')) {
                $bundleComponents[] = $itemSku;
                $bundleTotal += (float)($item['price'] ?? 0);
            }
        }
        
        if (count($bundleComponents) > 0 && $bundleTotal > 0) {
            $pricePerComponent = $bundleTotal / count($bundleComponents);
            
            Log::channel('pricing')->info('Bundle pricing calculation', [
                'sku' => $sku,
                'bundle_components' => $bundleComponents,
                'bundle_total' => $bundleTotal,
                'component_count' => count($bundleComponents),
                'price_per_component' => $pricePerComponent,
            ]);
            
            return round($pricePerComponent, 2);
        }
        
        return 0.0;
    }

    /**
     * Apply discount percentage to a price
     */
    protected function applyDiscountToPrice(float $price, float $discountPercentage): float
    {
        if ($discountPercentage <= 0) {
            return $price;
        }
        
        $discountAmount = ($price * $discountPercentage) / 100;
        $discountedPrice = $price - $discountAmount;
        
        Log::channel('pricing')->info('Applied discount to price', [
            'original_price' => $price,
            'discount_percentage' => $discountPercentage . '%',
            'discount_amount' => $discountAmount,
            'discounted_price' => $discountedPrice,
        ]);
        
        return round($discountedPrice, 2);
    }

    /**
     * Check if Shopify order has bundle with component discounts
     */
    protected function hasBundleWithComponentDiscounts(array $shopifyOrderData): bool
    {
        $lineItems = $shopifyOrderData['line_items'] ?? [];
        
        $hasBundle = false;
        $hasComponentsWithDiscounts = false;
        
        foreach ($lineItems as $item) {
            $sku = $item['sku'] ?? '';
            $discountAllocation = (float)($item['discount_allocations'][0]['amount'] ?? 0);
            $price = (float)($item['price'] ?? 0);
            
            // Check if this is a bundle SKU
            if (str_starts_with($sku, 'BUND-')) {
                $hasBundle = true;
            }
            
            // Check if this is a component with 100% discount
            if (str_starts_with($sku, 'REG-') && $discountAllocation >= $price && $price > 0) {
                $hasComponentsWithDiscounts = true;
            }
        }
        
        Log::channel('pricing')->info('Bundle order analysis', [
            'has_bundle' => $hasBundle,
            'has_components_with_discounts' => $hasComponentsWithDiscounts,
            'is_bundle_with_component_discounts' => $hasBundle && $hasComponentsWithDiscounts,
        ]);
        
        return $hasBundle && $hasComponentsWithDiscounts;
    }

    /**
     * Get original Shopify order data to verify discount information
     */
    protected function getShopifyOrderData(string $orderNumber): ?array
    {
        try {
            // First try to find the order in our database
            $shopifyOrder = \App\Models\ShopifyOrder::where('order_number', $orderNumber)->first();
            
            if ($shopifyOrder && $shopifyOrder->raw_json) {
                Log::channel('pricing')->info('Found Shopify order in database', [
                    'order_number' => $orderNumber,
                    'shopify_order_id' => $shopifyOrder->id,
                ]);
                return $shopifyOrder->raw_json;
            }
            
            // If not found in database, fetch from Shopify API
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->get("https://" . env('SHOPIFY_STORE_DOMAIN') . "/admin/api/2025-10/orders.json", [
                'name' => $orderNumber,
                'limit' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $orders = $data['orders'] ?? [];
                
                if (!empty($orders)) {
                    $order = $orders[0];
                    
                    Log::channel('pricing')->info('Fetched Shopify order from API', [
                        'order_number' => $orderNumber,
                        'shopify_order_id' => $order['id'],
                        'total_price' => $order['total_price'] ?? 0,
                        'total_discounts' => $order['total_discounts'] ?? 0,
                        'discount_codes' => $order['discount_codes'] ?? [],
                    ]);
                    
                    return $order;
                }
            }
            
            Log::channel('pricing')->warning('Shopify order not found', [
                'order_number' => $orderNumber,
                'api_response_status' => $response->status(),
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            Log::channel('pricing')->error('Error fetching Shopify order data', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}


