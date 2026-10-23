<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Exam extends Model
{
    public const SUPPORTED_PATTERN_CODES = [
        'SSC_CGL_TIER1',
        'SSC_CGL_TIER2_PAPER1',
        'RRB_NTPC_CBT1',
        'RRB_NTPC_CBT2',
        ...\App\Services\OfficialExamPatterns::CODES,
    ];

    protected $fillable = [
        'course_id', 'exam_pattern_id', 'created_by', 'title', 'type', 'instructions', 'starts_at', 'ends_at',
        'duration_minutes', 'total_questions', 'passing_marks', 'negative_marking', 'status', 'dest_passage',
    ];

    protected $appends = ['state'];

    protected static function booted(): void
    {
        // An empty "Passing" box arrives as null; the column does not accept null on MySQL.
        static::creating(function (Exam $exam) {
            $exam->passing_marks ??= 0;
        });
    }

    /** upcoming = before starts_at, closed = after ends_at, otherwise available. */
    public function getStateAttribute(): string
    {
        if ($this->status !== 'published') {
            return 'draft';
        }
        if ($this->starts_at?->isFuture()) {
            return 'upcoming';
        }
        if ($this->ends_at?->isPast()) {
            return 'closed';
        }

        return 'available';
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'negative_marking' => 'boolean',
            'passing_marks' => 'decimal:2',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(ExamPattern::class, 'exam_pattern_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')->withPivot('position', 'marks')->withTimestamps();
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function scopeSupported(Builder $query): Builder
    {
        return $query->whereHas('pattern', fn (Builder $pattern) => $pattern->whereIn('code', self::SUPPORTED_PATTERN_CODES));
    }
}
