<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SoberDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'mycolean_user_id',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
