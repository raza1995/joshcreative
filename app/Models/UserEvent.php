<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'page_url',
        'start_time',
        'end_time',
        'stay_duration',
        'focus_time',
        'event_type',
        'element'
    ];
}
