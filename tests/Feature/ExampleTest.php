<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_open_dashboard(): void
    {
        $this->seed();

        $this->post('/login', [
            'email' => 'admin@nonax.test',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Super admin Dashboard', false);
    }

    public function test_student_cannot_open_admin_course_management(): void
    {
        $this->seed();

        $student = User::where('email', 'student@nonax.test')->firstOrFail();

        $this->actingAs($student)->get('/courses')->assertForbidden();
    }

    public function test_api_login_returns_bearer_token_for_mobile_clients(): void
    {
        $this->seed();

        $token = $this->postJson('/api/login', [
            'email' => 'student@nonax.test',
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('user.email', 'student@nonax.test');
    }

    public function test_admin_can_create_course_and_student(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();

        $this->actingAs($admin)->post('/courses', [
            'name' => 'Foundation Flow',
            'code' => 'FLOW-T',
            'duration' => '6 months',
            'description' => 'Feature test course.',
        ])->assertRedirect();

        $this->assertDatabaseHas('courses', ['code' => 'FLOW-T']);

        $courseId = \App\Models\Course::where('code', 'FLOW-T')->value('id');

        $this->actingAs($admin)->post('/students', [
            'name' => 'Feature Student',
            'email' => 'feature.student@nonax.test',
            'password' => 'password',
            'admission_number' => 'ADM-FEATURE-1',
            'course_id' => $courseId,
            'father_name' => 'Feature Parent',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'feature.student@nonax.test', 'role' => 'student']);
        $this->assertDatabaseHas('student_profiles', ['admission_number' => 'ADM-FEATURE-1']);
        $this->assertDatabaseHas('parent_profiles', ['father_name' => 'Feature Parent']);
    }

    public function test_admin_creates_subject_then_uploads_subject_note(): void
    {
        Storage::fake('public');
        $this->seed();

        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $course = \App\Models\Course::where('code', 'SSC-CGL')->firstOrFail();

        $this->actingAs($admin)->post("/courses/{$course->id}/subjects", [
            'name' => 'Feature Physics',
            'code' => 'FEATURE-PHY',
        ])->assertRedirect();

        $subject = \App\Models\Subject::where('code', 'FEATURE-PHY')->firstOrFail();

        $this->actingAs($admin)->post("/subjects/{$subject->id}/materials", [
            'title' => 'Feature Motion Notes',
            'type' => 'pdf',
            'file' => UploadedFile::fake()->create('motion.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseHas('study_materials', [
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'title' => 'Feature Motion Notes',
            'type' => 'pdf',
        ]);
    }

    public function test_admin_can_open_subjects_page_and_create_subject_from_popup_form(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $course = \App\Models\Course::where('code', 'SSC-CGL')->firstOrFail();

        $this->actingAs($admin)->get('/subjects')
            ->assertOk()
            ->assertSee('Subjects')
            ->assertSee('Add Subject')
            ->assertSee($course->name);

        $this->actingAs($admin)->post('/subjects', [
            'course_id' => $course->id,
            'name' => 'Feature Biology',
            'code' => 'FEATURE-BIO',
        ])->assertRedirect();

        $this->assertDatabaseHas('subjects', [
            'course_id' => $course->id,
            'name' => 'Feature Biology',
            'code' => 'FEATURE-BIO',
        ]);
    }

    public function test_admin_can_create_teacher(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $courseId = \App\Models\Course::where('code', 'SSC-CGL')->value('id');

        $this->actingAs($admin)->post('/teachers', [
            'name' => 'Feature Teacher',
            'email' => 'feature.teacher@nonax.test',
            'password' => 'password',
            'employee_id' => 'EMP-FEATURE-1',
            'department' => 'Science',
            'qualification' => 'M.Sc',
            'experience_years' => 5,
            'course_ids' => [$courseId],
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'feature.teacher@nonax.test', 'role' => 'teacher']);
        $this->assertDatabaseHas('teacher_profiles', ['employee_id' => 'EMP-FEATURE-1']);
        $this->assertDatabaseHas('course_teacher', ['course_id' => $courseId]);
    }

    public function test_admin_can_update_student_teacher_course_and_subject(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $student = \App\Models\StudentProfile::with('user', 'parent')->firstOrFail();
        $teacher = \App\Models\TeacherProfile::with('user')->firstOrFail();
        $course = \App\Models\Course::where('code', 'SSC-CGL')->firstOrFail();
        $subject = \App\Models\Subject::where('course_id', $course->id)->firstOrFail();

        $this->actingAs($admin)->put("/students/{$student->id}", [
            'name' => 'Updated Student',
            'email' => $student->user->email,
            'mobile' => '9000000001',
            'admission_number' => $student->admission_number,
            'course_id' => $course->id,
            'gender' => 'male',
            'father_name' => 'Updated Father',
            'status' => 'active',
        ])->assertRedirect();

        $this->actingAs($admin)->put("/teachers/{$teacher->id}", [
            'name' => 'Updated Teacher',
            'email' => $teacher->user->email,
            'mobile' => '9000000002',
            'employee_id' => $teacher->employee_id,
            'department' => 'Updated Dept',
            'qualification' => 'M.Ed',
            'experience_years' => 8,
            'course_ids' => [$course->id],
            'status' => 'active',
        ])->assertRedirect();

        $this->actingAs($admin)->put("/courses/{$course->id}", [
            'name' => 'Updated Course',
            'code' => $course->code,
            'duration' => '12 months',
            'description' => 'Updated course description.',
            'status' => 'active',
        ])->assertRedirect();

        $this->actingAs($admin)->put("/subjects/{$subject->id}", [
            'course_id' => $course->id,
            'name' => 'Updated Subject',
            'code' => $subject->code,
            'teacher_profile_id' => $teacher->id,
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $student->user_id, 'name' => 'Updated Student']);
        $this->assertDatabaseHas('parent_profiles', ['student_profile_id' => $student->id, 'father_name' => 'Updated Father']);
        $this->assertDatabaseHas('users', ['id' => $teacher->user_id, 'name' => 'Updated Teacher']);
        $this->assertDatabaseHas('teacher_profiles', ['id' => $teacher->id, 'department' => 'Updated Dept']);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'name' => 'Updated Course']);
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'name' => 'Updated Subject', 'teacher_profile_id' => $teacher->id]);
    }

    public function test_admin_can_delete_student_teacher_course_and_subject(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $course = \App\Models\Course::create([
            'name' => 'Delete Flow Course',
            'code' => 'DELETE-FLOW',
            'status' => 'active',
        ]);
        $subject = \App\Models\Subject::create([
            'course_id' => $course->id,
            'name' => 'Delete Flow Subject',
            'code' => 'DELETE-SUB',
            'status' => 'active',
        ]);

        $studentUser = User::create([
            'name' => 'Delete Student',
            'email' => 'delete.student@nonax.test',
            'password' => 'password',
            'role' => 'student',
            'status' => 'active',
        ]);
        $student = \App\Models\StudentProfile::create([
            'user_id' => $studentUser->id,
            'student_id' => 'STD-DELETE',
            'admission_number' => 'ADM-DELETE',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $teacherUser = User::create([
            'name' => 'Delete Teacher',
            'email' => 'delete.teacher@nonax.test',
            'password' => 'password',
            'role' => 'teacher',
            'status' => 'active',
        ]);
        $teacher = \App\Models\TeacherProfile::create([
            'user_id' => $teacherUser->id,
            'employee_id' => 'EMP-DELETE',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->delete("/students/{$student->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/teachers/{$teacher->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/subjects/{$subject->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/courses/{$course->id}")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $studentUser->id]);
        $this->assertDatabaseMissing('student_profiles', ['id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $teacherUser->id]);
        $this->assertDatabaseMissing('teacher_profiles', ['id' => $teacher->id]);
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_admin_can_search_and_filter_attendance(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $student = \App\Models\StudentProfile::with('user')->firstOrFail();
        $course = \App\Models\Course::firstOrFail();

        \App\Models\Attendance::create([
            'student_profile_id' => $student->id,
            'course_id' => $course->id,
            'marked_by' => $admin->id,
            'attendance_date' => '2026-06-26',
            'status' => 'present',
            'remarks' => 'Search visible record',
        ]);

        $this->actingAs($admin)->get('/attendance?search='.urlencode($student->user->name).'&status=present&attendance_date=2026-06-26')
            ->assertOk()
            ->assertSee('Search visible record')
            ->assertSee('Reset')
            ->assertSee('Student name, ID, admission, email, mobile');

        $this->actingAs($admin)->get('/attendance?search=not-found-attendance-student')
            ->assertOk()
            ->assertSee('No attendance records found.');
    }

    public function test_teacher_bulk_uploads_questions_and_creates_exam_from_selected_questions(): void
    {
        $this->seed();

        $teacher = User::where('email', 'teacher@nonax.test')->firstOrFail();
        $course = \App\Models\Course::where('code', 'SSC-CGL')->firstOrFail();
        $subject = \App\Models\Subject::where('course_id', $course->id)->firstOrFail();
        $patternId = \App\Models\ExamPattern::where('code', 'SSC_CGL_TIER1')->value('id');

        $file = UploadedFile::fake()->createWithContent('questions.csv', implode("\n", [
            'question,option_a,option_b,option_c,option_d,correct_answer,difficulty,marks,explanation,topic,type',
            '"What is force?","Push or pull","Cell","Atom","Plant","A","easy","2","Force explanation","Mechanics","mcq"',
            '"Is water wet?","Yes","No","","","A","easy","1","Simple yes/no","Basics","yes_no"',
        ]));

        $this->actingAs($teacher)->post('/questions/bulk-upload', [
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'file' => $file,
        ])->assertRedirect();

        $this->assertDatabaseHas('questions', ['question' => 'What is force?', 'subject_id' => $subject->id]);
        $questionIds = \App\Models\Question::whereIn('question', ['What is force?', 'Is water wet?'])->pluck('id')->all();

        $this->actingAs($teacher)->post('/exams', [
            'course_id' => $course->id,
            'exam_pattern_id' => $patternId,
            'title' => 'Selected Question Exam',
            'type' => 'mock_test',
            'duration_minutes' => 30,
            'total_questions' => 10,
            'question_ids' => $questionIds,
        ])->assertRedirect();

        $exam = \App\Models\Exam::where('title', 'Selected Question Exam')->firstOrFail();

        $this->assertSame(2, $exam->questions()->count());
        $this->assertSame(2, $exam->total_questions);
    }

    public function test_question_template_download_and_yes_no_upload_works_for_student_exam_flow(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nonax.test')->firstOrFail();
        $student = User::where('email', 'student@nonax.test')->firstOrFail();
        $course = \App\Models\Course::where('code', 'SSC-CGL')->firstOrFail();
        $subject = \App\Models\Subject::firstOrCreate(
            ['code' => 'SSC-UPLOAD-FLOW'],
            ['course_id' => $course->id, 'name' => 'SSC Upload Flow', 'status' => 'active']
        );
        $pattern = \App\Models\ExamPattern::where('code', 'SSC_CGL_TIER1')->firstOrFail();

        $this->actingAs($admin)->get('/questions/template')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->assertSee('question')
            ->assertSee('yes_no')
            ->assertSee('answer');

        $file = UploadedFile::fake()->createWithContent('yes-no-upload.xls', implode("\n", [
            "question\ttype\toption_a\toption_b\toption_c\toption_d\tanswer\tsection\tlanguage\tdifficulty\tmarks\texplanation\ttopic",
            "The Constitution of India came into force in 1950.\tyes_no\tYes\tNo\t\t\tYes\treasoning\tenglish\teasy\t2\tIt came into force on 26 January 1950.\tPolity",
        ]));

        $this->actingAs($admin)->post('/questions/bulk-upload', [
            'course_id' => $course->id,
            'subject_id' => $subject->id,
            'exam_pattern_id' => $pattern->id,
            'section_key' => 'reasoning',
            'language' => 'english',
            'file' => $file,
        ])->assertRedirect();

        $question = \App\Models\Question::where('question', 'The Constitution of India came into force in 1950.')->firstOrFail();
        $this->assertSame('yes_no', $question->type);
        $this->assertSame('A', $question->correct_answer);
        $this->assertSame('Yes', $question->options['A']);
        $this->assertSame('No', $question->options['B']);

        $this->actingAs($admin)->post('/exams', [
            'course_id' => $course->id,
            'exam_pattern_id' => $pattern->id,
            'title' => 'Uploaded Yes No Student Flow',
            'type' => 'mock_test',
            'duration_minutes' => 60,
            'total_questions' => 1,
            'question_ids' => [$question->id],
        ])->assertRedirect();

        $exam = \App\Models\Exam::where('title', 'Uploaded Yes No Student Flow')->firstOrFail();
        $this->actingAs($student)->post("/exams/{$exam->id}/start")->assertRedirect();
        $attempt = \App\Models\ExamAttempt::where('exam_id', $exam->id)
            ->where('student_profile_id', $student->studentProfile->id)
            ->firstOrFail();

        $this->actingAs($student)->get("/attempts/{$attempt->id}")
            ->assertOk()
            ->assertSee('Yes')
            ->assertSee('No');

        $this->actingAs($student)->post("/attempts/{$attempt->id}", [
            'answers' => [$question->id => 'A'],
            'action' => 'continue',
        ])->assertRedirect();
        $this->actingAs($student)->post("/attempts/{$attempt->id}", ['action' => 'continue'])->assertRedirect();
        $this->actingAs($student)->post("/attempts/{$attempt->id}", ['action' => 'continue'])->assertRedirect();
        $this->actingAs($student)->post("/attempts/{$attempt->id}", ['action' => 'continue'])->assertRedirect();

        $this->assertSame('submitted', $attempt->refresh()->status);
        $this->assertSame('2.00', $attempt->score);
    }

    public function test_student_can_open_read_only_learning_pages(): void
    {
        $this->seed();

        $student = User::where('email', 'student@nonax.test')->firstOrFail();

        $this->actingAs($student)->get('/exams')->assertOk()->assertDontSee('Create</button>', false);
        $this->actingAs($student)->get('/attendance')->assertOk()->assertDontSee('Save</button>', false);
        $this->actingAs($student)->get('/materials')->assertOk()->assertDontSee('Upload</button>', false);
    }

    public function test_ssc_section_timer_flow_locks_sections_and_scores_from_pattern(): void
    {
        $this->seed();

        $student = User::where('email', 'student@nonax.test')->firstOrFail();
        $exam = \App\Models\Exam::whereHas('pattern', fn ($query) => $query->where('code', 'SSC_CGL_TIER1'))->firstOrFail();

        $this->actingAs($student)->post("/exams/{$exam->id}/start")->assertRedirect();
        $attempt = \App\Models\ExamAttempt::where('exam_id', $exam->id)->firstOrFail();
        $reasoning = $exam->questions()->where('section_key', 'reasoning')->get();

        $this->actingAs($student)->post("/attempts/{$attempt->id}", [
            'answers' => [
                $reasoning[0]->id => $reasoning[0]->correct_answer,
                $reasoning[1]->id => $reasoning[1]->correct_answer === 'A' ? 'B' : 'A',
            ],
            'action' => 'continue',
        ])->assertRedirect();

        $attempt->refresh();
        $this->assertContains('reasoning', $attempt->locked_sections);
        $this->assertSame('general_awareness', $attempt->current_section_key);

        $this->actingAs($student)->post("/attempts/{$attempt->id}", ['action' => 'continue'])->assertRedirect();
        $this->actingAs($student)->post("/attempts/{$attempt->id}", ['action' => 'continue'])->assertRedirect();
        $this->actingAs($student)->post("/attempts/{$attempt->id}", ['action' => 'continue'])->assertRedirect();

        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame('1.50', $attempt->score);
        $this->assertArrayHasKey('reasoning', $attempt->section_scores);
        $this->actingAs($student)->get("/attempts/{$attempt->id}/result")
            ->assertOk()
            ->assertSee('Section Breakdown')
            ->assertSee('Answer Review');
    }

    public function test_rrb_scoring_uses_one_third_negative_marking_and_rounds_total(): void
    {
        $this->seed();

        $student = User::where('email', 'student@nonax.test')->firstOrFail();
        $exam = \App\Models\Exam::whereHas('pattern', fn ($query) => $query->where('code', 'RRB_NTPC_CBT1'))->firstOrFail();
        $this->actingAs($student)->post("/exams/{$exam->id}/start");
        $attempt = \App\Models\ExamAttempt::where('exam_id', $exam->id)->firstOrFail();
        $questions = $exam->questions()->take(2)->get();

        $this->actingAs($student)->post("/attempts/{$attempt->id}", [
            'answers' => [
                $questions[0]->id => $questions[0]->correct_answer,
                $questions[1]->id => $questions[1]->correct_answer === 'A' ? 'B' : 'A',
            ],
            'action' => 'continue',
        ])->assertRedirect();

        $this->assertSame('0.67', $attempt->refresh()->score);
    }
}
