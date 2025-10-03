<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_session_id',
        'event_type',
        'question_id',
        'event_data',
        'event_timestamp',
        'time_on_question',
        'user_action',
        'interaction_data',
    ];

    protected $casts = [
        'event_data' => 'array',
        'interaction_data' => 'array',
        'event_timestamp' => 'datetime',
    ];

    public function quizSession()
    {
        return $this->belongsTo(QuizSession::class);
    }

    // Alias for convenience
    public function session()
    {
        return $this->quizSession();
    }

    public function scopeByEventType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeByQuestion($query, $questionId)
    {
        return $query->where('question_id', $questionId);
    }
}
