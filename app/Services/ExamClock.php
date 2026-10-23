<?php

namespace App\Services;

use App\Models\ExamAttempt;
use Illuminate\Validation\ValidationException;

class ExamClock
{
    public function isBreak(ExamAttempt $attempt): bool
    {
        return $attempt->current_section_key === 'dest' && $attempt->section_started_at === null
            && ($attempt->exam->pattern?->settings['official_mock'] ?? false);
    }

    public function remaining(ExamAttempt $attempt): int
    {
        $pattern = $attempt->exam->pattern;
        if ($this->isBreak($attempt)) return 0;
        if ($pattern?->timer_mode === 'section') {
            $section = $pattern->sections->firstWhere('key', $attempt->current_section_key);
            $deadline = $attempt->section_started_at?->copy()->addMinutes($section?->duration_minutes ?? 0);
        } else {
            $deadline = $attempt->started_at?->copy()->addMinutes($pattern?->duration_minutes ?? $attempt->exam->duration_minutes);
        }
        return $deadline ? max(0, (int) ceil(now()->diffInSeconds($deadline, false))) : 0;
    }

    public function advance(ExamAttempt $attempt, ExamScoringService $scoring): void
    {
        $pattern = $attempt->exam->pattern;
        if ($attempt->status === 'submitted') return;
        if ($this->isBreak($attempt)) return;
        $official = $pattern?->settings['official_mock'] ?? false;
        if ($official && $pattern->timer_mode === 'section' && $this->remaining($attempt) > 0) {
            throw ValidationException::withMessages(['action' => 'This section closes automatically when its time ends.']);
        }
        do {
            if ($pattern?->timer_mode !== 'section') {
                $scoring->score($attempt);
                return;
            }
            $current = $pattern->sections->firstWhere('key', $attempt->current_section_key);
            $deadline = $attempt->section_started_at->copy()->addMinutes($current->duration_minutes);
            $expired = $deadline->lte(now());
            $times = $attempt->section_times ?? [];
            $times[$current->key] = min($current->duration_minutes * 60, max(0, (int) $attempt->section_started_at->diffInSeconds(now())));
            $locked = array_values(array_unique([...($attempt->locked_sections ?? []), $current->key]));
            $next = $pattern->sections->first(fn ($s) => $s->position > $current->position && ! in_array($s->key, $locked, true));
            $attempt->fill(['section_times' => $times, 'locked_sections' => $locked]);
            if (! $next) {
                $attempt->save();
                $scoring->score($attempt);
                return;
            }
            $attempt->fill([
                'current_section_key' => $next->key,
                'section_started_at' => $official && $next->key === 'dest' ? null : ($expired ? $deadline : now()),
            ])->save();
            // Offline time consumes later sections too; reconnecting never grants extra time.
        } while (! $this->isBreak($attempt) && $this->remaining($attempt) === 0);
    }

    public function saveTyping(ExamAttempt $attempt, string $text): void
    {
        if ($attempt->current_section_key === 'dest' && ! $this->isBreak($attempt) && $this->remaining($attempt) > 0) {
            $attempt->update(['dest_text' => mb_substr($text, 0, 10000)]);
        }
    }
}
