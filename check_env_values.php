<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "📋 CURRENT SHIPSTATION ENVIRONMENT CONFIGURATION\n";
echo "=================================================\n\n";

$queueConnection = env('SHIPSTATION_QUEUE_CONNECTION', 'not set');
$autoPush = env('SHIPSTATION_AUTO_PUSH', 'not set');
$webhookDelay = env('SHIPSTATION_WEBHOOK_DELAY_MINUTES', 'not set');

echo "SHIPSTATION_QUEUE_CONNECTION = {$queueConnection}\n";
echo "SHIPSTATION_AUTO_PUSH = {$autoPush}\n";
echo "SHIPSTATION_WEBHOOK_DELAY_MINUTES = {$webhookDelay}\n\n";

echo "✅ WHAT YOU NEED FOR INSTANT PROCESSING:\n";
echo "========================================\n";
echo "SHIPSTATION_QUEUE_CONNECTION = sync\n";
echo "SHIPSTATION_AUTO_PUSH = true\n";
echo "SHIPSTATION_WEBHOOK_DELAY_MINUTES = 0\n\n";

$needsChange = false;

if ($queueConnection !== 'sync') {
    echo "❌ QUEUE_CONNECTION should be 'sync' (currently: {$queueConnection})\n";
    $needsChange = true;
}

if ($autoPush !== 'true' && $autoPush !== true && $autoPush !== '1' && $autoPush !== 1) {
    echo "❌ AUTO_PUSH should be 'true' (currently: {$autoPush})\n";
    $needsChange = true;
}

if ($webhookDelay !== '0' && $webhookDelay !== 0) {
    echo "❌ WEBHOOK_DELAY_MINUTES should be '0' (currently: {$webhookDelay})\n";
    $needsChange = true;
}

if (!$needsChange) {
    echo "\n🎉 PERFECT! All settings are configured for instant processing!\n";
} else {
    echo "\n⚠️  UPDATE REQUIRED: Please update your .env file with the correct values above.\n";
}

