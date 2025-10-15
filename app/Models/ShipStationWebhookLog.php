<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipStationWebhookLog extends Model
{
    use HasFactory;

    protected $table = 'shipstation_webhook_logs';

    protected $fillable = [
        'event_type',
        'resource_url',
        'resource_type',
        'order_id',
        'order_number',
        'raw_request',
        'headers',
        'ip_address',
        'user_agent',
        'status',
        'processing_result',
        'error_message',
        'error_trace',
        'processed_at',
        'processing_time_ms',
    ];

    protected $casts = [
        'raw_request' => 'array',
        'headers' => 'array',
        'processing_result' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Mark webhook as processing
     */
    public function markAsProcessing()
    {
        $this->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark webhook as success
     */
    public function markAsSuccess($result = null, $processingTimeMs = null)
    {
        $this->update([
            'status' => 'success',
            'processing_result' => $result,
            'processed_at' => now(),
            'processing_time_ms' => $processingTimeMs,
        ]);
    }

    /**
     * Mark webhook as failed
     */
    public function markAsFailed($errorMessage, $errorTrace = null, $processingTimeMs = null)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'error_trace' => $errorTrace,
            'processed_at' => now(),
            'processing_time_ms' => $processingTimeMs,
        ]);
    }

    /**
     * Scope to get pending webhooks
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get failed webhooks
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get successful webhooks
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope to filter by event type
     */
    public function scopeByEventType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to filter by order number
     */
    public function scopeByOrderNumber($query, $orderNumber)
    {
        return $query->where('order_number', $orderNumber);
    }
}
