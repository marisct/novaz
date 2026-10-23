<?php

namespace Tests\Feature;

use App\Models\{Exam, ExamAttempt, ExamPattern, Question, StudentProfile, Subject};
use App\Services\{ExamGeneratorService, OfficialExamPatterns};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficialExamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $this->seed();
    }

    private function paper(string $code): Exam
    {
        $student = StudentProfile::firstOrFail();
        $course = $student->courses()->firstOrFail();
        $subject = Subject::where('course_id', $course->id)->firstOrFail();
        $pattern = ExamPattern::with('sections')->where('code', $code)->firstOrFail();
        foreach ($pattern->sections as $section) {
            for ($i = 0; $i < $section->question_count; $i++) {
                Question::create(['course_id' => $course->id, 'subject_id' => $subject->id,
                    'exam_pattern_id' => $pattern->id, 'section_key' => $section->key,
                    'question' => "{$section->key} question {$i}", 'type' => 'mcq',
                    'options' => ['A' => 'One', 'B' => 'Two', 'C' => 'Three', 'D' => 'Four'],
                    'correct_answer' => 'A', 'marks' => 99, 'negative_marks' => 99, 'status' => 'active']);
            }
        }
        return app(ExamGeneratorService::class)->createOfficial([
            'course_id' => $course->id, 'exam_pattern_id' => $pattern->id,
            'title' => 'Full official mock', 'type' => 'mock_test', 'dest_passage' => str_repeat('Practice typing. ', 120),
        ], $student->user_id);
    }

    private function token(): string
    {
        return $this->postJson('/api/login', ['email' => 'student@nonax.test', 'password' => 'password'])->assertOk()->json('token');
    }

    public function test_catalog_matches_the_four_standard_patterns_and_installs_idempotently(): void
    {
        $before = ExamPattern::count();
        app(OfficialExamPatterns::class)->install();
        $this->assertSame($before, ExamPattern::count());
        foreach (array_combine(OfficialExamPatterns::CODES, [[100,60], [150,150], [100,90], [120,90]]) as $code => [$count,$minutes]) {
            $pattern = ExamPattern::with('sections')->where('code', $code)->firstOrFail();
            $this->assertEquals($count, $pattern->sections->sum('question_count'));
            $this->assertEquals($minutes, $pattern->duration_minutes);
            if ($pattern->timer_mode === 'section') $this->assertEquals($minutes, $pattern->sections->sum('duration_minutes'));
        }
    }

    public function test_incomplete_or_mixed_papers_cannot_publish_or_leave_orphan_exams(): void
    {
        $pattern = ExamPattern::where('code', OfficialExamPatterns::CODES[0])->firstOrFail();
        $count = Exam::count();
        try {
            app(ExamGeneratorService::class)->createOfficial(['exam_pattern_id' => $pattern->id, 'course_id' => Subject::first()->course_id, 'title' => 'Incomplete'], 1);
            $this->fail('Incomplete exam accepted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('requires 25', $e->getMessage());
        }
        $this->assertSame($count, Exam::count());
    }

    public function test_ssc_tier_one_runs_on_one_sixty_minute_clock_that_consumes_offline_time(): void
    {
        $exam = $this->paper(OfficialExamPatterns::CODES[0]);
        $this->withToken($this->token());
        $start = $this->postJson("/api/exams/{$exam->id}/start")->assertOk()->assertJsonCount(100, 'questions')->assertJsonPath('remaining_seconds', 3600)->json();
        $id = $start['attempt']['id'];
        $question = $start['questions'][0];
        $this->assertArrayNotHasKey('correct_answer', $question);
        $this->assertArrayNotHasKey('questions', $start['exam']);
        $this->assertArrayNotHasKey('exam', $start['attempt']);
        $this->getJson('/api/management/questions')->assertForbidden();
        $listed = $this->getJson('/api/management/exams')->assertOk()->json('data.data');
        foreach ($listed as $item) $this->assertArrayNotHasKey('questions', $item);
        // Any section can be answered at any time; time away from the app still counts.
        $other = collect($start['questions'])->firstWhere('section_key', 'general_awareness');
        $this->postJson("/api/attempts/{$id}", ['action' => 'save', 'answers' => [$question['id'] => 'A', $other['id'] => 'B']])->assertOk();
        $this->travel(16)->minutes();
        $this->getJson("/api/attempts/{$id}")->assertOk()->assertJsonCount(100, 'questions')->assertJsonPath('remaining_seconds', 2640);
        $this->travel(45)->minutes();
        $this->getJson("/api/attempts/{$id}")->assertOk()->assertJsonPath('status', 'result')->assertJsonPath('attempt.score', '1.50');
        $this->postJson("/api/attempts/{$id}", ['action' => 'save'])->assertConflict();
    }

    public function test_rrb_uses_global_navigation_and_exact_one_third_penalty(): void
    {
        $exam = $this->paper(OfficialExamPatterns::CODES[2]);
        $this->withToken($this->token());
        $start = $this->postJson("/api/exams/{$exam->id}/start")->assertOk()->assertJsonCount(100, 'questions')->assertJsonPath('remaining_seconds', 5400)->json();
        $answers = collect($start['questions'])->groupBy('section_key')->mapWithKeys(fn ($rows) => [$rows[0]['id'] => 'B'])->all();
        $this->postJson('/api/attempts/'.$start['attempt']['id'], ['action' => 'continue', 'answers' => $answers])->assertOk()->assertJsonPath('attempt.score', '-1.00')->assertJsonPath('attempt.wrong_count', 3);
    }

    public function test_tier_two_has_separate_typing_session_and_qualifying_computer_score_on_both_channels(): void
    {
        $exam = $this->paper(OfficialExamPatterns::CODES[1]);
        $student = StudentProfile::firstOrFail();
        $this->actingAs($student->user)->post("/exams/{$exam->id}/start")->assertRedirect();
        $attempt = ExamAttempt::where('exam_id', $exam->id)->firstOrFail();
        $this->get("/attempts/{$attempt->id}")->assertOk()->assertSee('Mathematical Abilities');
        $this->travel(120)->minutes();
        $this->withToken($this->token());
        $computer = $this->getJson("/api/attempts/{$attempt->id}")->assertOk()->assertJsonPath('attempt.current_section_key', 'computer')->assertJsonCount(20, 'questions')->json('questions');
        $this->postJson("/api/attempts/{$attempt->id}", ['action' => 'save', 'section_key' => 'computer', 'answers' => [$computer[0]['id'] => 'A', $computer[1]['id'] => 'B']])->assertOk();
        $this->travel(15)->minutes();
        $this->getJson("/api/attempts/{$attempt->id}")->assertOk()->assertJsonPath('session_break', true);
        $this->get("/attempts/{$attempt->id}")->assertOk()->assertSee('Start typing session');
        $this->travel(10)->minutes();
        $this->postJson("/api/attempts/{$attempt->id}", ['action' => 'start_dest'])->assertOk()->assertJsonPath('remaining_seconds', 900);
        $this->postJson("/api/attempts/{$attempt->id}", ['action' => 'save', 'dest_text' => 'Practice typing.'])->assertOk();
        $this->travel(15)->minutes();
        $this->getJson("/api/attempts/{$attempt->id}")->assertOk()->assertJsonPath('status', 'result')->assertJsonPath('attempt.score', '0.00')->assertJsonPath('section_scores.computer.score', 2)->assertJsonPath('section_scores.dest.characters_typed', 16);
        $this->get("/attempts/{$attempt->id}/result")->assertOk()->assertSee('Typing practice');
    }
}
