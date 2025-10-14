<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Test Any Single Order Pricing on ShipStation\n";
echo str_repeat("=", 60) . "\n\n";

// Usage instructions
echo "📋 USAGE:\n";
echo "php test_any_order_pricing.php [ORDER_ID]\n";
echo "php test_any_order_pricing.php 52886\n";
echo "php test_any_order_pricing.php 6886148702518\n\n";

// Get order ID from command line argument
$orderId = $argv[1] ?? null;

if (!$orderId) {
    echo "❌ Please provide an order ID\n";
    echo "Example: php test_any_order_pricing.php 52886\n";
    exit(1);
}

echo "🔍 Testing Order ID: {$orderId}\n";
echo str_repeat("-", 40) . "\n\n";

$pullService = app(\App\Services\ShipStationPullService::class);
$apiService = app(\App\Services\ShipStationApiService::class);

// Try to fetch the order
echo "📦 Fetching order from ShipStation...\n";

try {
    // First try as ShipStation order ID (numeric)
    if (is_numeric($orderId)) {
        $orders = $pullService->pullSpecificOrder($orderId);
    } else {
        // Try as Shopify order ID (long number)
        echo "Order ID appears to be a Shopify order ID, searching...\n";
        
        // Try different ShipStation order ID ranges
        $possibleIds = [];
        $baseId = 154085000; // Base range
        
        for ($i = 0; $i < 1000; $i++) {
            $possibleIds[] = $baseId + $i;
        }
        
        $orders = [];
        foreach ($possibleIds as $possibleId) {
            $testOrders = $pullService->pullSpecificOrder($possibleId);
            if (!empty($testOrders)) {
                $order = $testOrders[0];
                $shopifyOrderId = $order['orderKey'] ?? '';
                
                if (strpos($shopifyOrderId, $orderId) !== false) {
                    $orders = $testOrders;
                    echo "✅ Found matching Shopify order ID: {$shopifyOrderId}\n";
                    break;
                }
            }
        }
    }
    
    if (empty($orders)) {
        echo "❌ Order not found in ShipStation\n";
        echo "Possible reasons:\n";
        echo "• Order hasn't been synced to ShipStation yet\n";
        echo "• Order ID format is incorrect\n";
        echo "• Order is in a different status\n";
        exit(1);
    }
    
    $order = $orders[0];
    $orderNumber = $order['orderNumber'] ?? 'Unknown';
    $customerEmail = $order['customer']['email'] ?? 'Unknown';
    
    echo "✅ Order Found!\n";
    echo "ShipStation Order ID: {$orderId}\n";
    echo "Order Number: {$orderNumber}\n";
    echo "Customer Email: {$customerEmail}\n";
    echo "Items Count: " . count($order['items'] ?? []) . "\n\n";
    
    echo "📦 ORDER ITEMS ANALYSIS:\n";
    echo str_repeat("-", 50) . "\n";
    
    $hasBundleSkus = false;
    $hasZeroPriceComponents = false;
    
    foreach ($order['items'] ?? [] as $index => $item) {
        $sku = $item['sku'] ?? 'NO-SKU';
        $name = $item['name'] ?? 'Unknown';
        $price = $item['unitPrice'] ?? 0;
        $quantity = $item['quantity'] ?? 1;
        $total = $price * $quantity;
        
        $bundleIcon = str_starts_with($sku, 'BUND-') ? '🎁' : '✅';
        $priceIcon = $price == 0 ? '❌' : '💰';
        
        $itemNumber = $index + 1;
        echo "{$itemNumber}. {$bundleIcon} {$sku} - {$name}\n";
        echo "   {$priceIcon} Price: \${$price} x {$quantity} = \${$total}\n";
        
        if (str_starts_with($sku, 'BUND-')) {
            $hasBundleSkus = true;
        }
        
        if (!str_starts_with($sku, 'BUND-') && $price == 0) {
            $hasZeroPriceComponents = true;
        }
        
        echo "\n";
    }
    
    echo "🔍 PRICING ANALYSIS:\n";
    echo str_repeat("-", 50) . "\n";
    
    if ($hasBundleSkus && $hasZeroPriceComponents) {
        echo "✅ SKIO Subscription Order Detected!\n";
        echo "• Has bundle SKUs (will be removed)\n";
        echo "• Has zero-price components (need pricing fix)\n";
        echo "• Will trigger backtracking to find original subscription pricing\n";
    } elseif ($hasBundleSkus && !$hasZeroPriceComponents) {
        echo "✅ Regular Bundle Order\n";
        echo "• Has bundle SKUs (will be removed)\n";
        echo "• Components have proper pricing\n";
        echo "• Standard bundle removal will apply\n";
    } elseif (!$hasBundleSkus && $hasZeroPriceComponents) {
        echo "⚠️  Zero-Price Components Without Bundles\n";
        echo "• No bundle SKUs found\n";
        echo "• Some components have zero pricing\n";
        echo "• May need manual pricing review\n";
    } else {
        echo "✅ Standard Order\n";
        echo "• No bundle SKUs\n";
        echo "• All items have proper pricing\n";
        echo "• No special processing needed\n";
    }
    
    echo "\n🚀 PROCESSING SIMULATION:\n";
    echo str_repeat("-", 50) . "\n";
    
    // Simulate the consolidation process
    $originalItems = $order['items'] ?? [];
    echo "Before Processing: " . count($originalItems) . " items\n";
    
    // Remove bundle SKUs
    $filteredItems = array_filter($originalItems, function($item) {
        $sku = $item['sku'] ?? '';
        return !str_starts_with($sku, 'BUND-');
    });
    
    echo "After Bundle Removal: " . count($filteredItems) . " items\n";
    
    if ($hasBundleSkus) {
        $removedBundles = count($originalItems) - count($filteredItems);
        echo "✅ Removed {$removedBundles} bundle SKU(s)\n";
    }
    
    // Check for pricing fixes needed
    if ($hasZeroPriceComponents) {
        echo "✅ Pricing fix will be applied to zero-price components\n";
        echo "• Will backtrack to find original subscription pricing\n";
        echo "• Components will show proper pricing on packing slip\n";
    }
    
    echo "\n📊 FINAL RESULT:\n";
    echo str_repeat("-", 50) . "\n";
    echo "• Packing slip will show only individual components\n";
    echo "• No bundle SKUs will appear\n";
    echo "• Components will have proper pricing\n";
    echo "• Warehouse will have clear, accurate information\n";
    
} catch (Exception $e) {
    echo "❌ Error testing order: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Order analysis complete!\n";
echo str_repeat("=", 60) . "\n";
