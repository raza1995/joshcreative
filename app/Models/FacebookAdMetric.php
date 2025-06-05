<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FacebookAdMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'facebook_ad_id',
        'ad_id',
        'interval',
        'date_key',
        'impressions',
        'clicks',
        'ctr',
        'cpc',
        'cpm',
        'spend',
        'purchase_roas',
        'conversions',
        'cpa',
        'add_to_cart',
        'initiate_checkout',
        'view_content',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'purchase_roas' => 'array',
        'date_key' => 'string',
        'start_date' => 'date',
        'end_date' => 'date',
    ];
    

    public function facebookAd()
    {
        return $this->belongsTo(FacebookAd::class, 'facebook_ad_id');
    }
}
