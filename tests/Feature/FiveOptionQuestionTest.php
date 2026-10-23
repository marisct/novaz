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

class FiveOptionQuestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_format_has_an_option_e_column_with_a_five_option_example(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();

        $body = $this->actingAs($admin)->get('/questions/template')->assertOk()->getContent();
        $lines = explode("\r\n", ltrim($body, "\xEF\xBB\xBF"));
        $headers = explode("\t", $lines[0]);

        $this->assertSame(['question', 'type', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'answer'], array_slice($headers, 0, 8));
        foreach (array_slice($lines, 1) as $line) {
            $this->assertCount(count($headers), explode("\t", $line), 'every example row has one value per column');
        }
        $this->assertStringContainsString("\t243\tE\t", $body);
    }

    public function test_questions_upload_with_option_e_and_students_can_answer_e(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $student = User::where('email', 'student@nonax.test')->firstOrFail();
        $course = Course::where('code', 'RRB-NTPC')->firstOrFail();
        $subject = Subject::where('course_id', $course->id)->firstOrFail();
        $pattern = ExamPattern::where('code', 'RRB_NTPC_CBT1')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent('five.xls', implode("\n", [
            "question\ttype\toption_a\toption_b\toption_c\toption_d\toption_e\tanswer\tsection\tlanguage\tdifficulty\tmarks\texplanation\ttopic",
            "Five options, E is right?\tmcq\t10\t20\t30\t40\tNone of these\tE\tmathematics\tenglish\teasy\t1\tBecause.\tTopic",
            "Five options, answer given as text?\tmcq\tRed\tBlue\tGreen\tPink\tBlack\tBlack\tmathematics\tenglish\teasy\t1\tBecause.\tTopic",
            "Only four options?\tmcq\t1\t2\t3\t4\t\tB\tmathematics\tenglish\teasy\t1\tBecause.\tTopic",
        ]));

        $this->actingAs($admin)->post('/questions/bulk-upload', [
            'course_id' => $course->id, 'subject_id' => $subject->id, 'exam_pattern_id' => $pattern->id, 'file' => $file,
        ])->assertRedirect()->assertSessionHas('status', "3 questions uploaded for {$subject->name}.");

        $five = Question::where('question', 'Five options, E is right?')->firstOrFail();
        $this->assertSame(['A' => '10', 'B' => '20', 'C' => '30', 'D' => '40', 'E' => 'None of these'], $five->options);
        $this->assertSame('E', $five->correct_answer);

        $byText = Question::where('question', 'Five options, answer given as text?')->firstOrFail();
        $this->assertSame('E', $byText->correct_answer);

        $four = Question::where('question', 'Only four options?')->firstOrFail();
        $this->assertSame(['A', 'B', 'C', 'D'], array_keys($four->options));

        $this->actingAs($admin)->post('/exams', [
            'course_id' => $course->id, 'exam_pattern_id' => $pattern->id, 'title' => 'Five Option Exam', 'type' => 'mock_test',
            'duration_minutes' => 90, 'total_questions' => 3, 'question_ids' => [$five->id, $byText->id, $four->id],
        ])->assertRedirect();
        $exam = Exam::where('title', 'Five Option Exam')->firstOrFail();

        $this->actingAs($student)->post("/exams/{$exam->id}/start")->assertRedirect();
        $attempt = ExamAttempt::where('exam_id', $exam->id)->firstOrFail();

        // The exam page offers option E with its text.
        $this->actingAs($student)->get("/attempts/{$attempt->id}")->assertOk()->assertSee('None of these')->assertSee('Black');

        $this->actingAs($student)->post("/attempts/{$attempt->id}", [
            'answers' => [$five->id => 'E', $byText->id => 'A', $four->id => 'E'], // last one: E does not exist, must be ignored
            'action' => 'continue',
        ])->assertRedirect();

        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame(1, $attempt->correct_count);
        $this->assertSame(1, $attempt->wrong_count);
        $this->assertSame(1, $attempt->skipped_count);
        $this->assertDatabaseHas('student_answers', ['exam_attempt_id' => $attempt->id, 'question_id' => $five->id, 'answer' => 'E', 'is_correct' => true]);
        $this->assertDatabaseMissing('student_answers', ['exam_attempt_id' => $attempt->id, 'question_id' => $four->id]);

        // Mobile API accepts five options on create and returns them to the student.
        $adminToken = $this->postJson('/api/login', ['email' => 'admin@nonax.test', 'password' => 'password'])->json('token');
        $this->withToken($adminToken)->postJson('/api/management/questions', [
            'course_id' => $course->id, 'subject_id' => $subject->id, 'type' => 'mcq', 'question' => 'API five options?',
            'options' => ['A' => '1', 'B' => '2', 'C' => '3', 'D' => '4', 'E' => '5'], 'correct_answer' => 'E', 'marks' => 1,
        ])->assertOk()->assertJsonPath('data.options.E', '5');
    }
}
