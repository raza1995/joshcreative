<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopifyMycoleanOrder extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'product_title',
        'variant_id',
        'quantity',
        'total_price',
        'raw_json',
        'order_date',
    ];
}
