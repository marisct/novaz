<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamPattern;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScheduleAndMaterialsTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $email): string
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => 'password'])->json('token');
    }

    private function exam(string $title, string $type, array $extra = []): Exam
    {
        $course = Course::where('code', 'SSC-CGL')->firstOrFail();

        return Exam::create($extra + [
            'course_id' => $course->id, 'exam_pattern_id' => ExamPattern::where('code', 'SSC_CGL_TIER1')->value('id'),
            'created_by' => User::where('email', 'admin@nonax.test')->value('id'), 'title' => $title, 'type' => $type,
            'duration_minutes' => 60, 'total_questions' => 1, 'status' => 'published',
        ]);
    }

    public function test_demo_data_has_no_neet_course_or_science_subjects(): void
    {
        $this->seed();

        $this->assertNull(Course::where('code', 'NEET')->first());
        $this->assertSame(0, Subject::whereIn('name', ['Physics', 'Chemistry', 'Biology', 'Botany', 'Zoology'])->count());
        $this->assertNotNull(Course::where('code', 'SSC-CGL')->first());
        $this->assertTrue(User::where('email', 'student@nonax.test')->first()->studentProfile->courses()->where('code', 'SSC-CGL')->exists());
    }

    public function test_scheduled_exam_cannot_be_started_before_it_opens_or_after_it_closes(): void
    {
        $this->seed();
        $student = User::where('email', 'student@nonax.test')->firstOrFail();
        $upcoming = $this->exam('Opens Later', 'weekly_test', ['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(3)]);
        $closed = $this->exam('Already Closed', 'weekly_test', ['starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);

        $this->assertSame('upcoming', $upcoming->state);
        $this->assertSame('closed', $closed->state);

        // Web: the list shows the opening time instead of a start button, and starting is refused.
        $this->actingAs($student)->get('/exams')->assertOk()->assertSee('Opens '.$upcoming->starts_at->format('d M, H:i'))->assertSee('Closed');
        $this->actingAs($student)->post("/exams/{$upcoming->id}/start")->assertRedirect('/exams')->assertSessionHasErrors('exam');
        $this->actingAs($student)->post("/exams/{$closed->id}/start")->assertRedirect('/exams')->assertSessionHasErrors('exam');
        $this->assertSame(0, ExamAttempt::count());

        // API: refused with 422 and a clear message.
        $token = $this->token('student@nonax.test');
        $this->withToken($token)->postJson("/api/exams/{$upcoming->id}/start")->assertStatus(422);
        $this->withToken($token)->postJson("/api/exams/{$closed->id}/start")->assertStatus(422);

        // Once the window opens, the same exam can be started.
        $this->travelTo(now()->addDays(2)->addMinute());
        $this->assertSame('available', $upcoming->fresh()->state);
        $this->withToken($token)->postJson("/api/exams/{$upcoming->id}/start")->assertOk()->assertJsonPath('status', 'attempt');
    }

    public function test_app_exam_list_shows_every_test_type_with_its_state(): void
    {
        $this->seed();
        $this->exam('Daily One', 'daily_quiz');
        $this->exam('Weekly One', 'weekly_test', ['starts_at' => now()->addDay()]);
        $token = $this->token('student@nonax.test');

        $all = $this->withToken($token)->getJson('/api/exams')->assertOk()->json('data.data');
        $titles = collect($all)->pluck('title');
        $this->assertTrue($titles->contains('Daily One'));
        $this->assertTrue($titles->contains('Weekly One'));
        $this->assertTrue($titles->contains('SSC CGL Tier 1 Practice Demo'));
        $this->assertSame('upcoming', collect($all)->firstWhere('title', 'Weekly One')['state']);
        $this->assertSame('available', collect($all)->firstWhere('title', 'Daily One')['state']);

        // The mock-test list still holds only mock tests.
        $mock = collect($this->withToken($token)->getJson('/api/mock-test')->assertOk()->json('data.data'))->pluck('type')->unique()->all();
        $this->assertSame(['mock_test'], $mock);
    }

    public function test_previous_year_papers_have_their_own_tab_and_api_with_download_link(): void
    {
        Storage::fake('public');
        $this->seed();
        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $student = User::where('email', 'student@nonax.test')->firstOrFail();
        $course = Course::where('code', 'SSC-CGL')->firstOrFail();
        $subject = Subject::where('course_id', $course->id)->firstOrFail();

        $this->actingAs($admin)->post('/materials', [
            'title' => 'SSC CGL Tier 1 2024 Shift 1', 'course_id' => $course->id, 'subject_id' => $subject->id,
            'type' => 'previous_question', 'year' => 2024, 'file' => UploadedFile::fake()->create('paper-2024.pdf', 120, 'application/pdf'),
        ])->assertRedirect();
        $this->actingAs($admin)->post('/materials', [
            'title' => 'Algebra Notes', 'course_id' => $course->id, 'subject_id' => $subject->id,
            'type' => 'note', 'file' => UploadedFile::fake()->create('algebra.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        // Web tabs: papers grouped by year on one tab, notes on the other.
        $this->actingAs($student)->get('/materials?tab=previous')->assertOk()
            ->assertSee('Previous Year Question Papers')->assertSee('2024')->assertSee('SSC CGL Tier 1 2024 Shift 1')->assertDontSee('Algebra Notes');
        $this->actingAs($student)->get('/materials')->assertOk()->assertSee('Algebra Notes')->assertDontSee('SSC CGL Tier 1 2024 Shift 1');
        $this->actingAs($student)->get('/dashboard')->assertOk()->assertSee('Previous Papers');

        // API: previous papers endpoint with a signed download link that needs no token.
        $token = $this->token('student@nonax.test');
        $papers = $this->withToken($token)->getJson('/api/previous-papers')->assertOk()->json('data.data');
        $this->assertCount(1, $papers);
        $this->assertSame(2024, (int) $papers[0]['year']);
        $this->assertStringContainsString('/materials/', $papers[0]['download_url']);
        $this->get($papers[0]['download_url'])->assertOk();
        $this->get(str_replace('signature=', 'signature=x', $papers[0]['download_url']))->assertForbidden();

        $notes = collect($this->withToken($token)->getJson('/api/notes')->assertOk()->json('data.data'))->pluck('title')->all();
        $this->assertSame(['Algebra Notes'], $notes);
    }

    public function test_attendance_percentage_is_shown_to_admin_and_student(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $student = User::where('email', 'student@nonax.test')->firstOrFail();
        $profile = $student->studentProfile;
        $course = Course::where('code', 'SSC-CGL')->firstOrFail();

        foreach ([['2026-09-01', 'present'], ['2026-09-02', 'late'], ['2026-09-03', 'absent'], ['2026-09-04', 'absent']] as [$date, $status]) {
            Attendance::create(['student_profile_id' => $profile->id, 'course_id' => $course->id, 'marked_by' => $admin->id, 'attendance_date' => $date, 'status' => $status]);
        }

        $this->assertSame(50.0, $profile->attendancePercent());
        $this->actingAs($admin)->get('/students')->assertOk()->assertSee('Attendance')->assertSee('50%');
        $this->actingAs($admin)->get("/students/{$profile->id}/performance")->assertOk()->assertSee('50%');
        $this->actingAs($student)->get('/dashboard')->assertOk()->assertSee('50%');

        $this->withToken($this->token('admin@nonax.test'))->getJson('/api/management/students')->assertOk()
            ->assertJsonFragment(['attendance_percent' => 50]);
        $this->withToken($this->token('student@nonax.test'))->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('attendance_percent', 50);
    }
}
