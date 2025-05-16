<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['product_id', 'title', 'vendor'];

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
