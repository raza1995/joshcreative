<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeedbackLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'reason',
        'comments',
    ];

    public function review()
    {
        return $this->belongsTo(EmailReview::class);
    }
}
