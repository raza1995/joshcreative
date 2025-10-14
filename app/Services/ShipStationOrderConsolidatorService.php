<?php

namespace App\Services;

use App\Models\ShopifyOrder;
use App\Models\ShipStationOrder;
use App\Models\ShipStationLineItem;
use App\Models\ShipStationSyncLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ShipStationOrderConsolidatorService
{
    protected $priceStrategy;
    protected $consolidateEnabled;

    public function __construct()
    {
        $this->priceStrategy = config('shipstation.price_strategy', 'weighted_average');
        $this->consolidateEnabled = config('shipstation.consolidate_skus', true);
    }

    /**
     * Consolidate a Shopify order for ShipStation
     * 
     * @param ShopifyOrder $shopifyOrder
     * @return ShipStationOrder|null
     */
    public function consolidateOrder(ShopifyOrder $shopifyOrder)
    {
        try {
            DB::beginTransaction();

            // Parse Shopify order data
            $orderData = $this->parseShopifyOrder($shopifyOrder);
            
            if (!$orderData) {
                Log::warning('Unable to parse Shopify order', ['order_id' => $shopifyOrder->id]);
                return null;
            }

            // Validate order
            if (!$this->validateOrder($orderData)) {
                Log::warning('Order validation failed', ['order_number' => $orderData['order_number']]);
                return null;
            }

            // Note: Duplicate handling is now done by updateOrCreate in createShipStationOrder

            // Create ShipStation order record
            $shipstationOrder = $this->createShipStationOrder($shopifyOrder, $orderData);

            // Consolidate line items
            $lineItemsData = $orderData['line_items'] ?? [];
            $originalCount = count($lineItemsData);
            
            $consolidatedItems = $this->consolidateEnabled 
                ? $this->consolidateLineItems($lineItemsData)
                : $this->mapLineItemsWithoutConsolidation($lineItemsData);

            // Create line items
            foreach ($consolidatedItems as $itemData) {
                ShipStationLineItem::create(array_merge($itemData, [
                    'shipstation_order_id' => $shipstationOrder->id,
                ]));
            }

            // Update order status
            $shipstationOrder->update([
                'consolidation_status' => 'consolidated',
            ]);

            // Log consolidation
            ShipStationSyncLog::logConsolidation(
                $shipstationOrder->id,
                $orderData['order_number'],
                $originalCount,
                count($consolidatedItems),
                [
                    'strategy' => $this->priceStrategy,
                    'consolidation_enabled' => $this->consolidateEnabled,
                ]
            );

            DB::commit();

            Log::info('Order consolidated successfully', [
                'order_number' => $orderData['order_number'],
                'items_before' => $originalCount,
                'items_after' => count($consolidatedItems),
            ]);

            return $shipstationOrder;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Order consolidation failed', [
                'order_id' => $shopifyOrder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            ShipStationSyncLog::logError(
                $shopifyOrder->order_number,
                $e->getMessage(),
                ['trace' => $e->getTraceAsString()]
            );

            return null;
        }
    }

    /**
     * Parse Shopify order raw JSON
     */
    protected function parseShopifyOrder(ShopifyOrder $shopifyOrder): ?array
    {
        $rawJson = $shopifyOrder->raw_json;
        
        if (is_string($rawJson)) {
            $rawJson = json_decode($rawJson, true);
        }

        if (!is_array($rawJson) || empty($rawJson)) {
            return null;
        }

        return $rawJson;
    }

    /**
     * Validate order meets requirements
     */
    protected function validateOrder(array $orderData): bool
    {
        $config = config('shipstation.validation');
        $orderNumber = $orderData['id'] ?? 'unknown';

        // Check shipping address
        if ($config['require_shipping_address'] ?? true) {
            $shipping = $orderData['shipping_address'] ?? null;
            if (!$shipping) {
                Log::warning('Order validation failed: Missing shipping_address', [
                    'order_number' => $orderNumber,
                ]);
                return false;
            }
            if (empty($shipping['address1'])) {
                Log::warning('Order validation failed: Missing address1', [
                    'order_number' => $orderNumber,
                    'shipping' => $shipping,
                ]);
                return false;
            }
            if (empty($shipping['city'])) {
                Log::warning('Order validation failed: Missing city', [
                    'order_number' => $orderNumber,
                    'shipping' => $shipping,
                ]);
                return false;
            }
        }

        // Check line items
        if ($config['require_line_items'] ?? true) {
            if (empty($orderData['line_items'])) {
                Log::warning('Order validation failed: No line items', [
                    'order_number' => $orderNumber,
                ]);
                return false;
            }
        }

        // Check minimum total
        $minTotal = $config['minimum_order_total'] ?? 0;
        if ($minTotal > 0) {
            $total = floatval($orderData['total_price'] ?? 0);
            if ($total < $minTotal) {
                Log::warning('Order validation failed: Below minimum total', [
                    'order_number' => $orderNumber,
                    'total' => $total,
                    'minimum_required' => $minTotal,
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Create ShipStation order record
     */
    protected function createShipStationOrder(ShopifyOrder $shopifyOrder, array $orderData): ShipStationOrder
    {
        $shipping = $orderData['shipping_address'] ?? [];
        $customer = $orderData['customer'] ?? [];
        
        $orderKey = config('shipstation.order_key_prefix', 'SHOPIFY') . '-' . $orderData['id'];

        $shipstationOrder = ShipStationOrder::updateOrCreate(
            ['order_key' => $orderKey],
            [
                'shopify_order_id' => $shopifyOrder->id,
                'order_number' => $orderData['id'],
                'customer_name' => trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
                'customer_email' => $orderData['email'] ?? $customer['email'] ?? null,
                'order_total' => $orderData['total_price'] ?? 0,
                'shipping_amount' => $orderData['total_shipping_price_set']['shop_money']['amount'] ?? $orderData['shipping_lines'][0]['price'] ?? 0,
                'tax_amount' => $orderData['total_tax'] ?? 0,
                'discount_amount' => $orderData['total_discounts'] ?? 0,
                'ship_name' => $shipping['name'] ?? trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '')),
                'ship_company' => $shipping['company'] ?? null,
                'ship_street1' => $shipping['address1'] ?? null,
                'ship_street2' => $shipping['address2'] ?? null,
                'ship_city' => $shipping['city'] ?? null,
                'ship_state' => $shipping['province_code'] ?? $shipping['province'] ?? null,
                'ship_postal_code' => $shipping['zip'] ?? null,
                'ship_country' => $shipping['country_code'] ?? $shipping['country'] ?? 'US',
                'ship_phone' => $shipping['phone'] ?? $customer['phone'] ?? null,
                'consolidation_status' => 'pending',
                'original_line_items' => $orderData['line_items'] ?? [],
                'shopify_raw' => $orderData,
            ]
        );

        // Log whether we created or updated
        $wasRecentlyCreated = $shipstationOrder->wasRecentlyCreated;
        Log::info('ShipStation order ' . ($wasRecentlyCreated ? 'created' : 'updated'), [
            'order_key' => $orderKey,
            'order_number' => $orderData['id'],
            'action' => $wasRecentlyCreated ? 'created' : 'updated',
        ]);

        return $shipstationOrder;
    }

    /**
     * Consolidate line items by SKU
     */
    protected function consolidateLineItems(array $lineItems): array
    {
        $grouped = [];

        // Group by SKU
        foreach ($lineItems as $item) {
            $sku = $item['sku'] ?? $item['variant_id'] ?? 'NO-SKU-' . ($item['id'] ?? uniqid());
            
            if (!isset($grouped[$sku])) {
                $grouped[$sku] = [];
            }
            
            $grouped[$sku][] = $item;
        }

        // Consolidate each SKU group
        $consolidated = [];
        foreach ($grouped as $sku => $items) {
            if (count($items) === 1) {
                // Single item, no consolidation needed
                $consolidated[] = $this->mapSingleLineItem($items[0]);
            } else {
                // Multiple items with same SKU - consolidate
                $consolidated[] = $this->mergeLineItems($sku, $items);
            }
        }

        return $consolidated;
    }

    /**
     * Map line items without consolidation
     */
    protected function mapLineItemsWithoutConsolidation(array $lineItems): array
    {
        return array_map(function ($item) {
            return $this->mapSingleLineItem($item);
        }, $lineItems);
    }

    /**
     * Map a single line item
     */
    protected function mapSingleLineItem(array $item): array
    {
        $quantity = intval($item['quantity'] ?? 1);
        $price = floatval($item['price'] ?? 0);
        $discount = floatval($item['total_discount'] ?? 0);

        return [
            'sku' => $item['sku'] ?? $item['variant_id'] ?? 'NO-SKU',
            'name' => $item['title'] ?? $item['name'] ?? 'Unknown Product',
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => $quantity * $price,
            'line_discount' => $discount,
            'is_consolidated' => false,
            'original_line_count' => 1,
            'original_line_ids' => [$item['id'] ?? null],
            'price_breakdown' => [
                [
                    'line_id' => $item['id'] ?? null,
                    'quantity' => $quantity,
                    'price' => $price,
                    'discount' => $discount,
                ]
            ],
            'image_url' => $item['image_url'] ?? null,
            'weight' => $item['grams'] ? $item['grams'] / 1000 : null, // Convert grams to kg
            'weight_unit' => $item['grams'] ? 'kg' : null,
        ];
    }

    /**
     * Merge multiple line items with same SKU
     */
    protected function mergeLineItems(string $sku, array $items): array
    {
        $totalQuantity = 0;
        $totalDiscount = 0;
        $originalLineIds = [];
        $priceBreakdown = [];
        $name = '';
        $imageUrl = null;
        $totalWeight = 0;

        // Collect data from all items
        foreach ($items as $item) {
            $qty = intval($item['quantity'] ?? 1);
            $price = floatval($item['price'] ?? 0);
            $discount = floatval($item['total_discount'] ?? 0);
            
            $totalQuantity += $qty;
            $totalDiscount += $discount;
            $originalLineIds[] = $item['id'] ?? null;
            
            $priceBreakdown[] = [
                'line_id' => $item['id'] ?? null,
                'quantity' => $qty,
                'price' => $price,
                'discount' => $discount,
            ];

            if (empty($name)) {
                $name = $item['title'] ?? $item['name'] ?? 'Unknown Product';
            }

            if (empty($imageUrl) && !empty($item['image_url'])) {
                $imageUrl = $item['image_url'];
            }

            if (!empty($item['grams'])) {
                $totalWeight += floatval($item['grams']) / 1000; // Convert to kg
            }
        }

        // Calculate unit price based on strategy
        $unitPrice = $this->calculateConsolidatedPrice($items);
        $lineTotal = $totalQuantity * $unitPrice;

        return [
            'sku' => $sku,
            'name' => $name,
            'quantity' => $totalQuantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'line_discount' => $totalDiscount,
            'is_consolidated' => true,
            'original_line_count' => count($items),
            'original_line_ids' => $originalLineIds,
            'price_breakdown' => $priceBreakdown,
            'image_url' => $imageUrl,
            'weight' => $totalWeight > 0 ? $totalWeight : null,
            'weight_unit' => $totalWeight > 0 ? 'kg' : null,
        ];
    }

    /**
     * Calculate consolidated price based on strategy
     */
    protected function calculateConsolidatedPrice(array $items): float
    {
        switch ($this->priceStrategy) {
            case 'weighted_average':
                return $this->calculateWeightedAverage($items);
            
            case 'lowest':
                return $this->findLowestPrice($items);
            
            case 'highest':
                return $this->findHighestPrice($items);
            
            case 'first':
                return floatval($items[0]['price'] ?? 0);
            
            default:
                return $this->calculateWeightedAverage($items);
        }
    }

    /**
     * Calculate weighted average price
     */
    protected function calculateWeightedAverage(array $items): float
    {
        $totalQuantity = 0;
        $totalValue = 0;

        foreach ($items as $item) {
            $qty = intval($item['quantity'] ?? 1);
            $price = floatval($item['price'] ?? 0);
            
            $totalQuantity += $qty;
            $totalValue += ($qty * $price);
        }

        if ($totalQuantity === 0) {
            return 0;
        }

        return round($totalValue / $totalQuantity, 2);
    }

    /**
     * Find lowest price
     */
    protected function findLowestPrice(array $items): float
    {
        $prices = array_map(fn($item) => floatval($item['price'] ?? 0), $items);
        return !empty($prices) ? min($prices) : 0;
    }

    /**
     * Find highest price
     */
    protected function findHighestPrice(array $items): float
    {
        $prices = array_map(fn($item) => floatval($item['price'] ?? 0), $items);
        return !empty($prices) ? max($prices) : 0;
    }
}

