<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use HasActiveScope;

    protected $fillable = ['name', 'code', 'description', 'duration', 'status'];

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(StudentProfile::class, 'course_student')->withTimestamps();
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(TeacherProfile::class, 'course_teacher')->withTimestamps();
    }
}
