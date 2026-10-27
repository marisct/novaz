<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamPattern;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class XlsxQuestionBankTest extends TestCase
{
    use RefreshDatabase;

    private function upload(User $admin, Course $course, Subject $subject)
    {
        $file = new UploadedFile(base_path('tests/Fixtures/questions.xlsx'), 'Reasoning.xlsx', null, null, true);

        return $this->actingAs($admin)->post('/questions/bulk-upload', [
            'course_id' => $course->id, 'subject_id' => $subject->id, 'file' => $file,
        ])->assertRedirect();
    }

    public function test_an_excel_workbook_uploads_from_its_questions_sheet(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $course = Course::where('code', 'SSC-CGL')->firstOrFail();
        $subject = Subject::where('course_id', $course->id)->firstOrFail();
        $before = Question::count();

        $this->upload($admin, $course, $subject)->assertSessionHas('status',
            "4 questions uploaded for {$subject->name}. 1 skipped because they are already in the question bank."
            .' 1 skipped because the question or the correct answer is missing.');
        $this->assertSame($before + 4, Question::count());

        $first = Question::where('question', 'Next in series 2, 4, 6, ?')->firstOrFail();
        $this->assertSame(['A' => '7', 'B' => '8', 'C' => '9', 'D' => '10', 'E' => 'None of these'], $first->options);
        $this->assertSame('B', $first->correct_answer);
        $this->assertSame('reasoning', $first->section_key);
        $this->assertNull($first->exam_pattern_id);
        $this->assertSame('easy', $first->difficulty);
        $this->assertSame('Add 2 & carry on <ok>', $first->explanation);
        $this->assertEquals(2, $first->marks);

        $this->assertSame('E', Question::where('question', 'Fifth option is right')->firstOrFail()->correct_answer);
        $yesNo = Question::where('question', 'Is 7 a prime number?')->firstOrFail();
        $this->assertSame('yes_no', $yesNo->type);
        $this->assertSame('A', $yesNo->correct_answer);
        $this->assertDatabaseMissing('questions', ['question' => 'Answer points to an empty option']);

        // The same file again adds nothing.
        $this->upload($admin, $course, $subject);
        $this->assertSame($before + 4, Question::count());
    }

    public function test_general_questions_build_a_full_ssc_cgl_tier_1_mock_and_it_is_scored_by_section(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $student = User::where('email', 'student@nonax.test')->firstOrFail();
        $course = Course::where('code', 'SSC-CGL')->firstOrFail();
        $subject = Subject::where('course_id', $course->id)->firstOrFail();
        $pattern = ExamPattern::with('sections')->where('code', 'SSC_CGL_TIER1_2026')->firstOrFail();
        Question::query()->delete();

        // 30 general questions per section, uploaded without choosing a pattern.
        foreach ($pattern->sections as $section) {
            for ($i = 1; $i <= 30; $i++) {
                Question::create([
                    'course_id' => $course->id, 'subject_id' => $subject->id, 'section_key' => $section->key,
                    'type' => 'mcq', 'question' => "{$section->key} question {$i}",
                    'options' => ['A' => 'right', 'B' => 'wrong', 'C' => 'wrong', 'D' => 'wrong', 'E' => 'None of these'],
                    'correct_answer' => 'A', 'marks' => 2, 'status' => 'active',
                ]);
            }
        }

        $this->actingAs($admin)->post('/exams', [
            'course_id' => $course->id, 'exam_pattern_id' => $pattern->id, 'title' => 'Tier 1 Full Mock', 'type' => 'mock_test',
            'duration_minutes' => 60, 'total_questions' => 100, 'passing_marks' => '', 'starts_at' => '', 'ends_at' => '', 'dest_passage' => '',
        ])->assertRedirect()->assertSessionHas('status', 'Official-pattern mock created.');

        $exam = Exam::where('title', 'Tier 1 Full Mock')->firstOrFail();
        $this->assertSame(100, $exam->questions()->count());
        foreach ($pattern->sections as $section) {
            $this->assertSame(25, $exam->questions()->where('section_key', $section->key)->count());
        }

        $this->assertSame('global', $pattern->timer_mode);
        $this->assertSame(60, $exam->duration_minutes);
        $this->assertSame(['quant', 'reasoning', 'english', 'general_awareness'], $pattern->sections->sortBy('position')->pluck('key')->all());

        // The app fetched (and cached) the exam list before the attempt; it must not show a stale status later.
        $token = $this->postJson('/api/login', ['email' => 'student@nonax.test', 'password' => 'password'])->json('token');
        $this->assertSame([], $this->withToken($token)->getJson('/api/exams')->assertOk()->json('data.data.0.attempts'));

        // One 60-minute paper: the student sees all 100 questions and gives one right and one wrong answer per section.
        $this->actingAs($student)->post("/exams/{$exam->id}/start")->assertRedirect();
        $attempt = ExamAttempt::where('exam_id', $exam->id)->firstOrFail();
        $this->actingAs($student)->get("/attempts/{$attempt->id}")->assertOk()
            ->assertSee('quant question')->assertSee('english question')->assertSee('None of these')
            ->assertSeeInOrder(['id="section-quant"', '1. quant question', 'id="section-reasoning"', '26. reasoning question', 'id="section-general_awareness"', '100. general_awareness question'], false);
        $answers = [];
        foreach ($pattern->sections as $section) {
            $ids = $exam->questions()->where('section_key', $section->key)->pluck('questions.id');
            $answers[$ids[0]] = 'A';
            $answers[$ids[1]] = 'B';
        }
        $this->actingAs($student)->post("/attempts/{$attempt->id}", ['answers' => $answers, 'action' => 'continue'])->assertRedirect();

        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame(4, $attempt->correct_count);
        $this->assertSame(4, $attempt->wrong_count);
        $this->assertSame(92, $attempt->skipped_count);
        $this->assertEquals(4 * 2 - 4 * 0.5, $attempt->score);

        // The app lists the newest exam first and sees the student's submitted attempt on it, even though the list was cached.
        $listed = $this->withToken($token)->getJson('/api/exams')->assertOk()->json('data.data');
        $this->assertSame($exam->id, $listed[0]['id']);
        $this->assertSame('submitted', $listed[0]['attempts'][0]['status']);
        $this->assertSame([], $listed[1]['attempts']);
    }
}
