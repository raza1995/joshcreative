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
    ];

    public function emailDraft()
    {
        return $this->hasOne(EmailDraft::class);
    }
}
