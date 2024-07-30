<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = ['project_id', 'salesname', 'ip_address', 'utm_source', 'total_amount', 'earned_commission', 'created_at', 'updated_at'];
    
    public function meta()
    {
        return $this->hasMany(SalesMeta::class);
    }
}
