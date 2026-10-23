<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use Illuminate\Support\Collection;

class ExamGeneratorService
{
    public function createOfficial(array $data, int $creator): Exam
    {
        $pattern = \App\Models\ExamPattern::with('sections')->findOrFail($data['exam_pattern_id']);
        $base = $this->bank($data['course_id'], $pattern->id)->where('type', 'mcq');
        $manual = ! empty($data['question_ids']);
        if ($manual) {
            $base->whereIn('id', $data['question_ids']);
        }
        $selected = collect();
        $errors = [];
        foreach ($pattern->sections as $section) {
            if (! $section->question_count) continue;
            // A random sample is enough; the bank can hold thousands of questions per section.
            $rows = (clone $base)->where('section_key', $section->key)->inRandomOrder()
                ->when(! $manual, fn ($query) => $query->limit($section->question_count * 4))->get()
                ->filter(fn ($q) => count(array_filter($q->options ?? [], fn ($v) => filled($v))) >= 4
                    && filled(($q->options ?? [])[$q->correct_answer] ?? null));
            if (($manual && $rows->count() !== $section->question_count) || $rows->count() < $section->question_count) {
                $errors[] = "{$section->name}: requires {$section->question_count} valid MCQs; found {$rows->count()}.";
            }
            $selected = $selected->merge($rows->take($section->question_count));
        }
        if ($manual && $selected->count() !== count(array_unique($data['question_ids']))) {
            $errors[] = 'Selected questions must belong to this course, pattern and section allocation.';
        }
        if (($pattern->settings['dest'] ?? false) && (mb_strlen($data['dest_passage'] ?? '') < 1900 || mb_strlen($data['dest_passage'] ?? '') > 2100)) {
            $errors[] = 'Tier 2 requires a typing passage of approximately 2,000 characters (1,900-2,100).';
        }
        if ($errors) throw \Illuminate\Validation\ValidationException::withMessages(['question_ids' => $errors]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $pattern, $selected, $creator) {
            $exam = Exam::create(array_merge($data, [
                'created_by' => $creator, 'status' => 'published', 'duration_minutes' => $pattern->duration_minutes,
                'total_questions' => $selected->count(), 'negative_marking' => true,
            ]));
            $exam->questions()->sync($selected->values()->mapWithKeys(fn ($q, $i) => [$q->id => ['position' => $i + 1, 'marks' => $q->marks]])->all());
            return $exam;
        });
    }

    /**
     * Active questions of a course usable for a pattern: those uploaded for that pattern,
     * and general ones (no pattern), which serve every pattern through their section.
     */
    private function bank(int|string $courseId, int|string $patternId): \Illuminate\Database\Eloquent\Builder
    {
        return Question::where('course_id', $courseId)->where('status', 'active')
            ->where(fn ($query) => $query->where('exam_pattern_id', $patternId)->orWhereNull('exam_pattern_id'));
    }

    public function attachBalancedQuestions(Exam $exam, int $totalQuestions): Collection
    {
        $exam->loadMissing('pattern.sections');
        if ($exam->pattern) {
            $selected = collect();
            foreach ($exam->pattern->sections as $section) {
                $selected = $selected->merge(
                    $this->bank($exam->course_id, $exam->exam_pattern_id)
                        ->where('section_key', $section->key)
                        ->inRandomOrder()
                        ->limit($section->question_count)
                        ->get()
                );
            }

            $sync = $selected->values()->mapWithKeys(fn (Question $question, int $index) => [
                $question->id => ['position' => $index + 1, 'marks' => $question->marks],
            ]);
            $exam->questions()->sync($sync->all());
            $exam->update(['total_questions' => $selected->count()]);

            return $exam->questions()->get();
        }

        $subjects = $exam->course->subjects()->active()->pluck('id');
        $perSubject = max(1, intdiv($totalQuestions, max(1, $subjects->count())));
        $selected = collect();

        foreach ($subjects as $subjectId) {
            $selected = $selected->merge(
                Question::where('course_id', $exam->course_id)
                    ->where('subject_id', $subjectId)
                    ->where('status', 'active')
                    ->inRandomOrder()
                    ->limit($perSubject)
                    ->get()
            );
        }

        if ($selected->count() < $totalQuestions) {
            $selected = $selected->merge(
                Question::where('course_id', $exam->course_id)
                    ->where('status', 'active')
                    ->whereNotIn('id', $selected->pluck('id'))
                    ->inRandomOrder()
                    ->limit($totalQuestions - $selected->count())
                    ->get()
            );
        }

        $sync = $selected->unique('id')->take($totalQuestions)->values()
            ->mapWithKeys(fn (Question $question, int $index) => [
                $question->id => ['position' => $index + 1, 'marks' => $question->marks],
            ]);

        $exam->questions()->sync($sync->all());

        return $exam->questions()->get();
    }
}
