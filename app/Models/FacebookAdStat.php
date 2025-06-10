<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookAdStat extends Model
{
    use HasFactory;

    protected $table = 'facebook_ad_stats';

    protected $fillable = [
        'facebook_ad_id',
        'ad_id',
        'campaign_name',
        'adset_name',
        'ad_name',
        'ad_type',
        'ad_account_name',
        'ad_link',
        'link_url',
        'thumbnail_url',
        'status',
        'updated_time',
        'interval',
        'date_key',
        'order_count',
        'spend',
        'clicks',
        'impressions',
        'ctr',
        'cpa',
        'roas',
    ];

    protected $casts = [
        'updated_time' => 'datetime',
    ];

    public function facebookAd()
    {
        return $this->belongsTo(FacebookAd::class);
    }
}
