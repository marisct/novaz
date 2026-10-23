<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\StudentProfile;
use App\Services\StudentProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_history_counts_only_personal_submissions_and_renders_on_both_channels(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 9)->startOfDay());
        $this->seed();
        $student = StudentProfile::firstOrFail();
        $exams = Exam::supported()->take(2)->get();
        foreach ($exams as $i => $exam) {
            ExamAttempt::create(['exam_id' => $exam->id, 'student_profile_id' => $student->id,
                'status' => 'submitted', 'submitted_at' => $i ? now()->subWeek() : now(),
                'correct_count' => $i ? 1 : 3, 'wrong_count' => 1, 'score' => $i ? 1 : 3]);
        }
        $service = app(StudentProgressService::class);
        $data = $service->weekly($student);
        $this->assertCount(8, $data['weeks']);
        $this->assertSame(1, $data['current']['tests']);
        $this->assertEquals(75, $data['current']['accuracy']);
        $this->assertEquals(25, $data['accuracy_change']);
        $this->actingAs($student->user)->get('/dashboard')->assertOk()->assertSee('Weekly performance')->assertSee('Exam notifications')
            ->assertSee('My Exams')->assertSee('My Attendance')->assertSee('Study Materials')->assertDontSee('Schedule Exam');
        $token = $this->postJson('/api/login', ['email' => $student->user->email, 'password' => 'password'])->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/home')->assertOk()->assertJsonPath('dashboard.weekly_performance.current.tests', 1);
        $exams[0]->attempts()->update(['status' => 'started']);
        $this->assertSame(0, $service->weekly($student)['current']['tests']);
        $this->travelBack();
    }

    public function test_reminders_exclude_other_courses_drafts_expired_and_completed_exams(): void
    {
        $this->seed();
        $student = StudentProfile::firstOrFail();
        $exam = Exam::supported()->firstOrFail();
        $student->courses()->sync([$exam->course_id]);
        $exam->update(['status' => 'published', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        $service = app(StudentProgressService::class);
        $find = fn () => collect($service->exams($student))->firstWhere('exam_id', $exam->id);
        $this->assertSame('upcoming', $find()['state']);
        $exam->update(['starts_at' => now()->subHour()]);
        $this->assertSame('available', $find()['state']);
        $exam->update(['status' => 'draft']);
        $this->assertNull($find());
        $exam->update(['status' => 'published', 'ends_at' => now()->subMinute()]);
        $this->assertNull($find());
        $exam->update(['ends_at' => now()->addDay()]);
        $student->courses()->detach();
        $this->assertNull($find());
        $student->courses()->attach($exam->course_id);
        ExamAttempt::create(['exam_id' => $exam->id, 'student_profile_id' => $student->id, 'status' => 'submitted', 'submitted_at' => now()]);
        $this->assertNull($find());
    }
}
