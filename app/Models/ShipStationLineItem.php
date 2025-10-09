<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipStationLineItem extends Model
{
    use HasFactory;

    protected $table = 'shipstation_line_items';

    protected $fillable = [
        'shipstation_order_id',
        'sku',
        'name',
        'quantity',
        'unit_price',
        'line_total',
        'line_discount',
        'is_consolidated',
        'original_line_count',
        'original_line_ids',
        'price_breakdown',
        'image_url',
        'weight',
        'weight_unit',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'line_discount' => 'decimal:2',
        'is_consolidated' => 'boolean',
        'original_line_count' => 'integer',
        'original_line_ids' => 'array',
        'price_breakdown' => 'array',
        'weight' => 'decimal:2',
    ];

    /**
     * Get the order that owns the line item
     */
    public function order()
    {
        return $this->belongsTo(ShipStationOrder::class, 'shipstation_order_id');
    }

    /**
     * Calculate weighted average price
     * Used when consolidating multiple line items with same SKU but different prices
     */
    public static function calculateWeightedAverage(array $items): float
    {
        $totalQuantity = 0;
        $totalValue = 0;

        foreach ($items as $item) {
            $qty = $item['quantity'] ?? 1;
            $price = $item['price'] ?? 0;
            
            $totalQuantity += $qty;
            $totalValue += ($qty * $price);
        }

        if ($totalQuantity === 0) {
            return 0;
        }

        return round($totalValue / $totalQuantity, 2);
    }

    /**
     * Get consolidation summary
     */
    public function getConsolidationSummary(): string
    {
        if (!$this->is_consolidated) {
            return 'Not consolidated';
        }

        return sprintf(
            'Merged %d line items (Total Qty: %d, Avg Price: $%s)',
            $this->original_line_count,
            $this->quantity,
            number_format((float)$this->unit_price, 2)
        );
    }
}

