<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\ShipStationApiService;
use App\Services\ShipStationOrderConsolidatorService;
use App\Models\ShopifyOrder;
use App\Models\ShipStationOrder;

class ShipStationWebhookController extends Controller
{
    protected $shipStationApi;
    protected $consolidator;

    public function __construct(ShipStationApiService $shipStationApi, ShipStationOrderConsolidatorService $consolidator)
    {
        $this->shipStationApi = $shipStationApi;
        $this->consolidator = $consolidator;
    }

    /**
     * Handle ShipStation webhook notifications
     */
    public function handleWebhook(Request $request)
    {
        Log::channel('shipstation_webhook')->info('ShipStation webhook received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        // Log detailed data to separate file
        Log::channel('shipstation_data')->info('Webhook Data Details', [
            'timestamp' => now()->toISOString(),
            'full_payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        try {
            $webhookData = $request->all();
            
            // Validate webhook data
            if (!$this->validateWebhookData($webhookData)) {
                Log::channel('shipstation_webhook')->warning('Invalid webhook data received', [
                    'data' => $webhookData,
                ]);
                return response()->json(['error' => 'Invalid webhook data'], 400);
            }

            $eventType = $webhookData['event'] ?? 'unknown';
            $orderId = $webhookData['resource_url'] ?? null;

            Log::channel('shipstation_webhook')->info('Processing webhook event', [
                'event_type' => $eventType,
                'order_id' => $orderId,
            ]);

            // Process based on event type
            switch ($eventType) {
                case 'ORDER_NOTIFY':
                case 'ITEM_ORDER_NOTIFY':
                    $this->processOrderNotification($orderId);
                    break;
                
                case 'SHIP_NOTIFY':
                case 'ITEM_SHIP_NOTIFY':
                    $this->processShipNotification($orderId);
                    break;
                
                case 'FULFILLMENT_SHIPPED':
                case 'FULFILLMENT_REJECTED':
                    $this->processFulfillmentNotification($orderId);
                    break;
                
                default:
                    Log::channel('shipstation_webhook')->info('Unhandled webhook event type', [
                        'event_type' => $eventType,
                    ]);
            }

            return response()->json(['status' => 'success', 'message' => 'Webhook processed']);

        } catch (\Exception $e) {
            Log::channel('shipstation_webhook')->error('Error processing ShipStation webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Process order notification - update order with consolidation and pricing
     */
    protected function processOrderNotification($orderId)
    {
        Log::channel('shipstation_webhook')->info('Processing order notification', [
            'order_id' => $orderId,
        ]);
        
        // Log detailed order processing data
        Log::channel('shipstation_data')->info('Order Processing Started', [
            'timestamp' => now()->toISOString(),
            'order_id' => $orderId,
            'action' => 'fetching_order_data',
        ]);

        try {
            // Get order from ShipStation
            $shipstationOrder = $this->shipStationApi->getOrder($orderId);
            
            // Log the raw order data
            Log::channel('shipstation_data')->info('Raw ShipStation Order Data', [
                'timestamp' => now()->toISOString(),
                'order_id' => $orderId,
                'order_data' => $shipstationOrder,
            ]);
            
            if (!$shipstationOrder) {
                Log::channel('shipstation_webhook')->warning('Order not found in ShipStation', [
                    'order_id' => $orderId,
                ]);
                return;
            }

            $orderNumber = $shipstationOrder['orderNumber'] ?? null;
            
            if (!$orderNumber) {
                Log::channel('shipstation_webhook')->warning('No order number found in ShipStation order', [
                    'order_id' => $orderId,
                    'order_data' => $shipstationOrder,
                ]);
                return;
            }

            Log::channel('shipstation_webhook')->info('Found ShipStation order', [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'status' => $shipstationOrder['orderStatus'] ?? 'unknown',
            ]);

            // Find corresponding Shopify order
            $shopifyOrder = ShopifyOrder::where('order_number', $orderNumber)->first();
            
            if (!$shopifyOrder) {
                Log::channel('shipstation_webhook')->warning('Shopify order not found for ShipStation order', [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                ]);
                return;
            }

            Log::channel('shipstation_webhook')->info('Found Shopify order', [
                'shopify_order_id' => $shopifyOrder->id,
                'order_number' => $orderNumber,
            ]);

            // Log Shopify order data
            Log::channel('shipstation_data')->info('Shopify Order Data', [
                'timestamp' => now()->toISOString(),
                'order_number' => $orderNumber,
                'shopify_order' => $shopifyOrder->toArray(),
            ]);
            
            // Apply consolidation and pricing (same logic as sync-from-api)
            $this->applyConsolidationAndPricing($shopifyOrder, $shipstationOrder);

        } catch (\Exception $e) {
            Log::channel('shipstation_webhook')->error('Error processing order notification', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Apply consolidation and pricing to order (same logic as sync-from-api)
     */
    protected function applyConsolidationAndPricing($shopifyOrder, $shipstationOrder)
    {
        Log::channel('shipstation_webhook')->info('Applying consolidation and pricing', [
            'shopify_order_id' => $shopifyOrder->id,
            'shipstation_order_id' => $shipstationOrder['orderId'] ?? 'unknown',
        ]);
        
        // Log detailed consolidation data
        Log::channel('shipstation_data')->info('Consolidation Process Started', [
            'timestamp' => now()->toISOString(),
            'shopify_order_id' => $shopifyOrder->id,
            'shipstation_order_id' => $shipstationOrder['orderId'] ?? 'unknown',
            'action' => 'applying_consolidation_and_pricing',
        ]);

        try {
            // Parse Shopify order data
            $orderData = $this->consolidator->parseShopifyOrder($shopifyOrder);
            
            if (!$orderData) {
                Log::channel('shipstation_webhook')->warning('Unable to parse Shopify order data', [
                    'shopify_order_id' => $shopifyOrder->id,
                ]);
                return;
            }

            // Validate order
            if (!$this->consolidator->validateOrder($orderData)) {
                Log::channel('shipstation_webhook')->warning('Order validation failed', [
                    'order_number' => $orderData['order_number'] ?? 'unknown',
                ]);
                return;
            }

            // Get current line items from ShipStation
            $currentLineItems = $shipstationOrder['items'] ?? [];
            
            Log::channel('shipstation_webhook')->info('Current ShipStation line items', [
                'item_count' => count($currentLineItems),
                'items' => array_map(function($item) {
                    return [
                        'sku' => $item['sku'] ?? 'unknown',
                        'name' => $item['name'] ?? 'unknown',
                        'quantity' => $item['quantity'] ?? 0,
                        'unitPrice' => $item['unitPrice'] ?? 0,
                    ];
                }, $currentLineItems),
            ]);

            // Apply same consolidation and pricing logic as sync-from-api
            $updatedItems = $this->applyConsolidationAndPricingLogic($orderData, $currentLineItems);

            if (empty($updatedItems)) {
                Log::channel('shipstation_webhook')->info('No items to update', [
                    'shopify_order_id' => $shopifyOrder->id,
                ]);
                return;
            }

            // Update order in ShipStation
            $this->updateShipStationOrder($shipstationOrder, $updatedItems);

        } catch (\Exception $e) {
            Log::channel('shipstation_webhook')->error('Error applying consolidation and pricing', [
                'shopify_order_id' => $shopifyOrder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Apply consolidation and pricing logic (same as sync-from-api)
     */
    protected function applyConsolidationAndPricingLogic($orderData, $currentLineItems)
    {
        Log::channel('shipstation_webhook')->info('Applying consolidation and pricing logic', [
            'order_id' => $orderData['id'] ?? 'unknown',
            'current_items_count' => count($currentLineItems),
        ]);

        $updatedItems = [];
        $processedSkus = [];

        foreach ($currentLineItems as $item) {
            $sku = $item['sku'] ?? '';
            $originalPrice = (float)($item['unitPrice'] ?? 0);
            $itemName = $item['name'] ?? 'Unknown';
            $quantity = $item['quantity'] ?? 1;
            $itemId = $item['orderItemId'] ?? null;

            Log::channel('shipstation_webhook')->info('Processing item', [
                'sku' => $sku,
                'name' => $itemName,
                'original_price' => $originalPrice,
                'quantity' => $quantity,
                'is_bundle' => str_starts_with($sku, 'BUND-'),
                'needs_pricing' => $originalPrice == 0 && !str_starts_with($sku, 'BUND-'),
            ]);

            // Skip if already processed
            if (in_array($sku, $processedSkus)) {
                continue;
            }

            // If it's a bundle SKU, replace with individual components
            if (str_starts_with($sku, 'BUND-')) {
                $bundleComponents = $this->getBundleComponents($sku, $orderData);
                
                if (!empty($bundleComponents)) {
                    Log::channel('shipstation_webhook')->info('Replacing bundle with components', [
                        'bundle_sku' => $sku,
                        'components' => $bundleComponents,
                    ]);

                    // Remove bundle item
                    $updatedItems[] = [
                        'orderItemId' => $itemId,
                        'action' => 'remove',
                    ];

                    // Add individual components
                    foreach ($bundleComponents as $component) {
                        $updatedItems[] = [
                            'action' => 'add',
                            'sku' => $component['sku'],
                            'name' => $component['name'],
                            'quantity' => $component['quantity'] * $quantity,
                            'unitPrice' => $component['price'],
                        ];
                    }
                }
            } else {
                // For individual items, update pricing if needed
                $newPrice = $originalPrice;
                
                if ($originalPrice == 0) {
                    $newPrice = $this->getShopifyProductPrice($sku);
                    
                    if ($newPrice > 0) {
                        Log::channel('shipstation_webhook')->info('Updating item price', [
                            'sku' => $sku,
                            'old_price' => $originalPrice,
                            'new_price' => $newPrice,
                        ]);

                        $updatedItems[] = [
                            'orderItemId' => $itemId,
                            'action' => 'update',
                            'unitPrice' => $newPrice,
                        ];
                    }
                }
            }

            $processedSkus[] = $sku;
        }

        Log::channel('shipstation_webhook')->info('Consolidation and pricing complete', [
            'updated_items_count' => count($updatedItems),
            'processed_skus' => $processedSkus,
        ]);

        return $updatedItems;
    }

    /**
     * Get bundle components (same logic as sync-from-api)
     */
    protected function getBundleComponents($bundleSku, $orderData)
    {
        $bundleComponents = config('shipstation.bundle_components', []);
        
        if (!isset($bundleComponents[$bundleSku])) {
            Log::channel('shipstation_webhook')->warning('Bundle mapping not found', [
                'bundle_sku' => $bundleSku,
            ]);
            return [];
        }

        $components = [];
        $mapping = $bundleComponents[$bundleSku];

        foreach ($mapping as $componentSku => $componentData) {
            $price = $this->getShopifyProductPrice($componentSku);
            
            if ($price > 0) {
                $components[] = [
                    'sku' => $componentSku,
                    'name' => $componentData['name'] ?? $componentSku,
                    'quantity' => $componentData['quantity'] ?? 1,
                    'price' => $price,
                ];
            }
        }

        return $components;
    }

    /**
     * Get Shopify product price (same logic as sync-from-api)
     */
    protected function getShopifyProductPrice($sku)
    {
        try {
            $product = \App\Models\ShopifyProduct::where('sku', $sku)->first();
            
            if ($product) {
                return (float) $product->price;
            }
            
            return 0.0;
        } catch (\Exception $e) {
            Log::channel('shipstation_webhook')->error('Error getting Shopify product price', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    /**
     * Update ShipStation order with new items
     */
    protected function updateShipStationOrder($shipstationOrder, $updatedItems)
    {
        $orderId = $shipstationOrder['orderId'] ?? null;
        
        if (!$orderId) {
            Log::channel('shipstation_webhook')->error('No order ID found for ShipStation update');
            return;
        }

        Log::channel('shipstation_webhook')->info('Updating ShipStation order', [
            'order_id' => $orderId,
            'items_to_update' => count($updatedItems),
        ]);

        try {
            // Use the same ShipStation API service to update the order
            $result = $this->shipStationApi->updateOrderItems($orderId, $updatedItems);
            
            Log::channel('shipstation_webhook')->info('ShipStation order updated successfully', [
                'order_id' => $orderId,
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            Log::channel('shipstation_webhook')->error('Error updating ShipStation order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Process ship notification
     */
    protected function processShipNotification($orderId)
    {
        Log::channel('shipstation_webhook')->info('Processing ship notification', [
            'order_id' => $orderId,
        ]);
        // Add ship notification logic if needed
    }

    /**
     * Process fulfillment notification
     */
    protected function processFulfillmentNotification($orderId)
    {
        Log::channel('shipstation_webhook')->info('Processing fulfillment notification', [
            'order_id' => $orderId,
        ]);
        // Add fulfillment notification logic if needed
    }

    /**
     * Validate webhook data
     */
    protected function validateWebhookData($data)
    {
        return isset($data['event']) && isset($data['resource_url']);
    }
}
