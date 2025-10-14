<?php

namespace App\Services;

use App\Models\ShipStationProcessedOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShipStationPullService
{
    protected $apiKey;
    protected $apiSecret;
    protected $baseUrl;
    protected $authHeader;

    public function __construct()
    {
        $this->apiKey = config('shipstation.api_key');
        $this->apiSecret = config('shipstation.api_secret');
        $this->baseUrl = rtrim(config('shipstation.base_url', 'https://ssapi.shipstation.com'), '/');
        $this->authHeader = 'Basic ' . base64_encode($this->apiKey . ':' . $this->apiSecret);
    }

    /**
     * Pull new orders from ShipStation that haven't been processed yet
     * 
     * @param int $minutes Look back this many minutes for new orders
     * @return array Array of orders
     */
    public function pullNewOrders(int $minutes = 60): array
    {
        try {
            $modifyDateStart = now()->subMinutes($minutes)->toIso8601String();
            
            Log::info('Pulling orders from ShipStation', [
                'since' => $modifyDateStart,
                'minutes' => $minutes,
            ]);

            $response = Http::withHeaders([
                'Authorization' => $this->authHeader,
            ])
            ->timeout(30)
            ->get($this->baseUrl . '/orders', [
                'modifyDateStart' => $modifyDateStart,
                'orderStatus' => 'awaiting_shipment', // Only orders that can be updated
                'pageSize' => 100,
                'page' => 1,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to pull orders from ShipStation', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];

            Log::info('Pulled orders from ShipStation', [
                'total' => count($orders),
                'page' => $data['page'] ?? 1,
                'pages' => $data['pages'] ?? 1,
            ]);

            return $orders;

        } catch (\Exception $e) {
            Log::error('Exception pulling orders from ShipStation', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Check if order has duplicate SKUs
     */
    public function hasDuplicateSKUs(array $order): bool
    {
        $items = $order['items'] ?? [];
        
        if (count($items) < 2) {
            return false;
        }

        // Extract SKUs and filter out non-string/non-integer values
        $skus = array_column($items, 'sku');
        $skus = array_filter($skus, function($sku) {
            return is_string($sku) || is_int($sku);
        });
        
        if (empty($skus)) {
            return false;
        }
        
        $skuCounts = array_count_values($skus);
        
        // Check if any SKU appears more than once
        foreach ($skuCounts as $count) {
            if ($count > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Consolidate duplicate SKUs in an order's items
     */
    public function consolidateItems(array $items, ?string $orderId = null, ?string $orderNumber = null): array
    {
        // Step 1: Remove bundle SKUs (anything starting with BUND-)
        $items = array_filter($items, function($item) use ($orderId, $orderNumber) {
            $sku = $item['sku'] ?? '';
            $isBundle = str_starts_with($sku, 'BUND-');
            
            if ($isBundle) {
                $logData = [
                    'bundle_sku' => $sku,
                    'item_name' => $item['name'] ?? 'Unknown',
                ];
                
                if ($orderId) {
                    $logData['order_id'] = $orderId;
                }
                
                if ($orderNumber) {
                    $logData['order_number'] = $orderNumber;
                }
                
                Log::info('Removed bundle SKU from order', $logData);
            }
            
            return !$isBundle; // Keep only non-bundle items
        });

        $grouped = [];

        // Group by SKU
        foreach ($items as $item) {
            $sku = $item['sku'] ?? 'NO-SKU';
            
            if (!isset($grouped[$sku])) {
                $grouped[$sku] = [];
            }
            
            $grouped[$sku][] = $item;
        }

        // Consolidate each group
        $consolidated = [];
        foreach ($grouped as $sku => $itemGroup) {
            if (count($itemGroup) === 1) {
                // No consolidation needed
                $item = $itemGroup[0];
                
                // Apply image URL override if configured
                $item = $this->applyImageUrlOverride($item);
                
                $consolidated[] = $item;
            } else {
                // Multiple items with same SKU - consolidate
                $totalQuantity = 0;
                $totalValue = 0;
                $first = $itemGroup[0];

                foreach ($itemGroup as $item) {
                    $qty = intval($item['quantity'] ?? 1);
                    $price = floatval($item['unitPrice'] ?? 0);
                    
                    $totalQuantity += $qty;
                    $totalValue += ($qty * $price);
                }

                $avgPrice = $totalQuantity > 0 ? $totalValue / $totalQuantity : 0;

                // Use first item as template, update quantity and price
                $consolidatedItem = array_merge($first, [
                    'quantity' => $totalQuantity,
                    'unitPrice' => round($avgPrice, 2),
                    'options' => array_merge($first['options'] ?? [], [
                        [
                            'name' => 'Consolidated',
                            'value' => 'Merged from ' . count($itemGroup) . ' line items',
                        ]
                    ]),
                ]);
                
                // Apply image URL override if configured
                $consolidatedItem = $this->applyImageUrlOverride($consolidatedItem);
                
                $consolidated[] = $consolidatedItem;
            }
        }

        return $consolidated;
    }

    /**
     * Apply image URL override for specific SKUs
     */
    protected function applyImageUrlOverride(array $item): array
    {
        $sku = $item['sku'] ?? null;
        
        if (!$sku) {
            return $item;
        }

        $imageOverrides = config('shipstation.sku_image_overrides', []);
        
        if (isset($imageOverrides[$sku])) {
            $item['imageUrl'] = $imageOverrides[$sku];
            
            Log::info('Applied image URL override for SKU', [
                'sku' => $sku,
                'image_url' => $imageOverrides[$sku],
            ]);
        }

        return $item;
    }

    /**
     * Get order details by order ID
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

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get order from ShipStation', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

