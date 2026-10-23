<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAnalytic extends Model
{
    protected $fillable = [
        'student_profile_id', 'subject_id', 'average_score', 'accuracy_percent', 'speed_seconds',
        'tests_attempted', 'tests_skipped', 'strong_areas', 'weak_areas', 'recommendations', 'snapshot_date',
    ];

    protected function casts(): array
    {
        return [
            'strong_areas' => 'array',
            'weak_areas' => 'array',
            'recommendations' => 'array',
            'snapshot_date' => 'date',
        ];
    }
}
