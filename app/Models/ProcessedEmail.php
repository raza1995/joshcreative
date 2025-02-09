<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedEmail extends Model
{
    protected $fillable = [
        'message_id',
        'sender_email',
        'subject',
        'snippet',
        'received_at',
    ];
}
