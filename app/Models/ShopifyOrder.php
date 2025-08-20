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

public function scopeByProduct($query, $sku)
{
    // collect one id per order_number that has a matching line_items[*].sku === $sku
    $idsByOrder = [];

    static::select('id', 'order_number', 'product_name', 'raw_json')
        ->orderBy('id')
        ->chunkById(1000, function ($chunk) use (&$idsByOrder, $sku) {
            foreach ($chunk as $row) {
                // decode safely whether it's cast or a JSON string
                $raw = is_array($row->raw_json)
                    ? $row->raw_json
                    : (is_string($row->raw_json) ? (json_decode($row->raw_json, true) ?: []) : []);

                foreach (($raw['line_items'] ?? []) as $li) {
                    if (($li['sku'] ?? null) === $sku) {
                        // keep a single row per order_number (pick smallest id)
                        if (!isset($idsByOrder[$row->order_number]) || $row->id < $idsByOrder[$row->order_number]) {
                            $idsByOrder[$row->order_number] = $row->id;
                        }
                        break; // next row
                    }
                }
            }
        });

    if (empty($idsByOrder)) {
        return $query->whereRaw('1=0'); // no matches
    }

    return $query->whereIn('id', array_values($idsByOrder));
}




}
