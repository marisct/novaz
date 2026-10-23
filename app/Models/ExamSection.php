<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSection extends Model
{
    protected $fillable = [
        'exam_pattern_id', 'key', 'name', 'position', 'question_count', 'duration_minutes',
        'positive_marks', 'negative_marks', 'locks_on_exit', 'is_scored',
    ];

    protected function casts(): array
    {
        return [
            'positive_marks' => 'decimal:2',
            'negative_marks' => 'decimal:4',
            'locks_on_exit' => 'boolean',
            'is_scored' => 'boolean',
        ];
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(ExamPattern::class, 'exam_pattern_id');
    }
}
