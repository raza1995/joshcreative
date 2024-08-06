<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SalesMeta;
class Sale extends Model
{
    use HasFactory;
    protected $fillable = ['project_id','name','user_id', 'salesname','email', 'ip_address', 'utm_source', 'total_amount', 'earned_commission', 'created_at', 'updated_at','dj_user_id', 'price','purchase_count','product_id','status', 'promo_code', 'sales_id', 'sales_event_id'];
    
    public function meta()
    {
        return $this->hasMany(SalesMeta::class);
    }
}
