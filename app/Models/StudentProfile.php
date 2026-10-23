<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentProfile extends Model
{
    use HasActiveScope;

    protected $fillable = [
        'user_id', 'student_id', 'admission_number', 'gender', 'dob', 'address',
        'blood_group', 'aadhaar_number', 'join_date', 'status',
    ];

    protected function casts(): array
    {
        return ['dob' => 'date', 'join_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): HasOne
    {
        return $this->hasOne(ParentProfile::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_student')->withTimestamps();
    }

    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'batch_student')->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** Present + late days as a percentage of all recorded days. Null when nothing is recorded yet. */
    public function attendancePercent(): ?float
    {
        $total = $this->attendances()->count();
        if ($total === 0) {
            return null;
        }

        return round($this->attendances()->whereIn('status', ['present', 'late'])->count() / $total * 100, 1);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }
}
