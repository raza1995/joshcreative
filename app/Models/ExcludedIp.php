<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExcludedIp extends Model
{
    use HasFactory;
    protected $table = 'excludedips';

    protected $fillable = ['ip_address', 'user_id'];
}
