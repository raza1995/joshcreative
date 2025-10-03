<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_type',
        'quiz_version',
        'question_number',
        'question_id',
        'question_text',
        'options',
        'help_text',
        'required',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByQuizType($query, $type)
    {
        return $query->where('quiz_type', $type);
    }

    public function scopeByVersion($query, $version)
    {
        return $query->where('quiz_version', $version);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('question_number');
    }
}
