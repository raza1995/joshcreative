<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_id',
        'assigned_to',
        'response',
        'status',
    ];
}
