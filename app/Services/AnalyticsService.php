<?php

namespace App\Services;

use App\Models\Ranking;
use App\Models\StudentAnalytic;
use App\Models\StudentProfile;

class AnalyticsService
{
    public function refreshStudent(StudentProfile $student): StudentAnalytic
    {
        $attempts = $student->attempts()->with('answers.question.subject')->where('status', 'submitted')->get();
        $answers = $attempts->flatMap->answers;
        $weak = $answers->where('is_correct', false)->pluck('question.topic')->filter()->countBy()->sortDesc()->keys()->take(3)->values();
        $strong = $answers->where('is_correct', true)->pluck('question.topic')->filter()->countBy()->sortDesc()->keys()->take(3)->values();
        $totalAnswers = max(1, $answers->count());

        return StudentAnalytic::updateOrCreate(
            ['student_profile_id' => $student->id, 'subject_id' => null, 'snapshot_date' => today()],
            [
                'average_score' => round((float) $attempts->avg('score'), 2),
                'accuracy_percent' => round(($answers->where('is_correct', true)->count() / $totalAnswers) * 100, 2),
                'speed_seconds' => (int) round((float) $answers->avg('time_seconds')),
                'tests_attempted' => $attempts->count(),
                'tests_skipped' => 0,
                'strong_areas' => $strong,
                'weak_areas' => $weak,
                'recommendations' => $weak->map(fn (string $topic) => "Practice {$topic} with timed revision sets."),
            ]
        );
    }

    public function rebuildOverallRankings(): void
    {
        StudentProfile::withAvg('attempts', 'score')
            ->orderByDesc('attempts_avg_score')
            ->get()
            ->values()
            ->each(function (StudentProfile $student, int $index) {
                Ranking::updateOrCreate(
                    ['student_profile_id' => $student->id, 'scope' => 'overall', 'period' => now()->format('Y-m')],
                    ['score' => (float) $student->attempts_avg_score, 'rank' => $index + 1]
                );
            });
    }
}
