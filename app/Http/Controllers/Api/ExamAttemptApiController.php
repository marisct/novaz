<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use App\Services\ExamScoringService;
use Illuminate\Http\Request;

class ExamAttemptApiController extends Controller
{
    public function start(Request $request, Exam $exam): array
    {
        $student = $request->user()->studentProfile;
        abort_unless($student && $student->courses()->whereKey($exam->course_id)->exists(), 403);
        abort_unless($exam->status === 'published', 404);

        $exam->load('pattern.sections');
        abort_if($exam->starts_at?->isFuture(), 422, 'This exam opens on '.$exam->starts_at?->format('d M Y, H:i').'.');
        abort_if($exam->ends_at?->isPast(), 422, 'This exam window has closed.');
        $firstSection = $exam->pattern?->sections->first()?->key;
        $attempt = ExamAttempt::firstOrCreate(
            ['exam_id' => $exam->id, 'student_profile_id' => $student->id],
            [
                'started_at' => now(),
                'current_section_key' => $firstSection,
                'section_started_at' => $firstSection ? now() : null,
                'section_times' => [],
                'locked_sections' => [],
                'status' => 'started',
            ]
        );

        return $attempt->status === 'submitted'
            ? $this->resultPayload($attempt)
            : $this->show($request, $attempt, app(ExamScoringService::class));
    }

    public function show(Request $request, ExamAttempt $attempt, ExamScoringService $scoring): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $attempt, $scoring) {
            $locked = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            return $this->showLocked($request, $locked, $scoring);
        });
    }

    private function showLocked(Request $request, ExamAttempt $attempt, ExamScoringService $scoring): array
    {
        $this->authorizeAttempt($request, $attempt);
        if ($attempt->status === 'submitted') {
            return $this->resultPayload($attempt);
        }
        if ($this->isExpired($attempt->load('exam.pattern.sections'))) {
            return $this->advanceOrSubmit($attempt, $scoring);
        }

        return $this->attemptPayload($attempt);
    }

    public function save(Request $request, ExamAttempt $attempt, ExamScoringService $scoring): array
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $attempt, $scoring) {
            $locked = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            return $this->saveLocked($request, $locked, $scoring);
        });
    }

    private function saveLocked(Request $request, ExamAttempt $attempt, ExamScoringService $scoring): array
    {
        $this->authorizeAttempt($request, $attempt);
        abort_if($attempt->status === 'submitted', 409);
        $attempt->load('exam.pattern.sections', 'exam.questions');
        $request->validate(['action' => ['required', 'in:save,continue,start_dest'], 'dest_text' => ['nullable', 'string', 'max:10000']]);
        $clock = app(\App\Services\ExamClock::class);
        if ($request->input('action') === 'start_dest' && $clock->isBreak($attempt)) {
            $attempt->update(['section_started_at' => now()]);
            return $this->attemptPayload($attempt->fresh());
        }
        if ($request->filled('section_key') && $request->input('section_key') !== $attempt->current_section_key) {
            return $this->attemptPayload($attempt);
        }
        if ($this->isExpired($attempt)) {
            return $this->advanceOrSubmit($attempt, $scoring);
        }

        $pattern = $attempt->exam->pattern;
        $clock->saveTyping($attempt, (string) $request->input('dest_text', $attempt->dest_text ?? ''));
        $allowedQuestions = $attempt->exam->questions
            ->pipe(fn ($items) => $attempt->questionsInCurrentSection($items))
            ->keyBy('id');

        foreach ((array) $request->input('answers', []) as $questionId => $answer) {
            $question = $allowedQuestions->get((int) $questionId);
            if (! $question || ! in_array(strtoupper((string) $answer), array_keys(array_filter($question->options ?? [])), true)) {
                continue;
            }

            StudentAnswer::updateOrCreate(
                ['exam_attempt_id' => $attempt->id, 'question_id' => $question->id],
                [
                    'section_key' => $question->section_key,
                    'answer' => strtoupper((string) $answer),
                    'time_seconds' => max(0, (int) $request->input("question_times.{$question->id}", 0)),
                ]
            );
        }

        if ($request->input('action') === 'save') {
            return $this->attemptPayload($attempt->fresh());
        }

        return $this->advanceOrSubmit($attempt->fresh('exam.pattern.sections'), $scoring);
    }

    public function result(Request $request, ExamAttempt $attempt): array
    {
        $this->authorizeAttempt($request, $attempt);
        abort_unless($attempt->status === 'submitted', 404);

        return $this->resultPayload($attempt);
    }

    private function attemptPayload(ExamAttempt $attempt): array
    {
        $attempt->load('exam.pattern.sections', 'exam.questions.subject', 'answers');
        $pattern = $attempt->exam->pattern;
        $questions = $attempt->exam->questions
            ->sortBy('pivot.position')
            ->pipe(fn ($items) => $attempt->questionsInCurrentSection($items))
            ->values();

        return [
            'status' => 'attempt',
            'attempt' => $attempt->withoutRelations(),
            'exam' => $attempt->exam->withoutRelations()->toArray() + ['pattern' => $pattern],
            'sections' => $pattern?->sections ?? [],
            'questions' => $questions->map(fn ($q) => $q->only(['id', 'question', 'options', 'language', 'section_key', 'subject_id'])),
            'answers' => $attempt->answers->keyBy('question_id'),
            'remaining_seconds' => $this->remainingSeconds($attempt),
            'session_break' => app(\App\Services\ExamClock::class)->isBreak($attempt),
            'typing_session' => $attempt->current_section_key === 'dest' && ($pattern?->settings['official_mock'] ?? false),
        ];
    }

    private function resultPayload(ExamAttempt $attempt): array
    {
        $attempt->load('exam.pattern.sections', 'exam.questions.subject', 'answers.question');

        return [
            'status' => 'result',
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'questions' => $attempt->exam->questions->sortBy('pivot.position')->values(),
            'answers' => $attempt->answers->keyBy('question_id'),
            'section_scores' => $attempt->section_scores ?? [],
        ];
    }

    private function advanceOrSubmit(ExamAttempt $attempt, ExamScoringService $scoring): array
    {
        app(\App\Services\ExamClock::class)->advance($attempt, $scoring);
        return $attempt->status === 'submitted' ? $this->resultPayload($attempt->fresh()) : $this->attemptPayload($attempt->fresh());
    }

    private function remainingSeconds(ExamAttempt $attempt): int
    {
        return app(\App\Services\ExamClock::class)->remaining($attempt);
    }

    private function isExpired(ExamAttempt $attempt): bool
    {
        return ! app(\App\Services\ExamClock::class)->isBreak($attempt) && $this->remainingSeconds($attempt) <= 0;
    }

    private function authorizeAttempt(Request $request, ExamAttempt $attempt): void
    {
        abort_unless(
            $request->user()->role === 'student'
            && $request->user()->studentProfile?->id === $attempt->student_profile_id,
            403
        );
    }
}
