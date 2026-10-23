<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    protected $fillable = [
        'exam_id', 'student_profile_id', 'started_at', 'current_section_key', 'section_started_at',
        'section_times', 'locked_sections', 'section_scores', 'accuracy_percent', 'submitted_at', 'score',
        'correct_count', 'wrong_count', 'skipped_count', 'duration_seconds', 'status', 'dest_text',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'section_started_at' => 'datetime', 'submitted_at' => 'datetime',
            'score' => 'decimal:2', 'accuracy_percent' => 'decimal:2', 'section_times' => 'array',
            'locked_sections' => 'array', 'section_scores' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    /**
     * Questions the student may see right now. Section-timed patterns show one
     * section at a time; questions whose section is missing or unknown to the
     * pattern are shown in the first section instead of being hidden.
     */
    public function questionsInCurrentSection(\Illuminate\Support\Collection $questions): \Illuminate\Support\Collection
    {
        $pattern = $this->exam?->pattern;
        if ($pattern?->timer_mode !== 'section') {
            return $questions;
        }

        $keys = $pattern->sections->pluck('key');
        $isFirst = $this->current_section_key === $keys->first();

        return $questions->filter(fn ($question) => $question->section_key === $this->current_section_key
            || ($isFirst && ! $keys->contains($question->section_key)));
    }

    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }
}
