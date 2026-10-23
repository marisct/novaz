<?php

namespace App\Services;

use App\Models\ExamPattern;
use Illuminate\Support\Facades\DB;

class OfficialExamPatterns
{
    public const CODES = ['SSC_CGL_TIER1_2026', 'SSC_CGL_TIER2_PAPER1_2026', 'RRB_NTPC_CBT1_2025', 'RRB_NTPC_CBT2_2025'];

    public function install(): void
    {
        $definitions = [
            // One 60-minute clock for the whole paper; the candidate moves freely between sections.
            ['SSC CGL Tier 1 (2026)', 'global', 60, [
                ['quant', 'Quantitative Aptitude', 25, null, 2, .5, true],
                ['reasoning', 'General Intelligence & Reasoning', 25, null, 2, .5, true],
                ['english', 'English Comprehension', 25, null, 2, .5, true],
                ['general_awareness', 'General Awareness', 25, null, 2, .5, true],
            ]],
            ['SSC CGL Tier 2 Paper 1 (2026)', 'section', 150, [
                ['mathematics', 'Mathematical Abilities', 30, 30, 3, 1, true],
                ['reasoning', 'Reasoning & General Intelligence', 30, 30, 3, 1, true],
                ['english', 'English Language & Comprehension', 45, 40, 3, 1, true],
                ['general_awareness', 'General Awareness', 25, 20, 3, 1, true],
                ['computer', 'Computer Knowledge (qualifying)', 20, 15, 3, 1, false],
                ['dest', 'Session II: Data Entry Speed Test', 0, 15, 0, 0, false],
            ]],
            ['RRB NTPC CBT 1 (CEN 06/2025)', 'global', 90, [
                ['general_awareness', 'General Awareness', 40, null, 1, 1/3, true],
                ['mathematics', 'Mathematics', 30, null, 1, 1/3, true],
                ['reasoning', 'General Intelligence & Reasoning', 30, null, 1, 1/3, true],
            ]],
            ['RRB NTPC CBT 2 (CEN 06/2025)', 'global', 90, [
                ['general_awareness', 'General Awareness', 50, null, 1, 1/3, true],
                ['mathematics', 'Mathematics', 35, null, 1, 1/3, true],
                ['reasoning', 'General Intelligence & Reasoning', 35, null, 1, 1/3, true],
            ]],
        ];
        DB::transaction(function () use ($definitions) {
            foreach ($definitions as $index => [$name, $mode, $duration, $sections]) {
                // Versioned records keep existing exams and historical scores unchanged.
                if (ExamPattern::where('code', self::CODES[$index])->exists()) {
                    continue;
                }
                $pattern = ExamPattern::create([
                    'code' => self::CODES[$index], 'name' => $name, 'timer_mode' => $mode,
                    'duration_minutes' => $duration, 'is_active' => true,
                    'settings' => ['official_mock' => true, 'standard_timing' => true,
                        'rrb_third_penalty' => $index >= 2, 'dest' => $index === 1,
                        'source_url' => $index < 2
                            ? 'https://ssc.gov.in/api/attachment/uploads/masterData/NoticeBoards/Notice_of_adv_cgl_2025.pdf'
                            : 'https://rrbsecunderabad.gov.in/wp-content/uploads/2025/10/Final-CEN-06-2025-21-10-2025-Publish.pdf'],
                ]);
                foreach ($sections as $position => [$key, $label, $count, $minutes, $positive, $negative, $scored]) {
                    $pattern->sections()->create([
                        'key' => $key, 'name' => $label, 'position' => $position + 1,
                        'question_count' => $count, 'duration_minutes' => $minutes,
                        'positive_marks' => $positive, 'negative_marks' => $negative,
                        'locks_on_exit' => $mode === 'section', 'is_scored' => $scored,
                    ]);
                }
            }
        });
    }
}
