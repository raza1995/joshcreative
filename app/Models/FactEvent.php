<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FactEvent extends Model
{
    protected $table = 'fact_events';
    protected $guarded = [];
    protected $fillable = [
        'device_id',
        'ref_domain_id',
        'product_id',
        'event_type',
        'event_ts',
        'order_id',
        'revenue',
        'qty',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'raw_props',
    ];
    public $timestamps = false;
}
