<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class QuizSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_uuid',
        'quiz_type',
        'quiz_version',
        'user_email',
        'user_ip',
        'user_agent',
        'referrer_url',
        'shopify_domain',
        'demographics',
        'responses',
        'total_score',
        'risk_level',
        'risk_message',
        'completed',
        'started_at',
        'completed_at',
        'time_taken_seconds',
        'page_views',
        'session_metadata',
    ];

    protected $casts = [
        'demographics' => 'array',
        'responses' => 'array',
        'page_views' => 'array',
        'session_metadata' => 'array',
        'completed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->session_uuid)) {
                $model->session_uuid = Str::uuid();
            }
            if (empty($model->started_at)) {
                $model->started_at = now();
            }
        });
    }

    public function analytics()
    {
        return $this->hasMany(QuizAnalytic::class);
    }

    public function calculateRiskLevel(): array
    {
        $quizTypeService = app(\App\Services\QuizTypeService::class);
        return $quizTypeService->calculateRiskLevel($this);
    }


    public function markCompleted()
    {
        $this->update([
            'completed' => true,
            'completed_at' => now(),
            'time_taken_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('completed', true);
    }

    public function scopeByRiskLevel($query, $level)
    {
        return $query->where('risk_level', $level);
    }

    public function scopeByQuizType($query, $type)
    {
        return $query->where('quiz_type', $type);
    }
}
