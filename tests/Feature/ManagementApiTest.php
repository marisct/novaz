<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_core_records_through_mobile_api(): void
    {
        $this->seed();
        $token = $this->apiToken('admin@nonax.test');

        $courseId = $this->withToken($token)->postJson('/api/management/courses', [
            'name' => 'API Course',
            'code' => 'api-course',
            'duration' => '6 months',
            'description' => 'Created from Flutter API.',
        ])->assertOk()
            ->assertJsonPath('data.code', 'API-COURSE')
            ->json('data.id');

        $subjectId = $this->withToken($token)->postJson('/api/management/subjects', [
            'course_id' => $courseId,
            'name' => 'API Subject',
            'code' => 'API-SUB',
        ])->assertOk()->json('data.id');

        $this->withToken($token)->postJson('/api/management/students', [
            'name' => 'API Student',
            'email' => 'api.student@nonax.test',
            'password' => 'password',
            'admission_number' => 'ADM-API-1',
            'course_id' => $courseId,
            'father_name' => 'API Parent',
        ])->assertOk()
            ->assertJsonPath('data.user.email', 'api.student@nonax.test');

        $questionId = $this->withToken($token)->postJson('/api/management/questions', [
            'course_id' => $courseId,
            'subject_id' => $subjectId,
            'type' => 'mcq',
            'question' => 'API question?',
            'options' => ['A' => 'One', 'B' => 'Two', 'C' => 'Three', 'D' => 'Four'],
            'correct_answer' => 'A',
        ])->assertOk()->json('data.id');

        $this->assertDatabaseHas('courses', ['id' => $courseId, 'code' => 'API-COURSE']);
        $this->assertDatabaseHas('subjects', ['id' => $subjectId, 'code' => 'API-SUB']);
        $this->assertDatabaseHas('questions', ['id' => $questionId, 'question' => 'API question?']);

        $this->withToken($token)->getJson('/api/management/courses')
            ->assertOk()
            ->assertJsonFragment(['code' => 'API-COURSE']);
    }

    public function test_student_cannot_write_management_records(): void
    {
        $this->seed();
        $token = $this->apiToken('student@nonax.test');
        $course = Course::firstOrFail();

        $this->withToken($token)->postJson('/api/management/subjects', [
            'course_id' => $course->id,
            'name' => 'Blocked Subject',
        ])->assertForbidden();
    }

    public function test_teacher_can_manage_questions_and_attendance_through_mobile_api(): void
    {
        $this->seed();
        $token = $this->apiToken('teacher@nonax.test');
        $course = Course::where('code', 'SSC-CGL')->firstOrFail();
        $subject = Subject::where('course_id', $course->id)->firstOrFail();
        $student = StudentProfile::firstOrFail();

        $this->withToken($token)->postJson('/api/management/questions', [
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'type' => 'yes_no',
            'question' => 'API yes no?',
            'options' => ['A' => 'Yes', 'B' => 'No'],
            'correct_answer' => 'A',
        ])->assertOk();

        $this->withToken($token)->postJson('/api/management/attendance', [
            'student_profile_id' => $student->id,
            'course_id' => $course->id,
            'attendance_date' => '2026-07-14',
            'status' => 'present',
            'remarks' => 'Marked from API',
        ])->assertOk();

        $this->assertDatabaseHas('questions', ['question' => 'API yes no?']);
        $this->assertDatabaseHas('attendances', ['remarks' => 'Marked from API']);
    }

    public function test_student_can_take_exam_through_mobile_api(): void
    {
        $this->seed();
        $token = $this->apiToken('student@nonax.test');
        $exam = \App\Models\Exam::whereHas('pattern', fn ($query) => $query->where('code', 'RRB_NTPC_CBT1'))->firstOrFail();

        $start = $this->withToken($token)
            ->postJson("/api/exams/{$exam->id}/start")
            ->assertOk()
            ->assertJsonPath('status', 'attempt')
            ->json();

        $attemptId = $start['attempt']['id'];
        $question = collect($start['questions'])->first();

        $this->withToken($token)->postJson("/api/attempts/{$attemptId}", [
            'answers' => [$question['id'] => Question::findOrFail($question['id'])->correct_answer],
            'action' => 'continue',
        ])->assertOk()
            ->assertJsonPath('status', 'result')
            ->assertJsonPath('attempt.status', 'submitted');
    }

    private function apiToken(string $email): string
    {
        return $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk()->json('token');
    }
}
