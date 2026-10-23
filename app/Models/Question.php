<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = [
        'course_id', 'subject_id', 'exam_pattern_id', 'section_key', 'language',
        'type', 'question', 'options', 'correct_answer',
        'difficulty', 'marks', 'negative_marks', 'explanation', 'topic', 'status',
    ];

    protected function casts(): array
    {
        return ['options' => 'array', 'marks' => 'decimal:2', 'negative_marks' => 'decimal:2'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(ExamPattern::class, 'exam_pattern_id');
    }
}
