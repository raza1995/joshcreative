<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'order_date',
        'product_name',
        'customer_name',
        'email_address',
        'tracking_number',
        'tracking_url',
        'coupon',
        'paid_amount',
        'discount',
        'number_of_items',
        'email_draft_id',
        'anon_id',
        'raw_json',
        'ad_id',
    ];

    public function emailDraft()
    {
        return $this->hasOne(EmailDraft::class);
    }
    public function facebookAd()
{
    return $this->belongsTo(FacebookAd::class, 'ad_id', 'ad_id');
}

public function scopeByProduct($query, $productName)
{
    return $query->where('product_name', $productName)
                 ->orWhere(function ($q) use ($productName) {
                     // Search in raw_json line_items[*].title
                     $q->whereRaw('JSON_SEARCH(raw_json, "one", ?, NULL, "$.line_items[*].title") IS NOT NULL', [$productName]);
                 });
}
}
