<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamPattern;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\SubjectPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Subject $maths;
    private Subject $awareness;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $course = Course::where('code', 'RRB-NTPC')->firstOrFail();
        $pattern = ExamPattern::where('code', 'RRB_NTPC_CBT1')->firstOrFail(); // shared timer: one "continue" submits
        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $this->student = User::where('email', 'student@nonax.test')->firstOrFail();
        $this->maths = Subject::create(['course_id' => $course->id, 'name' => 'Perf Maths', 'code' => 'PERF-M', 'status' => 'active']);
        $this->awareness = Subject::create(['course_id' => $course->id, 'name' => 'Perf Awareness', 'code' => 'PERF-A', 'status' => 'active']);

        $make = fn (Subject $subject, string $section, string $text, string $answer) => Question::create([
            'course_id' => $course->id, 'subject_id' => $subject->id, 'exam_pattern_id' => $pattern->id, 'section_key' => $section,
            'type' => 'mcq', 'question' => $text, 'options' => ['A' => 'one', 'B' => 'two', 'C' => 'three', 'D' => 'four'],
            'correct_answer' => $answer, 'marks' => 1, 'status' => 'active',
        ]);
        $m1 = $make($this->maths, 'mathematics', 'Perf M1', 'A');
        $m2 = $make($this->maths, 'mathematics', 'Perf M2', 'B');
        $a1 = $make($this->awareness, 'general_awareness', 'Perf A1', 'C');
        $a2 = $make($this->awareness, 'general_awareness', 'Perf A2', 'D');

        $exam = Exam::create([
            'course_id' => $course->id, 'exam_pattern_id' => $pattern->id, 'created_by' => $admin->id, 'title' => 'Perf Exam',
            'type' => 'mock_test', 'duration_minutes' => 90, 'total_questions' => 4, 'status' => 'published',
        ]);
        $exam->questions()->sync(collect([$m1, $m2, $a1, $a2])->values()->mapWithKeys(fn ($q, $i) => [$q->id => ['position' => $i + 1, 'marks' => 1]])->all());

        $token = $this->postJson('/api/login', ['email' => 'student@nonax.test', 'password' => 'password'])->json('token');
        $attemptId = $this->withToken($token)->postJson("/api/exams/{$exam->id}/start")->assertOk()->json('attempt.id');
        // Maths: one right, one wrong. Awareness: one right, one skipped.
        $this->withToken($token)->postJson("/api/attempts/{$attemptId}", [
            'answers' => [$m1->id => 'A', $m2->id => 'D', $a1->id => 'C'], 'action' => 'continue',
        ])->assertOk()->assertJsonPath('attempt.status', 'submitted');
    }

    public function test_service_counts_correct_wrong_and_skipped_per_subject(): void
    {
        $rows = app(SubjectPerformanceService::class)->forStudent($this->student->studentProfile)->keyBy('subject');

        $this->assertSame(['tests' => 1, 'questions' => 2, 'attempted' => 2, 'correct' => 1, 'wrong' => 1, 'skipped' => 0],
            collect($rows['Perf Maths'])->only(['tests', 'questions', 'attempted', 'correct', 'wrong', 'skipped'])->all());
        $this->assertSame(50.0, $rows['Perf Maths']['accuracy']);
        $this->assertEqualsWithDelta(0.67, $rows['Perf Maths']['marks'], 0.01); // +1 and -1/3

        $this->assertSame(['tests' => 1, 'questions' => 2, 'attempted' => 1, 'correct' => 1, 'wrong' => 0, 'skipped' => 1],
            collect($rows['Perf Awareness'])->only(['tests', 'questions', 'attempted', 'correct', 'wrong', 'skipped'])->all());
        $this->assertSame(100.0, $rows['Perf Awareness']['accuracy']);

        // A subject of the student's course with no tests yet is listed, with "no answers" rather than 0%.
        $untested = $rows->first(fn ($row) => $row['questions'] === 0);
        $this->assertNotNull($untested);
        $this->assertNull($untested['accuracy']);

        $bySubject = app(SubjectPerformanceService::class)->forSubject($this->maths);
        $this->assertSame('Riya Sharma', $bySubject->first()['name']);
        $this->assertSame(50.0, $bySubject->first()['accuracy']);
    }

    public function test_admin_and_teacher_see_reports_and_students_cannot(): void
    {
        $profileId = $this->student->studentProfile->id;

        foreach (['admin@nonax.test', 'teacher@nonax.test'] as $email) {
            $staff = User::where('email', $email)->firstOrFail();
            $this->actingAs($staff)->get("/students/{$profileId}/performance")
                ->assertOk()->assertSee('Riya Sharma')->assertSee('Perf Maths')->assertSee('50%')->assertSee('Perf Awareness');
            $this->actingAs($staff)->get('/performance?subject_id='.$this->maths->id)
                ->assertOk()->assertSee('Perf Maths')->assertSee('Riya Sharma');
            $this->actingAs($staff)->get('/students')->assertOk()->assertSee('Performance');
        }

        $this->actingAs($this->student)->get("/students/{$profileId}/performance")->assertForbidden();
        $this->actingAs($this->student)->get('/performance')->assertForbidden();
    }

    public function test_student_sees_own_subject_performance_on_dashboard_and_api(): void
    {
        $this->actingAs($this->student)->get('/dashboard')
            ->assertOk()->assertSee('Subject-wise performance')->assertSee('Perf Maths')->assertSee('Perf Awareness');

        $token = $this->postJson('/api/login', ['email' => 'student@nonax.test', 'password' => 'password'])->json('token');
        $this->withToken($token)->getJson('/api/subject-performance')->assertOk()
            ->assertJsonFragment(['subject' => 'Perf Maths', 'correct' => 1, 'wrong' => 1, 'skipped' => 0, 'accuracy' => 50]);
        $this->withToken($token)->getJson('/api/home')->assertOk()->assertJsonPath('dashboard.subject_performance.0.tests', fn ($v) => is_int($v));
        $this->withToken($token)->getJson('/api/management/students/'.$this->student->studentProfile->id.'/performance')->assertForbidden();

        $adminToken = $this->postJson('/api/login', ['email' => 'admin@nonax.test', 'password' => 'password'])->json('token');
        $this->withToken($adminToken)->getJson('/api/management/students/'.$this->student->studentProfile->id.'/performance')
            ->assertOk()->assertJsonFragment(['subject' => 'Perf Awareness', 'skipped' => 1]);
        $this->withToken($adminToken)->getJson('/api/management/subjects/'.$this->maths->id.'/performance')
            ->assertOk()->assertJsonFragment(['name' => 'Riya Sharma', 'correct' => 1]);
    }
}
