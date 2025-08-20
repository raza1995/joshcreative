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
    $table = $query->getModel()->getTable();

    return $query->whereIn('id', function ($sub) use ($table, $productName) {
        $sub->from("$table as t")
            ->selectRaw('MIN(t.id) as id')
            ->where('t.product_name', $productName)
            ->orWhereRaw("
                EXISTS (
                  SELECT 1
                  FROM JSON_TABLE(CAST(t.raw_json AS JSON), '$.line_items[*]'
                       COLUMNS (sku VARCHAR(255) PATH '$.sku')) li
                  WHERE li.sku = ?
                )
            ", [$productName])
            ->groupBy('t.order_number');
    });
}



}
