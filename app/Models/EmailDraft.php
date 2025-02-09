<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_id',
        'subject',
        'body',
        'status',
        'shopify_order_id',
    ];

    public function shopifyOrder()
    {
        return $this->belongsTo(ShopifyOrder::class);
    }

    public function review()
    {
        return $this->hasOne(EmailReview::class);
    }
}
