<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipStationOrder extends Model
{
    use HasFactory;

    protected $table = 'shipstation_orders';

    protected $fillable = [
        'shopify_order_id',
        'order_number',
        'order_key',
        'customer_name',
        'customer_email',
        'order_total',
        'shipping_amount',
        'tax_amount',
        'discount_amount',
        'ship_name',
        'ship_company',
        'ship_street1',
        'ship_street2',
        'ship_city',
        'ship_state',
        'ship_postal_code',
        'ship_country',
        'ship_phone',
        'consolidation_status',
        'shipstation_order_id',
        'pushed_at',
        'push_error',
        'original_line_items',
        'shopify_raw',
    ];

    protected $casts = [
        'order_total' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'pushed_at' => 'datetime',
        'original_line_items' => 'array',
        'shopify_raw' => 'array',
    ];

    /**
     * Get the Shopify order that this ShipStation order belongs to
     */
    public function shopifyOrder()
    {
        return $this->belongsTo(ShopifyOrder::class, 'shopify_order_id');
    }

    /**
     * Get the line items for this order
     */
    public function lineItems()
    {
        return $this->hasMany(ShipStationLineItem::class, 'shipstation_order_id');
    }

    /**
     * Get the sync logs for this order
     */
    public function syncLogs()
    {
        return $this->hasMany(ShipStationSyncLog::class, 'shipstation_order_id');
    }

    /**
     * Scope to get orders ready to push
     */
    public function scopeReadyToPush($query)
    {
        return $query->where('consolidation_status', 'consolidated')
                     ->whereNull('pushed_at');
    }

    /**
     * Scope to get failed orders
     */
    public function scopeFailed($query)
    {
        return $query->where('consolidation_status', 'failed');
    }

    /**
     * Mark order as pushed
     */
    public function markAsPushed($shipstationOrderId = null)
    {
        $this->update([
            'consolidation_status' => 'pushed',
            'shipstation_order_id' => $shipstationOrderId,
            'pushed_at' => now(),
            'push_error' => null,
        ]);
    }

    /**
     * Mark order as failed
     */
    public function markAsFailed($errorMessage)
    {
        $this->update([
            'consolidation_status' => 'failed',
            'push_error' => $errorMessage,
        ]);
    }
}

