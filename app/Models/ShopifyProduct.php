<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyProduct extends Model
{
    use HasFactory;

    protected $table = 'shopify_products';

    protected $fillable = [
        'shopify_product_id',
        'shopify_variant_id',
        'title',
        'handle',
        'sku',
        'barcode',
        'price',
        'compare_at_price',
        'cost_price',
        'weight',
        'weight_unit',
        'inventory_quantity',
        'inventory_policy',
        'inventory_management',
        'fulfillment_service',
        'product_type',
        'vendor',
        'tags',
        'options',
        'status',
        'requires_shipping',
        'taxable',
        'tax_code',
        'images',
        'body_html',
        'seo_title',
        'seo_description',
        'metafields',
        'raw_data',
        'last_synced_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:3',
        'inventory_quantity' => 'integer',
        'requires_shipping' => 'boolean',
        'taxable' => 'boolean',
        'tags' => 'array',
        'options' => 'array',
        'images' => 'array',
        'metafields' => 'array',
        'raw_data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get product by SKU
     */
    public static function findBySku(string $sku): ?self
    {
        return static::where('sku', $sku)->first();
    }

    /**
     * Get products by SKU array
     */
    public static function findBySkus(array $skus): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereIn('sku', $skus)->get();
    }

    /**
     * Get active products only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get products by type (bundle, regular, etc.)
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('product_type', $type);
    }

    /**
     * Get products that need syncing (older than specified hours)
     */
    public function scopeNeedsSync($query, int $hours = 24)
    {
        return $query->where(function ($q) use ($hours) {
            $q->whereNull('last_synced_at')
              ->orWhere('last_synced_at', '<', now()->subHours($hours));
        });
    }

    /**
     * Check if this is a bundle product
     */
    public function isBundle(): bool
    {
        return str_starts_with($this->sku ?? '', 'BUND-');
    }

    /**
     * Check if this is a component product
     */
    public function isComponent(): bool
    {
        return str_starts_with($this->sku ?? '', 'REG-');
    }

    /**
     * Get the effective price (use price column, not compare_at_price)
     */
    public function getEffectivePrice(): float
    {
        return (float) ($this->price ?? 0);
    }

    /**
     * Update last synced timestamp
     */
    public function markAsSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }
}