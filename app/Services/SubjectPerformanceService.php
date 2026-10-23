<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\Subject;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Subject-wise performance, calculated from every question of every submitted
 * attempt: a question counts towards the subject it belongs to, whether it was
 * answered correctly, answered wrongly or left unanswered.
 */
class SubjectPerformanceService
{
    /** One row per subject for a single student (subjects of the student's courses, even if untested). */
    public function forStudent(StudentProfile $student): Collection
    {
        $stats = $this->base()
            ->where('attempts.student_profile_id', $student->id)
            ->groupBy('questions.subject_id')
            ->select('questions.subject_id')
            ->selectRaw($this->aggregates())
            ->get()
            ->keyBy('subject_id');

        $subjects = Subject::with('course')
            ->where(fn ($query) => $query
                ->whereIn('course_id', $student->courses()->pluck('courses.id'))
                ->orWhereIn('id', $stats->keys()))
            ->orderBy('course_id')->orderBy('name')
            ->get();

        return $subjects->map(fn (Subject $subject) => [
            'subject_id' => $subject->id,
            'subject' => $subject->name,
            'course' => $subject->course?->name,
        ] + $this->shape($stats->get($subject->id)))
            // Subjects with results first, then the ones not tested yet.
            ->sortBy([fn (array $a, array $b) => ($b['questions'] > 0) <=> ($a['questions'] > 0), ['course', 'asc'], ['subject', 'asc']])
            ->values();
    }

    /** One row per student for a single subject, best accuracy first. */
    public function forSubject(Subject $subject): Collection
    {
        $stats = $this->base()
            ->where('questions.subject_id', $subject->id)
            ->groupBy('attempts.student_profile_id')
            ->select('attempts.student_profile_id')
            ->selectRaw($this->aggregates())
            ->get()
            ->keyBy('student_profile_id');

        $students = StudentProfile::with('user')
            ->where(fn ($query) => $query
                ->whereHas('courses', fn ($courses) => $courses->whereKey($subject->course_id))
                ->orWhereIn('id', $stats->keys()))
            ->get();

        return $students->map(fn (StudentProfile $student) => [
            'student_profile_id' => $student->id,
            'student_id' => $student->student_id,
            'name' => $student->user?->name,
        ] + $this->shape($stats->get($student->id)))
            ->sortBy([['accuracy', 'desc'], ['correct', 'desc'], ['name', 'asc']])
            ->values();
    }

    private function base(): Builder
    {
        return DB::table('exam_attempts as attempts')
            ->join('exam_questions', 'exam_questions.exam_id', '=', 'attempts.exam_id')
            ->join('questions', 'questions.id', '=', 'exam_questions.question_id')
            ->leftJoin('student_answers as answers', fn ($join) => $join
                ->on('answers.exam_attempt_id', '=', 'attempts.id')
                ->on('answers.question_id', '=', 'questions.id'))
            ->where('attempts.status', 'submitted');
    }

    private function aggregates(): string
    {
        return 'count(*) as questions, '
            .'count(distinct attempts.id) as tests, '
            .'sum(case when answers.answer is not null then 1 else 0 end) as attempted, '
            .'sum(case when answers.answer is not null and answers.is_correct = 1 then 1 else 0 end) as correct, '
            .'sum(case when answers.answer is not null then answers.marks_awarded else 0 end) as marks';
    }

    private function shape(?object $row): array
    {
        $questions = (int) ($row->questions ?? 0);
        $attempted = (int) ($row->attempted ?? 0);
        $correct = (int) ($row->correct ?? 0);

        return [
            'tests' => (int) ($row->tests ?? 0),
            'questions' => $questions,
            'attempted' => $attempted,
            'correct' => $correct,
            'wrong' => $attempted - $correct,
            'skipped' => $questions - $attempted,
            'marks' => round((float) ($row->marks ?? 0), 2),
            // Accuracy is correct / attempted. Null means nothing was answered yet, which is not the same as 0%.
            'accuracy' => $attempted ? round($correct / $attempted * 100, 1) : null,
        ];
    }
}
