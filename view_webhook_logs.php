<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "📊 SHIPSTATION WEBHOOK LOGS\n";
echo "===========================\n\n";

// Get command line arguments
$filter = $argv[1] ?? 'all';
$limit = isset($argv[2]) ? (int)$argv[2] : 10;

echo "Filter: {$filter} | Limit: {$limit}\n\n";

// Build query based on filter
$query = \App\Models\ShipStationWebhookLog::query();

switch ($filter) {
    case 'pending':
        $query->pending();
        break;
    case 'success':
        $query->success();
        break;
    case 'failed':
        $query->failed();
        break;
    case 'order':
        if (isset($argv[2])) {
            $orderNumber = $argv[2];
            $query->byOrderNumber($orderNumber);
            $limit = 100;
            echo "Filtering by Order Number: {$orderNumber}\n\n";
        }
        break;
}

$logs = $query->orderBy('id', 'desc')->limit($limit)->get();

if ($logs->isEmpty()) {
    echo "No webhook logs found.\n";
    exit;
}

echo "Found {$logs->count()} webhook log(s):\n";
echo str_repeat("=", 80) . "\n\n";

foreach ($logs as $log) {
    echo "📝 ID: {$log->id} | Event: {$log->event_type} | Status: {$log->status}\n";
    echo "   Order: {$log->order_number} | Time: {$log->processing_time_ms}ms\n";
    echo "   Created: {$log->created_at}\n";
    
    if ($log->status === 'success' && $log->processing_result) {
        echo "   Result: " . json_encode($log->processing_result) . "\n";
    }
    
    if ($log->status === 'failed' && $log->error_message) {
        echo "   ❌ Error: {$log->error_message}\n";
    }
    
    echo "\n";
}

// Show statistics
echo str_repeat("=", 80) . "\n";
echo "📈 STATISTICS\n";
echo str_repeat("=", 80) . "\n";
$totalLogs = \App\Models\ShipStationWebhookLog::count();
$pendingLogs = \App\Models\ShipStationWebhookLog::pending()->count();
$successLogs = \App\Models\ShipStationWebhookLog::success()->count();
$failedLogs = \App\Models\ShipStationWebhookLog::failed()->count();

echo "Total: {$totalLogs} | Pending: {$pendingLogs} | Success: {$successLogs} | Failed: {$failedLogs}\n\n";

echo "💡 Usage:\n";
echo "   php view_webhook_logs.php [filter] [limit]\n";
echo "   Filters: all, pending, success, failed, order\n";
echo "   Examples:\n";
echo "     php view_webhook_logs.php all 20\n";
echo "     php view_webhook_logs.php failed\n";
echo "     php view_webhook_logs.php order 52944\n";
