<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SSC CGL Tier 1 has one 60-minute clock for all 100 questions, not a locked timer per section.
     */
    public function up(): void
    {
        $patternId = DB::table('exam_patterns')->where('code', 'SSC_CGL_TIER1_2026')->value('id');
        if (! $patternId) {
            return;
        }

        DB::table('exam_patterns')->where('id', $patternId)->update(['timer_mode' => 'global', 'duration_minutes' => 60]);
        foreach (['quant', 'reasoning', 'english', 'general_awareness'] as $index => $key) {
            DB::table('exam_sections')->where('exam_pattern_id', $patternId)->where('key', $key)
                ->update(['position' => $index + 1, 'duration_minutes' => null, 'locks_on_exit' => false]);
        }
    }

    public function down(): void
    {
        $patternId = DB::table('exam_patterns')->where('code', 'SSC_CGL_TIER1_2026')->value('id');
        if (! $patternId) {
            return;
        }

        DB::table('exam_patterns')->where('id', $patternId)->update(['timer_mode' => 'section']);
        foreach (['reasoning', 'general_awareness', 'quant', 'english'] as $index => $key) {
            DB::table('exam_sections')->where('exam_pattern_id', $patternId)->where('key', $key)
                ->update(['position' => $index + 1, 'duration_minutes' => 15, 'locks_on_exit' => true]);
        }
    }
};
