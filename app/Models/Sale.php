<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SalesMeta;
class Sale extends Model
{
    use HasFactory;
    protected $fillable = ['project_id', 'user_id', 'salesname','email', 'ip_address', 'utm_source', 'total_amount', 'earned_commission', 'created_at', 'updated_at'];
    
    public function meta()
    {
        return $this->hasMany(SalesMeta::class);
    }
}
