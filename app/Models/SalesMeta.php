<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesMeta extends Model
{
    use HasFactory;

    protected $fillable = ['sale_id', 'meta_key', 'meta_value'];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}