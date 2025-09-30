<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MycoleanUser extends Model
{
    use HasFactory;

    protected $table = 'mycolean_users';

    protected $fillable = [
        'email',
        'name',
    ];
}

