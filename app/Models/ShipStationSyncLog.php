<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipStationSyncLog extends Model
{
    use HasFactory;

    protected $table = 'shipstation_sync_logs';

    protected $fillable = [
        'shipstation_order_id',
        'shopify_order_number',
        'action',
        'status',
        'message',
        'metadata',
        'items_before',
        'items_after',
        'api_response_time_ms',
        'api_status_code',
        'error_message',
        'error_trace',
    ];

    protected $casts = [
        'metadata' => 'array',
        'error_trace' => 'array',
        'items_before' => 'integer',
        'items_after' => 'integer',
        'api_response_time_ms' => 'integer',
    ];

    /**
     * Get the order that owns the log
     */
    public function order()
    {
        return $this->belongsTo(ShipStationOrder::class, 'shipstation_order_id');
    }

    /**
     * Create a consolidation log entry
     */
    public static function logConsolidation(
        $shipstationOrderId,
        $shopifyOrderNumber,
        $itemsBefore,
        $itemsAfter,
        $metadata = []
    ) {
        return static::create([
            'shipstation_order_id' => $shipstationOrderId,
            'shopify_order_number' => $shopifyOrderNumber,
            'action' => 'consolidate',
            'status' => 'success',
            'message' => "Consolidated {$itemsBefore} line items into {$itemsAfter}",
            'items_before' => $itemsBefore,
            'items_after' => $itemsAfter,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a push log entry
     */
    public static function logPush(
        $shipstationOrderId,
        $shopifyOrderNumber,
        $statusCode,
        $responseTime,
        $success = true,
        $errorMessage = null
    ) {
        return static::create([
            'shipstation_order_id' => $shipstationOrderId,
            'shopify_order_number' => $shopifyOrderNumber,
            'action' => 'push',
            'status' => $success ? 'success' : 'failed',
            'message' => $success ? 'Order pushed to ShipStation successfully' : 'Failed to push order',
            'api_status_code' => $statusCode,
            'api_response_time_ms' => $responseTime,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Create an error log entry
     */
    public static function logError(
        $shopifyOrderNumber,
        $errorMessage,
        $errorTrace = []
    ) {
        return static::create([
            'shopify_order_number' => $shopifyOrderNumber,
            'action' => 'error',
            'status' => 'failed',
            'message' => 'Error during processing',
            'error_message' => $errorMessage,
            'error_trace' => $errorTrace,
        ]);
    }

    /**
     * Scope to get recent errors
     */
    public function scopeRecentErrors($query, $hours = 24)
    {
        return $query->where('status', 'failed')
                     ->where('created_at', '>=', now()->subHours($hours))
                     ->orderBy('created_at', 'desc');
    }
}

