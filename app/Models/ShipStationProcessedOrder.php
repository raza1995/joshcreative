<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipStationProcessedOrder extends Model
{
    use HasFactory;

    protected $table = 'shipstation_processed_orders';

    protected $fillable = [
        'shipstation_order_id',
        'order_number',
        'order_key',
        'action',
        'items_before',
        'items_after',
        'processed_at',
        'last_checked_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Check if order was already processed
     */
    public static function wasProcessed(string $shipstationOrderId): bool
    {
        return static::where('shipstation_order_id', $shipstationOrderId)->exists();
    }

    /**
     * Mark order as processed
     */
    public static function markAsProcessed(
        string $shipstationOrderId,
        string $orderNumber,
        string $orderKey,
        string $action,
        ?int $itemsBefore = null,
        ?int $itemsAfter = null,
        ?string $notes = null,
        array $metadata = []
    ) {
        return static::updateOrCreate(
            ['shipstation_order_id' => $shipstationOrderId],
            [
                'order_number' => $orderNumber,
                'order_key' => $orderKey,
                'action' => $action,
                'items_before' => $itemsBefore,
                'items_after' => $itemsAfter,
                'processed_at' => now(),
                'last_checked_at' => now(),
                'notes' => $notes,
                'metadata' => $metadata,
            ]
        );
    }
}

