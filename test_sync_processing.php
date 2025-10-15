<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🚀 SHIPSTATION SYNC PROCESSING TEST\n";
echo "====================================\n\n";

// Check queue configuration
$queueConnection = config('shipstation.queue.connection');
echo "Queue Connection: {$queueConnection}\n";

if ($queueConnection === 'sync') {
    echo "✅ SYNC MODE: Jobs will process IMMEDIATELY (no delay)\n";
} else {
    echo "⚠️  ASYNC MODE: Jobs will be queued (may have delays)\n";
}

echo "\n";

// Check auto-push setting
$autoPush = config('shipstation.auto_push');
echo "Auto Push: " . ($autoPush ? 'ENABLED ✅' : 'DISABLED ❌') . "\n";

if (!$autoPush) {
    echo "   ⚠️  Auto-push is disabled. Orders will be consolidated but not pushed.\n";
}

echo "\n";

// Check webhook delay
$webhookDelay = config('shipstation.webhook_delay_minutes', 0);
echo "Webhook Delay: {$webhookDelay} minutes\n";

if ($webhookDelay > 0) {
    echo "   ⚠️  There's a {$webhookDelay} minute delay before processing.\n";
} else {
    echo "   ✅ No delay - immediate processing!\n";
}

echo "\n";

// Test queue driver
echo "Testing Queue Driver...\n";
$queueManager = app('queue');
$connection = $queueManager->connection($queueConnection);
echo "Queue Driver: " . get_class($connection) . "\n";

if (get_class($connection) === 'Illuminate\Queue\SyncQueue') {
    echo "✅ SYNC QUEUE: Jobs execute immediately in the same process!\n";
} else {
    echo "⚠️  ASYNC QUEUE: Jobs are queued for later processing.\n";
}

echo "\n";
echo "📊 PROCESSING SPEED SUMMARY:\n";
echo "============================\n";

$speed = 'SLOW ⏰';
$description = 'Orders may take several minutes to process';

if ($queueConnection === 'sync' && $webhookDelay === 0) {
    $speed = 'INSTANT ⚡';
    $description = 'Orders process immediately when webhook arrives!';
} elseif ($queueConnection === 'sync' && $webhookDelay > 0) {
    $speed = "DELAYED ({$webhookDelay} min) ⏱️";
    $description = "Orders wait {$webhookDelay} minutes before processing";
} elseif ($queueConnection !== 'sync') {
    $speed = 'QUEUED 📝';
    $description = 'Orders wait for queue worker to process them';
}

echo "Speed: {$speed}\n";
echo "Description: {$description}\n\n";

if ($queueConnection === 'sync' && $webhookDelay === 0 && $autoPush) {
    echo "🎉 PERFECT SETUP FOR INSTANT PROCESSING! 🎉\n";
    echo "Orders will be consolidated and pushed to ShipStation immediately!\n";
} else {
    echo "⚠️  RECOMMENDATIONS FOR INSTANT PROCESSING:\n";
    echo "==========================================\n";
    
    if ($queueConnection !== 'sync') {
        echo "1. Set SHIPSTATION_QUEUE_CONNECTION=sync in .env\n";
    }
    
    if ($webhookDelay > 0) {
        echo "2. Set SHIPSTATION_WEBHOOK_DELAY_MINUTES=0 in config\n";
    }
    
    if (!$autoPush) {
        echo "3. Set SHIPSTATION_AUTO_PUSH=true in .env\n";
    }
}

