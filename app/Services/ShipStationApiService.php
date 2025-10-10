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
     * Build ShipStation order payload
     * 
     * @param ShipStationOrder $order
     * @return array
     */
    protected function buildOrderPayload(ShipStationOrder $order): array
    {
        $imageOverrides = config('shipstation.sku_image_overrides', []);
        
        $lineItems = $order->lineItems->map(function ($item) use ($imageOverrides) {
            // Check for image URL override
            $imageUrl = $item->image_url;
            if (isset($imageOverrides[$item->sku])) {
                $imageUrl = $imageOverrides[$item->sku];
                
                Log::info('Applied image URL override for SKU in payload build', [
                    'sku' => $item->sku,
                    'image_url' => $imageUrl,
                ]);
            }
            
            $itemData = [
                'lineItemKey' => null, // Optional, can be used for order modifications
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => (int)$item->quantity,
                'unitPrice' => (float)$item->unit_price,
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

