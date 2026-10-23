<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\StudentProfile;
use Carbon\CarbonImmutable;

class StudentProgressService
{
    public function weekly(StudentProfile $student): array
    {
        $start = CarbonImmutable::now()->startOfWeek();
        $attempts = $student->attempts()->where('status', 'submitted')
            ->whereBetween('submitted_at', [$start->subWeeks(7), now()])->get();
        $weeks = [];
        for ($offset = 7; $offset >= 0; $offset--) {
            $from = $start->subWeeks($offset);
            $rows = $attempts->filter(fn ($attempt) => $attempt->submitted_at->gte($from) && $attempt->submitted_at->lt($from->addWeek()));
            $answered = $rows->sum('correct_count') + $rows->sum('wrong_count');
            $weeks[] = [
                'start' => $from->toDateString(), 'label' => $from->format('d M'),
                'tests' => $rows->count(),
                'accuracy' => $answered ? round(100 * $rows->sum('correct_count') / $answered, 1) : null,
                'average_score' => $rows->isEmpty() ? null : round($rows->avg('score'), 2),
            ];
        }
        $sections = [];
        foreach ($attempts->filter(fn ($attempt) => $attempt->submitted_at->gte($start)) as $attempt) {
            foreach ($attempt->section_scores ?? [] as $key => $row) {
                $sections[$key] ??= ['name' => $row['name'] ?? $key, 'correct' => 0, 'attempted' => 0];
                $sections[$key]['correct'] += $row['correct'] ?? 0;
                $sections[$key]['attempted'] += $row['attempted'] ?? 0;
            }
        }
        foreach ($sections as &$section) {
            $section['accuracy'] = $section['attempted'] ? round(100 * $section['correct'] / $section['attempted'], 1) : null;
        }
        unset($section);
        $current = $weeks[7];
        $previous = $weeks[6];

        return [
            'timezone' => config('app.timezone'), 'weeks' => $weeks,
            'current' => $current, 'previous' => $previous,
            'accuracy_change' => $current['accuracy'] !== null && $previous['accuracy'] !== null
                ? round($current['accuracy'] - $previous['accuracy'], 1) : null,
            'sections' => array_values($sections),
        ];
    }

    public function exams(StudentProfile $student): array
    {
        return Exam::supported()->with('course')->where('status', 'published')
            ->whereIn('course_id', $student->courses()->select('courses.id'))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->whereDoesntHave('attempts', fn ($q) => $q->where('student_profile_id', $student->id)->where('status', 'submitted'))
            ->orderBy('starts_at')->limit(25)->get()->map(fn ($exam) => [
                'id' => 'exam-'.$exam->id, 'exam_id' => $exam->id,
                'title' => $exam->title, 'course' => $exam->course?->name,
                'type' => 'exam', 'state' => $exam->starts_at?->isFuture() ? 'upcoming' : 'available',
                'body' => $exam->starts_at?->isFuture() ? 'Exam scheduled' : 'Ready to take',
                'starts_at' => $exam->starts_at?->toIso8601String(),
                'ends_at' => $exam->ends_at?->toIso8601String(),
                'duration_minutes' => $exam->duration_minutes, 'total_questions' => $exam->total_questions,
            ])->all();
    }
}
