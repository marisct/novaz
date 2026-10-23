<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use App\Services\ExamScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamAttemptController extends Controller
{
    public function start(Request $request, Exam $exam): RedirectResponse
    {
        $student = $request->user()->studentProfile;
        abort_unless($student && $student->courses()->whereKey($exam->course_id)->exists(), 403);
        abort_unless($exam->status === 'published', 404);

        $exam->load('pattern.sections');
        if ($exam->starts_at?->isFuture()) {
            return redirect()->route('exams.index')->withErrors(['exam' => 'This exam opens on '.$exam->starts_at->format('d M Y, H:i').'.']);
        }
        if ($exam->ends_at?->isPast()) {
            return redirect()->route('exams.index')->withErrors(['exam' => 'This exam window has closed.']);
        }
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
            ? redirect()->route('attempts.result', $attempt)
            : redirect()->route('attempts.show', $attempt);
    }

    public function show(Request $request, ExamAttempt $attempt, ExamScoringService $scoring): View|RedirectResponse
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $attempt, $scoring) {
            $locked = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            return $this->showLocked($request, $locked, $scoring);
        });
    }

    private function showLocked(Request $request, ExamAttempt $attempt, ExamScoringService $scoring): View|RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);
        if ($attempt->status === 'submitted') {
            return redirect()->route('attempts.result', $attempt);
        }

        $attempt->load('exam.pattern.sections', 'exam.questions.subject', 'answers');
        if ($this->isExpired($attempt)) {
            return $this->advanceOrSubmit($attempt, $scoring);
        }

        $pattern = $attempt->exam->pattern;
        $currentSection = $pattern?->timer_mode === 'section' ? $pattern->sections->firstWhere('key', $attempt->current_section_key) : null;
        $questions = $attempt->exam->questions
            ->sortBy('pivot.position')
            ->pipe(fn ($items) => $attempt->questionsInCurrentSection($items))
            ->values();

        return view('exams.take', [
            'attempt' => $attempt,
            'questions' => $questions,
            'answers' => $attempt->answers->keyBy('question_id'),
            'sections' => $pattern?->sections ?? collect(),
            'currentSection' => $currentSection,
            'remainingSeconds' => $this->remainingSeconds($attempt),
        ]);
    }

    public function save(Request $request, ExamAttempt $attempt, ExamScoringService $scoring): RedirectResponse
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $attempt, $scoring) {
            $locked = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            return $this->saveLocked($request, $locked, $scoring);
        });
    }

    private function saveLocked(Request $request, ExamAttempt $attempt, ExamScoringService $scoring): RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);
        abort_if($attempt->status === 'submitted', 409);
        $attempt->load('exam.pattern.sections', 'exam.questions');
        $request->validate(['action' => ['required', 'in:save,continue,start_dest'], 'dest_text' => ['nullable', 'string', 'max:10000']]);
        $clock = app(\App\Services\ExamClock::class);
        if ($request->input('action') === 'start_dest' && $clock->isBreak($attempt)) {
            $attempt->update(['section_started_at' => now()]);
            return redirect()->route('attempts.show', $attempt);
        }
        if ($request->filled('section_key') && $request->input('section_key') !== $attempt->current_section_key) {
            return redirect()->route('attempts.show', $attempt);
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
            return back()->with('status', 'Answers saved.');
        }

        return $this->advanceOrSubmit($attempt->fresh('exam.pattern.sections'), $scoring);
    }

    public function result(Request $request, ExamAttempt $attempt): View
    {
        $this->authorizeAttempt($request, $attempt);
        abort_unless($attempt->status === 'submitted', 404);
        $attempt->load('exam.pattern.sections', 'exam.questions.subject', 'answers.question');

        return view('exams.result', ['attempt' => $attempt]);
    }

    private function advanceOrSubmit(ExamAttempt $attempt, ExamScoringService $scoring): RedirectResponse
    {
        app(\App\Services\ExamClock::class)->advance($attempt, $scoring);
        return $attempt->status === 'submitted' ? redirect()->route('attempts.result', $attempt) : redirect()->route('attempts.show', $attempt);
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
