<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceDim extends Model
{
    protected $table = 'devices_dim';
    protected $guarded = []; // Allow mass assignment
    protected $fillable = ['device_uuid'];
    public $timestamps = true;
}
