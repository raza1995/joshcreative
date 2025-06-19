<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDim extends Model
{
    protected $table = 'products_dim';
    protected $guarded = [];
    protected $fillable = [
        'shopify_product_id',
        'shopify_variant_id',
        'title',
        'sku',
        'list_price'
    ];
    public $timestamps = true;
}
