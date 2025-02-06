<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_identifier', 'order_number', 'conversation_data'];

    protected $casts = [
        'conversation_data' => 'array', // Auto-cast JSON to array
    ];
}
