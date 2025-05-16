<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopifyEventLog extends Model
{
    protected $fillable = [
        'anon_id', 'event_type', 'funnel_stage', 'element',
        'page_url', 'page_type', 'referrer', 'timestamp',
        'focus_time', 'utm', 'screen', 'user_agent', 'platform'
    ];

    protected $casts = [
        'utm' => 'array',
        'screen' => 'array',
        'timestamp' => 'datetime',
    ];
}
