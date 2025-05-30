<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacebookAd extends Model
{
    use HasFactory;

    protected $fillable = [
        'ad_account_name',
        'account_name',
        'ad_id',
        'campaign_id',
        'campaign_name',
        'adset_id',
        'adset_name',
        'ad_name',
        'ad_type',
        'ad_link',
        'title',
        'body',
        'description',
        'thumbnail_url',
        'video_id',
        'image_url',
        'link_url',
        'display_url',
        'call_to_action',
        'impressions',
        'clicks',
        'ctr',
        'cpc',
        'cpm',
        'spend',
        'status',
        'updated_time',
        'purchase_roas',
        'conversions',
        'cpa',
        'video_url',
        'full_picture',
    ];

    public function metrics()
{
    return $this->hasMany(FacebookAdMetric::class);
}

}
