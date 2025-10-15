<?php

echo "🚀 SETTING UP INSTANT SHIPSTATION PROCESSING\n";
echo "============================================\n\n";

$envFile = '.env';

if (!file_exists($envFile)) {
    echo "❌ .env file not found!\n";
    exit(1);
}

$envContents = file_get_contents($envFile);

// Check if settings already exist
$hasQueueConnection = strpos($envContents, 'SHIPSTATION_QUEUE_CONNECTION') !== false;
$hasAutoPush = strpos($envContents, 'SHIPSTATION_AUTO_PUSH') !== false;
$hasWebhookDelay = strpos($envContents, 'SHIPSTATION_WEBHOOK_DELAY_MINUTES') !== false;

echo "Current Configuration:\n";
echo "=====================\n";
echo "SHIPSTATION_QUEUE_CONNECTION: " . ($hasQueueConnection ? "EXISTS" : "NOT SET") . "\n";
echo "SHIPSTATION_AUTO_PUSH: " . ($hasAutoPush ? "EXISTS" : "NOT SET") . "\n";
echo "SHIPSTATION_WEBHOOK_DELAY_MINUTES: " . ($hasWebhookDelay ? "EXISTS" : "NOT SET") . "\n\n";

// Prepare settings to add
$settingsToAdd = [];

if (!$hasQueueConnection) {
    $settingsToAdd[] = "SHIPSTATION_QUEUE_CONNECTION=sync";
}

if (!$hasAutoPush) {
    $settingsToAdd[] = "SHIPSTATION_AUTO_PUSH=true";
}

if (!$hasWebhookDelay) {
    $settingsToAdd[] = "SHIPSTATION_WEBHOOK_DELAY_MINUTES=0";
}

if (empty($settingsToAdd)) {
    echo "✅ All instant processing settings are already configured!\n\n";
    echo "Run this command to verify:\n";
    echo "php test_sync_processing.php\n";
    exit(0);
}

echo "Settings to add:\n";
echo "================\n";
foreach ($settingsToAdd as $setting) {
    echo "  + {$setting}\n";
}

echo "\n";
echo "Do you want to add these settings to .env? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$answer = trim(strtolower($line));
fclose($handle);

if ($answer !== 'yes' && $answer !== 'y') {
    echo "\n❌ Cancelled. No changes made.\n";
    exit(0);
}

// Add settings to .env
$newLines = "\n# ShipStation Instant Processing (Added automatically)\n" . implode("\n", $settingsToAdd) . "\n";

if (file_put_contents($envFile, $envContents . $newLines)) {
    echo "\n✅ Settings added successfully!\n\n";
    
    echo "📋 Next steps:\n";
    echo "=============\n";
    echo "1. Clear config cache:\n";
    echo "   php artisan config:clear\n\n";
    echo "2. Test the configuration:\n";
    echo "   php test_sync_processing.php\n\n";
    echo "3. Test with a real order:\n";
    echo "   php artisan shipstation:sync-from-api --order-id=ORDER_NUMBER\n\n";
    
    echo "🎉 ShipStation will now process orders INSTANTLY!\n";
} else {
    echo "\n❌ Error: Could not write to .env file.\n";
    echo "Please add these lines manually to your .env file:\n\n";
    foreach ($settingsToAdd as $setting) {
        echo "{$setting}\n";
    }
}

