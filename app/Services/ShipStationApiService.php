<?php

namespace App\Services;

use App\Models\ShipStationOrder;
use App\Models\ShipStationSyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShipStationApiService
{
    protected $apiKey;
    protected $apiSecret;
    protected $baseUrl;
    protected $authHeader;
    protected $logApiRequests;
    protected $logApiResponses;
    protected $testMode;

    public function __construct()
    {
        $this->apiKey = config('shipstation.api_key');
        $this->apiSecret = config('shipstation.api_secret');
        $this->baseUrl = rtrim(config('shipstation.base_url', 'https://ssapi.shipstation.com'), '/');
        $this->authHeader = 'Basic ' . base64_encode($this->apiKey . ':' . $this->apiSecret);
        $this->logApiRequests = config('shipstation.logging.log_api_requests', true);
        $this->logApiResponses = config('shipstation.logging.log_api_responses', false);
        $this->testMode = config('shipstation.test_mode', false);
    }

    /**
     * Push order to ShipStation (create or update)
     * 
     * @param ShipStationOrder $order
     * @return array ['success' => bool, 'order_id' => string|null, 'error' => string|null]
     */
    public function pushOrder(ShipStationOrder $order): array
    {
        if ($this->testMode) {
            Log::info('[TEST MODE] Would push order to ShipStation', [
                'order_number' => $order->order_number,
            ]);
            
            return [
                'success' => true,
                'order_id' => 'TEST-' . $order->order_number,
                'error' => null,
                'test_mode' => true,
                'action' => 'test',
            ];
        }

        try {
            $syncStrategy = config('shipstation.sync_strategy', 'update_if_exists');
            $onlyUpdateExisting = config('shipstation.only_update_existing', false);
            
            // Check if order exists in ShipStation
            $existingOrder = null;
            if ($syncStrategy === 'update_if_exists' || $onlyUpdateExisting) {
                $existingOrder = $this->getOrderByKey($order->order_key);
                
                if ($existingOrder) {
                    $orderStatus = $existingOrder['orderStatus'] ?? null;
                    
                    Log::info('Order exists in ShipStation (likely from auto-sync), will update with consolidated SKUs', [
                        'order_key' => $order->order_key,
                        'shipstation_order_id' => $existingOrder['orderId'] ?? null,
                        'order_status' => $orderStatus,
                    ]);
                    
                    // Check if order can be updated (only awaiting_payment, awaiting_shipment, on_hold)
                    $cannotUpdateStatuses = ['shipped', 'cancelled'];
                    if (in_array($orderStatus, $cannotUpdateStatuses)) {
                        Log::warning('Cannot update order - status is ' . $orderStatus, [
                            'order_key' => $order->order_key,
                            'shipstation_order_id' => $existingOrder['orderId'],
                            'note' => 'ShipStation API does not allow updating shipped or cancelled orders',
                        ]);
                        
                        return [
                            'success' => true,
                            'order_id' => $existingOrder['orderId'],
                            'action' => 'skipped_shipped',
                            'message' => "Order already {$orderStatus} - cannot be updated via API",
                        ];
                    }
                    
                    return $this->updateExistingOrder($order, $existingOrder);
                } elseif ($onlyUpdateExisting) {
                    Log::info('Order not in ShipStation yet, skipping (only_update_existing mode)', [
                        'order_key' => $order->order_key,
                        'note' => 'Waiting for ShipStation auto-sync to create it first',
                    ]);
                    
                    return [
                        'success' => true,
                        'order_id' => null,
                        'action' => 'waiting_for_shipstation',
                        'message' => 'Order not in ShipStation yet, will update after auto-sync',
                    ];
                }
            } elseif ($syncStrategy === 'create_only' && $order->shipstation_order_id) {
                Log::info('Order already pushed, skipping (create_only mode)', [
                    'order_key' => $order->order_key,
                ]);
                
                return [
                    'success' => true,
                    'order_id' => $order->shipstation_order_id,
                    'action' => 'skipped',
                    'message' => 'Order already exists',
                ];
            }

            // Create new order
            return $this->createNewOrder($order);

        } catch (\Exception $e) {
            Log::error('ShipStation API exception', [
                'order_key' => $order->order_key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ShipStationSyncLog::logError(
                $order->order_number,
                $e->getMessage(),
                ['trace' => $e->getTraceAsString()]
            );

            return [
                'success' => false,
                'order_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create new order in ShipStation
     */
    protected function createNewOrder(ShipStationOrder $order): array
    {
        $payload = $this->buildOrderPayload($order);
        
        if ($this->logApiRequests) {
            Log::info('ShipStation API Request (CREATE)', [
                'endpoint' => '/orders/createorder',
                'order_key' => $order->order_key,
                'payload' => $payload,
            ]);
        }

        $startTime = microtime(true);
        
        $response = Http::withHeaders([
            'Authorization' => $this->authHeader,
            'Content-Type' => 'application/json',
        ])
        ->timeout(30)
        ->post($this->baseUrl . '/orders/createorder', $payload);

        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        $statusCode = $response->status();
        $responseBody = $response->json();

        if ($this->logApiResponses) {
            Log::info('ShipStation API Response (CREATE)', [
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime,
                'body' => $responseBody,
            ]);
        }

        if ($response->successful()) {
            $shipstationOrderId = $responseBody['orderId'] ?? null;
            
            ShipStationSyncLog::logPush(
                $order->id,
                $order->order_number,
                $statusCode,
                $responseTime,
                true
            );

            return [
                'success' => true,
                'order_id' => $shipstationOrderId,
                'order_number' => $responseBody['orderNumber'] ?? null,
                'order_key' => $responseBody['orderKey'] ?? null,
                'action' => 'created',
                'error' => null,
            ];
        } else {
            // Get full error details
            $rawBody = $response->body();
            $errorMessage = $responseBody['message'] ?? $rawBody;
            
            // Log FULL response for debugging
            Log::error('ShipStation API error (CREATE) - FULL DETAILS', [
                'order_key' => $order->order_key,
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime,
                'error_message' => $errorMessage,
                'response_body_json' => $responseBody,
                'response_body_raw' => $rawBody,
                'request_payload' => $payload, // Include what we sent
            ]);
            
            ShipStationSyncLog::logPush(
                $order->id,
                $order->order_number,
                $statusCode,
                $responseTime,
                false,
                $errorMessage
            );

            return [
                'success' => false,
                'order_id' => null,
                'action' => 'create_failed',
                'error' => $errorMessage,
            ];
        }
    }

    /**
     * Update existing order in ShipStation
     */
    protected function updateExistingOrder(ShipStationOrder $order, array $existingOrder): array
    {
        if (!config('shipstation.update_existing', true)) {
            Log::info('Update disabled, skipping existing order', [
                'order_key' => $order->order_key,
            ]);
            
            return [
                'success' => true,
                'order_id' => $existingOrder['orderId'],
                'action' => 'skipped',
                'message' => 'Updates disabled in config',
            ];
        }

        $shipstationOrderId = $existingOrder['orderId'];
        $payload = $this->buildOrderPayload($order);
        
        // Add orderId to payload for update
        $payload['orderId'] = $shipstationOrderId;
        
        if ($this->logApiRequests) {
            Log::info('ShipStation API Request (UPDATE)', [
                'endpoint' => '/orders/createorder',
                'order_key' => $order->order_key,
                'shipstation_order_id' => $shipstationOrderId,
                'payload' => $payload,
            ]);
        }

        $startTime = microtime(true);
        
        // ShipStation uses same endpoint for create/update - orderId presence determines action
        $response = Http::withHeaders([
            'Authorization' => $this->authHeader,
            'Content-Type' => 'application/json',
        ])
        ->timeout(30)
        ->post($this->baseUrl . '/orders/createorder', $payload);

        $responseTime = (int)((microtime(true) - $startTime) * 1000);
        $statusCode = $response->status();
        $responseBody = $response->json();

        if ($this->logApiResponses) {
            Log::info('ShipStation API Response (UPDATE)', [
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime,
                'body' => $responseBody,
            ]);
        }

        if ($response->successful()) {
            ShipStationSyncLog::create([
                'shipstation_order_id' => $order->id,
                'shopify_order_number' => $order->order_number,
                'action' => 'update',
                'status' => 'success',
                'message' => 'Order updated in ShipStation',
                'api_status_code' => $statusCode,
                'api_response_time_ms' => $responseTime,
            ]);

            return [
                'success' => true,
                'order_id' => $shipstationOrderId,
                'action' => 'updated',
                'error' => null,
            ];
        } else {
            // Get full error details
            $rawBody = $response->body();
            $errorMessage = $responseBody['message'] ?? $rawBody;
            
            // Log FULL response for debugging
            Log::error('ShipStation API error (UPDATE) - FULL DETAILS', [
                'order_key' => $order->order_key,
                'shipstation_order_id' => $shipstationOrderId,
                'status_code' => $statusCode,
                'response_time_ms' => $responseTime,
                'error_message' => $errorMessage,
                'response_body_json' => $responseBody,
                'response_body_raw' => $rawBody,
                'request_payload' => $payload, // Include what we sent
            ]);
            
            ShipStationSyncLog::create([
                'shipstation_order_id' => $order->id,
                'shopify_order_number' => $order->order_number,
                'action' => 'update',
                'status' => 'failed',
                'message' => 'Failed to update order in ShipStation',
                'api_status_code' => $statusCode,
                'api_response_time_ms' => $responseTime,
                'error_message' => $errorMessage,
                'metadata' => [
                    'response_body' => $responseBody,
                    'raw_body' => $rawBody,
                ],
            ]);

            return [
                'success' => false,
                'order_id' => null,
                'action' => 'update_failed',
                'error' => $errorMessage,
            ];
        }
    }

    /**
     * Calculate proper price for bundle components by backtracking to original subscription
     * 
     * @param ShipStationOrder $order
     * @param string $componentSku
     * @return float
     */
    protected function calculateBundleComponentPrice(ShipStationOrder $order, string $componentSku): float
    {
        // Get bundle component mappings from config
        $bundleMappings = config('shipstation.bundle_components', []);
        
        // Find which bundle this component belongs to
        $parentBundle = null;
        foreach ($bundleMappings as $bundleSku => $components) {
            if (in_array($componentSku, $components)) {
                $parentBundle = $bundleSku;
                break;
            }
        }
        
        if (!$parentBundle) {
            return 0.0;
        }
        
        // Find the bundle item in this order
        $bundleItem = $order->lineItems->firstWhere('sku', $parentBundle);
        
        if (!$bundleItem) {
            return 0.0;
        }
        
        // Get the number of components in this bundle
        $componentCount = count($bundleMappings[$parentBundle]);
        
        if ($componentCount <= 0) {
            return 0.0;
        }
        
        // Check if this is a SKIO subscription order (no unit price)
        $isSubscriptionOrder = $bundleItem->unit_price <= 0;
        
        if ($isSubscriptionOrder) {
            // Backtrack to find the original subscription order
            $originalPrice = $this->findOriginalSubscriptionPrice($order, $parentBundle);
            
            if ($originalPrice > 0) {
                $pricePerComponent = $originalPrice / $componentCount;
                
                Log::info('Found original subscription price for bundle component', [
                    'component_sku' => $componentSku,
                    'bundle_sku' => $parentBundle,
                    'current_order' => $order->order_number,
                    'customer_email' => $order->customer_email,
                    'original_bundle_price' => $originalPrice,
                    'component_price' => $pricePerComponent,
                    'source' => 'backtracked_from_subscription',
                ]);
                
                return round($pricePerComponent, 2);
            }
        }
        
        // Fallback: Use current bundle price (for non-subscription orders)
        $bundleOriginalPrice = $bundleItem->unit_price;
        $pricePerComponent = $bundleOriginalPrice / $componentCount;
        
        Log::info('Using current bundle price for component', [
            'component_sku' => $componentSku,
            'bundle_sku' => $parentBundle,
            'bundle_price' => $bundleOriginalPrice,
            'component_price' => $pricePerComponent,
            'source' => 'current_order',
        ]);
        
        return round($pricePerComponent, 2);
    }

    /**
     * Find the original subscription price by backtracking customer order history
     * 
     * @param ShipStationOrder $currentOrder
     * @param string $bundleSku
     * @return float
     */
    protected function findOriginalSubscriptionPrice(ShipStationOrder $currentOrder, string $bundleSku): float
    {
        $customerEmail = $currentOrder->customer_email;
        
        if (!$customerEmail) {
            Log::warning('No customer email found for subscription price lookup', [
                'order_number' => $currentOrder->order_number,
                'bundle_sku' => $bundleSku,
            ]);
            return 0.0;
        }
        
        // Look for previous orders from the same customer with this bundle SKU
        $previousOrders = \App\Models\ShipStationOrder::where('customer_email', $customerEmail)
            ->where('order_number', '!=', $currentOrder->order_number) // Exclude current order
            ->where('created_at', '<', $currentOrder->created_at) // Only older orders
            ->orderBy('created_at', 'desc') // Most recent first
            ->get();
        
        foreach ($previousOrders as $previousOrder) {
            // Check if this order has the bundle with a proper price
            $bundleItem = $previousOrder->lineItems->firstWhere('sku', $bundleSku);
            
            if ($bundleItem && $bundleItem->unit_price > 0) {
                Log::info('Found original subscription order with bundle price', [
                    'current_order' => $currentOrder->order_number,
                    'original_order' => $previousOrder->order_number,
                    'customer_email' => $customerEmail,
                    'bundle_sku' => $bundleSku,
                    'original_price' => $bundleItem->unit_price,
                    'order_date' => $previousOrder->created_at,
                ]);
                
                return (float)$bundleItem->unit_price;
            }
        }
        
        // If no previous ShipStation orders found, check Shopify orders
        $shopifyOrders = \App\Models\ShopifyOrder::where('email_address', $customerEmail)
            ->orderBy('order_date', 'desc')
            ->get();
        
        foreach ($shopifyOrders as $shopifyOrder) {
            // Parse the raw JSON to find bundle pricing
            $rawJson = $shopifyOrder->raw_json ?? null;
            
            if ($rawJson && isset($rawJson['line_items'])) {
                foreach ($rawJson['line_items'] as $lineItem) {
                    if (($lineItem['sku'] ?? '') === $bundleSku && ($lineItem['price'] ?? 0) > 0) {
                        $price = (float)$lineItem['price'];
                        
                        Log::info('Found original subscription price from Shopify order', [
                            'current_order' => $currentOrder->order_number,
                            'shopify_order' => $shopifyOrder->order_number,
                            'customer_email' => $customerEmail,
                            'bundle_sku' => $bundleSku,
                            'original_price' => $price,
                            'order_date' => $shopifyOrder->order_date,
                        ]);
                        
                        return $price;
                    }
                }
            }
        }
        
        Log::warning('No original subscription price found', [
            'current_order' => $currentOrder->order_number,
            'customer_email' => $customerEmail,
            'bundle_sku' => $bundleSku,
        ]);
        
        return 0.0;
    }

    /**
     * Build ShipStation order payload
     * 
     * @param ShipStationOrder $order
     * @return array
     */
    protected function buildOrderPayload(ShipStationOrder $order): array
    {
        $imageOverrides = config('shipstation.sku_image_overrides', []);
        
        $lineItems = $order->lineItems->map(function ($item) use ($imageOverrides, $order) {
            // Check for image URL override
            $imageUrl = $item->image_url;
            if (isset($imageOverrides[$item->sku])) {
                $imageUrl = $imageOverrides[$item->sku];
                
                Log::info('Applied image URL override for SKU in payload build', [
                    'sku' => $item->sku,
                    'image_url' => $imageUrl,
                ]);
            }
            
            // Calculate proper unit price for bundle components
            $originalPrice = (float)$item->unit_price;
            $unitPrice = $originalPrice;
            
            // If this is a bundle component (not a bundle itself) and has $0 price,
            // calculate the price based on bundle pricing
            if ($unitPrice == 0 && !str_starts_with($item->sku, 'BUND-')) {
                $calculatedPrice = $this->calculateBundleComponentPrice($order, $item->sku);
                
                if ($calculatedPrice > 0) {
                    $unitPrice = $calculatedPrice;
                    
                    // Log to both main log and pricing log
                    $pricingLogData = [
                        'timestamp' => now()->toDateTimeString(),
                        'order_number' => $order->order_number,
                        'sku' => $item->sku,
                        'item_name' => $item->name,
                        'quantity' => $item->quantity,
                        'original_unit_price' => $originalPrice,
                        'calculated_unit_price' => $unitPrice,
                        'price_change' => $unitPrice - $originalPrice,
                        'total_value_change' => ($unitPrice - $originalPrice) * $item->quantity,
                        'pricing_method' => 'bundle_component_backtracking',
                        'customer_email' => $order->customer_email,
                    ];
                    
                    Log::channel('pricing')->info('PRICING UPDATE', $pricingLogData);
                    
                    Log::info('Applied bundle component pricing', [
                        'sku' => $item->sku,
                        'original_price' => $originalPrice,
                        'calculated_price' => $unitPrice,
                        'change' => '+$' . number_format($unitPrice - $originalPrice, 2),
                    ]);
                }
            }
            
            $itemData = [
                'lineItemKey' => null, // Optional, can be used for order modifications
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => (int)$item->quantity,
                'unitPrice' => $unitPrice,
                'imageUrl' => $imageUrl,
                'taxAmount' => null,
                'shippingAmount' => null,
                'warehouseLocation' => null,
                'options' => [],
                'productId' => null,
                'fulfillmentSku' => $item->sku,
                'adjustment' => false,
                'upc' => null,
            ];
            
            // Add weight if available (convert kg to ounces: 1 kg = 35.274 oz)
            if ($item->weight && $item->weight > 0) {
                $weightInOunces = $item->weight_unit === 'kg' 
                    ? $item->weight * 35.274 
                    : (float)$item->weight;
                
                $itemData['weight'] = [
                    'value' => round($weightInOunces, 2),
                    'units' => 'ounces',
                ];
            } else {
                $itemData['weight'] = [
                    'value' => 1,
                    'units' => 'ounces',
                ];
            }
            
            // Add consolidation info to options if item was consolidated
            if ($item->is_consolidated) {
                $itemData['options'][] = [
                    'name' => 'Consolidated',
                    'value' => "Merged from {$item->original_line_count} line items",
                ];
            }
            
            return $itemData;
        })->values()->toArray();

        $payload = [
            'orderNumber' => (string)$order->order_number,
            'orderKey' => $order->order_key,
            'orderDate' => $order->created_at->toIso8601String(),
            'paymentDate' => $order->created_at->toIso8601String(),
            'orderStatus' => 'awaiting_shipment',
            'customerUsername' => $order->customer_email ?: null,
            'customerEmail' => $order->customer_email ?: null,
            'billTo' => [
                'name' => $order->customer_name ?: 'Customer',
                'company' => $order->ship_company,
                'street1' => $order->ship_street1,
                'street2' => $order->ship_street2,
                'street3' => null,
                'city' => $order->ship_city,
                'state' => $order->ship_state,
                'postalCode' => $order->ship_postal_code,
                'country' => $order->ship_country ?: 'US',
                'phone' => $order->ship_phone,
                'residential' => null,
            ],
            'shipTo' => [
                'name' => $order->ship_name ?: $order->customer_name ?: 'Customer',
                'company' => $order->ship_company,
                'street1' => $order->ship_street1 ?: 'No Address',
                'street2' => $order->ship_street2,
                'street3' => null,
                'city' => $order->ship_city ?: 'Unknown',
                'state' => $order->ship_state ?: 'XX',
                'postalCode' => $order->ship_postal_code ?: '00000',
                'country' => $order->ship_country ?: 'US',
                'phone' => $order->ship_phone,
                'residential' => true,
            ],
            'items' => $lineItems,
            'amountPaid' => (float)$order->order_total,
            'taxAmount' => (float)$order->tax_amount,
            'shippingAmount' => (float)$order->shipping_amount,
            'customerNotes' => null,
            'internalNotes' => $this->buildInternalNotes($order),
            'gift' => false,
            'giftMessage' => null,
            'paymentMethod' => 'Shopify',
            'requestedShippingService' => config('shipstation.default_service_code'),
            'carrierCode' => config('shipstation.default_carrier_code'),
            'serviceCode' => config('shipstation.default_service_code'),
            'packageCode' => 'package',
            'confirmation' => 'none',
            'shipDate' => now()->toDateString(),
        ];

        return $payload;
    }

    /**
     * Build internal notes with consolidation info
     */
    protected function buildInternalNotes(ShipStationOrder $order): ?string
    {
        $consolidatedItems = $order->lineItems()->where('is_consolidated', true)->count();
        
        if ($consolidatedItems === 0) {
            return 'Imported from Shopify';
        }

        $notes = "Imported from Shopify\n";
        $notes .= "SKU Consolidation: {$consolidatedItems} item(s) merged\n";
        $notes .= "Original line items: " . count($order->original_line_items ?? []);

        return $notes;
    }

    /**
     * Update order in ShipStation
     */
    public function updateOrder(ShipStationOrder $order): array
    {
        // Implementation for updating existing orders if needed
        // ShipStation API endpoint: PUT /orders/{orderId}
        
        return [
            'success' => false,
            'error' => 'Update not implemented yet',
        ];
    }

    /**
     * Get order from ShipStation by order ID
     */
    public function getOrder(string $orderId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->authHeader,
            ])
            ->timeout(30)
            ->get($this->baseUrl . '/orders/' . $orderId);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to get order from ShipStation', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Error getting order from ShipStation', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update order items in ShipStation
     */
    public function updateOrderItems(string $orderId, array $items): array
    {
        try {
            $payload = [
                'items' => $items,
            ];

            $response = Http::withHeaders([
                'Authorization' => $this->authHeader,
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->put($this->baseUrl . '/orders/' . $orderId, $payload);

            if ($response->successful()) {
                Log::info('Successfully updated order items in ShipStation', [
                    'order_id' => $orderId,
                    'items_count' => count($items),
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error('Failed to update order items in ShipStation', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to update order items',
                'status' => $response->status(),
                'response' => $response->body(),
            ];

        } catch (\Exception $e) {
            Log::error('Error updating order items in ShipStation', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get order from ShipStation by order key
     */
    public function getOrderByKey(string $orderKey): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->authHeader,
            ])
            ->timeout(30)
            ->get($this->baseUrl . '/orders', [
                'orderKey' => $orderKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['orders'][0] ?? null;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to fetch order from ShipStation', [
                'order_key' => $orderKey,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->authHeader,
            ])
            ->timeout(10)
            ->get($this->baseUrl . '/accounts/listtags');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                ];
            }

            return [
                'success' => false,
                'message' => 'API returned error: ' . $response->status(),
                'error' => $response->body(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get rate limit status
     */
    public function getRateLimitStatus(): array
    {
        // ShipStation returns rate limit info in response headers
        // X-Rate-Limit-Limit: 40
        // X-Rate-Limit-Remaining: 39
        // X-Rate-Limit-Reset: 1234567890
        
        return [
            'limit' => config('shipstation.rate_limit.requests_per_minute'),
            'message' => 'Rate limit: 40 requests per minute',
        ];
    }
}

