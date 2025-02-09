<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'draft_id',
        'reviewer_id',
        'status',
        'feedback',
    ];

    public function draft()
    {
        return $this->belongsTo(EmailDraft::class);
    }
}
