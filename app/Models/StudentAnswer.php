<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    protected $fillable = [
        'exam_attempt_id', 'question_id', 'section_key', 'answer', 'is_correct', 'marks_awarded', 'time_seconds',
    ];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'marks_awarded' => 'decimal:2'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }
}
