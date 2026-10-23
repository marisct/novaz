<?php

namespace App\Services;

use App\Models\ExamAttempt;

class ExamScoringService
{
    public function score(ExamAttempt $attempt): ExamAttempt
    {
        $attempt->loadMissing('exam.pattern.sections', 'exam.questions', 'answers.question');
        $sections = $attempt->exam->pattern?->sections?->keyBy('key');
        $breakdown = [];
        $correct = 0;
        $wrong = 0;
        $skipped = 0;
        $total = 0.0;

        foreach ($attempt->exam->questions as $question) {
            $answer = $attempt->answers->firstWhere('question_id', $question->id);
            $section = $sections?->get($question->section_key);
            $sectionKey = $question->section_key ?: 'general';
            $sectionName = $section?->name ?: 'General';
            $positive = $section ? (float) $section->positive_marks : (float) $question->marks;
            $negative = $section ? (float) $section->negative_marks : (float) $question->negative_marks;
            if ($attempt->exam->pattern?->settings['rrb_third_penalty'] ?? false) $negative = 1 / 3;
            $isScored = $section?->is_scored ?? true;

            $breakdown[$sectionKey] ??= [
                'name' => $sectionName, 'score' => 0.0, 'correct' => 0, 'wrong' => 0,
                'skipped' => 0, 'attempted' => 0, 'total' => 0,
                'time_seconds' => (int) (($attempt->section_times ?? [])[$sectionKey] ?? 0),
            ];
            $breakdown[$sectionKey]['total']++;

            if (! $answer || blank($answer->answer)) {
                $skipped++;
                $breakdown[$sectionKey]['skipped']++;
                continue;
            }

            $isCorrect = strtoupper($answer->answer) === strtoupper($question->correct_answer);
            $marks = $isCorrect ? $positive : -$negative;
            $answer->update(['is_correct' => $isCorrect, 'marks_awarded' => $marks]);
            $breakdown[$sectionKey]['attempted']++;
            $breakdown[$sectionKey]['score'] += $marks;
            if ($isScored) $total += $marks;

            if ($isCorrect) {
                $correct++;
                $breakdown[$sectionKey]['correct']++;
            } else {
                $wrong++;
                $breakdown[$sectionKey]['wrong']++;
            }
        }

        foreach ($breakdown as &$row) {
            $row['score'] = round($row['score'], 2);
            $row['accuracy'] = $row['attempted'] ? round($row['correct'] / $row['attempted'] * 100, 2) : 0;
        }
        unset($row);

        if ($attempt->exam->pattern?->settings['dest'] ?? false) {
            $text = $attempt->dest_text ?? '';
            $breakdown['dest'] = [
                'name' => 'Data Entry Speed Test (practice)', 'characters_typed' => mb_strlen($text),
                'target_characters' => mb_strlen($attempt->exam->dest_passage ?? ''),
                'time_seconds' => (int) (($attempt->section_times ?? [])['dest'] ?? 0),
                'evaluation' => 'Typing practice only; no official qualification verdict.',
            ];
        }

        if ($attempt->exam->pattern?->timer_mode !== 'section') {
            foreach ($attempt->answers as $answer) {
                $sectionKey = $answer->question->section_key ?: 'general';
                if (isset($breakdown[$sectionKey])) {
                    $breakdown[$sectionKey]['time_seconds'] += $answer->time_seconds;
                }
            }
        }

        $attempted = $correct + $wrong;
        $attempt->update([
            'submitted_at' => now(),
            'score' => round($total, 2),
            'correct_count' => $correct,
            'wrong_count' => $wrong,
            'skipped_count' => $skipped,
            'duration_seconds' => ($attempt->exam->pattern?->settings['official_mock'] ?? false) && $attempt->exam->pattern->timer_mode === 'section'
                ? array_sum($attempt->section_times ?? []) : ($attempt->started_at?->diffInSeconds(now()) ?? 0),
            'accuracy_percent' => $attempted ? round($correct / $attempted * 100, 2) : 0,
            'section_scores' => $breakdown,
            'status' => 'submitted',
        ]);

        return $attempt->refresh();
    }

    public function normalizedRrbScore(float $rawScore, array $shiftStatistics = []): float
    {
        return round($rawScore, 2);
    }
}
