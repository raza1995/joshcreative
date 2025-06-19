<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefDomainDim extends Model
{
    protected $table = 'ref_domains_dim';
    protected $guarded = [];
    protected $fillable = ['domain'];
    public $timestamps = false; // no created_at/updated_at
}
