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
use Tests\TestCase;

class UnsectionedQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_questions_without_a_section_are_shown_and_scored_in_section_timed_exams(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $student = User::where('email', 'student@nonax.test')->firstOrFail();
        $course = Course::where('code', 'SSC-CGL')->firstOrFail();
        $subject = Subject::where('course_id', $course->id)->firstOrFail();
        $pattern = ExamPattern::where('code', 'SSC_CGL_TIER1')->firstOrFail();

        $plain = Question::create([
            'course_id' => $course->id, 'subject_id' => $subject->id, 'type' => 'mcq',
            'question' => 'Unsectioned: 6 x 7?', 'options' => ['A' => '42', 'B' => '36', 'C' => '48', 'D' => '56'],
            'correct_answer' => 'A', 'marks' => 2, 'negative_marks' => 0.5, 'status' => 'active',
        ]);
        $quant = Question::create([
            'course_id' => $course->id, 'subject_id' => $subject->id, 'exam_pattern_id' => $pattern->id,
            'section_key' => 'quant', 'type' => 'mcq',
            'question' => 'Sectioned: 9 x 9?', 'options' => ['A' => '72', 'B' => '81', 'C' => '90', 'D' => '99'],
            'correct_answer' => 'B', 'marks' => 2, 'negative_marks' => 0.5, 'status' => 'active',
        ]);

        $this->actingAs($admin)->post('/exams', [
            'course_id' => $course->id, 'exam_pattern_id' => $pattern->id, 'title' => 'Mixed Section Exam',
            'type' => 'mock_test', 'duration_minutes' => 60, 'total_questions' => 2,
            'question_ids' => [$plain->id, $quant->id],
        ])->assertRedirect();
        $exam = Exam::where('title', 'Mixed Section Exam')->firstOrFail();

        $this->actingAs($student)->post("/exams/{$exam->id}/start")->assertRedirect();
        $attempt = ExamAttempt::where('exam_id', $exam->id)->firstOrFail();

        // First section: the unsectioned question is visible, the quant one is not yet.
        $this->actingAs($student)->get("/attempts/{$attempt->id}")
            ->assertOk()
            ->assertSee('Unsectioned: 6 x 7?')
            ->assertDontSee('Sectioned: 9 x 9?');

        $this->actingAs($student)->post("/attempts/{$attempt->id}", [
            'answers' => [$plain->id => 'A'], 'action' => 'continue',
        ])->assertRedirect();
        $this->actingAs($student)->post("/attempts/{$attempt->id}", ['action' => 'continue'])->assertRedirect();

        // Third section (quant): only the sectioned question shows.
        $this->actingAs($student)->get("/attempts/{$attempt->id}")
            ->assertOk()
            ->assertSee('Sectioned: 9 x 9?')
            ->assertDontSee('Unsectioned: 6 x 7?');

        $this->actingAs($student)->post("/attempts/{$attempt->id}", [
            'answers' => [$quant->id => 'B'], 'action' => 'continue',
        ])->assertRedirect();
        $this->actingAs($student)->post("/attempts/{$attempt->id}", ['action' => 'continue'])->assertRedirect();

        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame(2, $attempt->correct_count);
        $this->assertSame('4.00', $attempt->score);
    }

    public function test_mobile_api_also_returns_unsectioned_questions_in_the_first_section(): void
    {
        $this->seed();

        $course = Course::where('code', 'SSC-CGL')->firstOrFail();
        $subject = Subject::where('course_id', $course->id)->firstOrFail();
        $pattern = ExamPattern::where('code', 'SSC_CGL_TIER1')->firstOrFail();
        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();

        $plain = Question::create([
            'course_id' => $course->id, 'subject_id' => $subject->id, 'type' => 'mcq',
            'question' => 'API unsectioned?', 'options' => ['A' => 'Yes', 'B' => 'No', 'C' => 'Maybe', 'D' => 'Never'],
            'correct_answer' => 'A', 'marks' => 1, 'status' => 'active',
        ]);
        $exam = Exam::create([
            'course_id' => $course->id, 'exam_pattern_id' => $pattern->id, 'created_by' => $admin->id,
            'title' => 'API Unsectioned Exam', 'type' => 'mock_test', 'duration_minutes' => 60,
            'total_questions' => 1, 'status' => 'published',
        ]);
        $exam->questions()->sync([$plain->id => ['position' => 1, 'marks' => 1]]);

        $token = $this->postJson('/api/login', ['email' => 'student@nonax.test', 'password' => 'password'])->json('token');

        $start = $this->withToken($token)->postJson("/api/exams/{$exam->id}/start")->assertOk()->json();
        $this->assertSame([$plain->id], collect($start['questions'])->pluck('id')->all());

        $attemptId = $start['attempt']['id'];
        $this->withToken($token)->postJson("/api/attempts/{$attemptId}", [
            'answers' => [$plain->id => 'A'], 'action' => 'continue',
        ])->assertOk();

        $this->assertDatabaseHas('student_answers', ['exam_attempt_id' => $attemptId, 'question_id' => $plain->id, 'answer' => 'A']);
    }
}
