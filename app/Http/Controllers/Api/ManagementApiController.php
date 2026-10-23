<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamPattern;
use App\Models\ParentProfile;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Models\StudyMaterial;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\ExamGeneratorService;
use App\Services\SubjectPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManagementApiController extends Controller
{
    public function meta(): array
    {
        return [
            'courses' => Course::with('subjects')->active()->orderBy('name')->get(),
            'subjects' => Subject::with('course')->active()->orderBy('name')->get(),
            'teachers' => TeacherProfile::with('user')->active()->get(),
            'students' => StudentProfile::with('user')->active()->get(),
            'batches' => Batch::with('course')->active()->get(),
            'patterns' => ExamPattern::with('sections')
                ->whereIn('code', Exam::SUPPORTED_PATTERN_CODES)
                ->where('is_active', true)
                ->get(),
        ];
    }

    public function students(Request $request): array
    {
        $this->authorizeStaff($request);

        return ['data' => StudentProfile::with('user', 'courses', 'batches', 'parent')->latest()->paginate($this->perPage($request))
            ->through(fn (StudentProfile $student) => tap($student, fn ($s) => $s->setAttribute('attendance_percent', $s->attendancePercent())))];
    }

    public function storeStudent(Request $request): array
    {
        $this->authorizeStaff($request);
        $data = $this->validateStudent($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'password' => Hash::make($data['password'] ?? 'password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $student = StudentProfile::create([
            'user_id' => $user->id,
            'student_id' => $this->nextStudentId(),
            'admission_number' => $data['admission_number'],
            'gender' => $data['gender'] ?? null,
            'dob' => $data['dob'] ?? null,
            'address' => $data['address'] ?? null,
            'blood_group' => $data['blood_group'] ?? null,
            'aadhaar_number' => $data['aadhaar_number'] ?? null,
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->syncStudentRelations($student, $data);

        return ['message' => 'Student created.', 'data' => $student->load('user', 'courses', 'batches', 'parent')];
    }

    public function updateStudent(Request $request, StudentProfile $student): array
    {
        $this->authorizeStaff($request);
        $data = $this->validateStudent($request, $student);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];
        if (! empty($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }

        $student->user->update($userData);
        $student->update([
            'admission_number' => $data['admission_number'],
            'gender' => $data['gender'] ?? null,
            'dob' => $data['dob'] ?? null,
            'address' => $data['address'] ?? null,
            'blood_group' => $data['blood_group'] ?? null,
            'aadhaar_number' => $data['aadhaar_number'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);
        $this->syncStudentRelations($student, $data);

        return ['message' => 'Student updated.', 'data' => $student->load('user', 'courses', 'batches', 'parent')];
    }

    public function destroyStudent(Request $request, StudentProfile $student): array
    {
        $this->authorizeStaff($request);
        $student->user()->delete();

        return ['message' => 'Student deleted.'];
    }

    public function teachers(Request $request): array
    {
        $this->authorizeAdmin($request);

        return ['data' => TeacherProfile::with('user', 'courses')->latest()->paginate($this->perPage($request))];
    }

    public function storeTeacher(Request $request): array
    {
        $this->authorizeAdmin($request);
        $data = $this->validateTeacher($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'password' => Hash::make($data['password'] ?? 'password'),
            'role' => 'teacher',
            'status' => 'active',
        ]);
        $teacher = TeacherProfile::create([
            'user_id' => $user->id,
            'employee_id' => $data['employee_id'],
            'department' => $data['department'] ?? null,
            'qualification' => $data['qualification'] ?? null,
            'experience_years' => $data['experience_years'] ?? 0,
            'status' => 'active',
        ]);
        $teacher->courses()->sync($data['course_ids'] ?? []);

        return ['message' => 'Teacher created.', 'data' => $teacher->load('user', 'courses')];
    }

    public function updateTeacher(Request $request, TeacherProfile $teacher): array
    {
        $this->authorizeAdmin($request);
        $data = $this->validateTeacher($request, $teacher);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];
        if (! empty($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }
        $teacher->user->update($userData);
        $teacher->update([
            'employee_id' => $data['employee_id'],
            'department' => $data['department'] ?? null,
            'qualification' => $data['qualification'] ?? null,
            'experience_years' => $data['experience_years'] ?? 0,
            'status' => $data['status'] ?? 'active',
        ]);
        $teacher->courses()->sync($data['course_ids'] ?? []);

        return ['message' => 'Teacher updated.', 'data' => $teacher->load('user', 'courses')];
    }

    public function destroyTeacher(Request $request, TeacherProfile $teacher): array
    {
        $this->authorizeAdmin($request);
        $teacher->user()->delete();

        return ['message' => 'Teacher deleted.'];
    }

    public function courses(Request $request): array
    {
        return ['data' => Course::withCount(['subjects', 'students', 'teachers'])->with('subjects')->latest()->paginate($this->perPage($request))];
    }

    public function storeCourse(Request $request): array
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:40', 'unique:courses,code'],
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'string', 'max:100'],
        ]);

        $course = Course::create([
            'name' => $data['name'],
            'code' => Str::upper($data['code']),
            'description' => $data['description'] ?? null,
            'duration' => $data['duration'] ?? null,
            'status' => 'active',
        ]);

        return ['message' => 'Course created.', 'data' => $course->load('subjects')];
    }

    public function updateCourse(Request $request, Course $course): array
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:40', Rule::unique('courses', 'code')->ignore($course->id)],
            'description' => ['nullable', 'string'],
            'duration' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $course->update([
            'name' => $data['name'],
            'code' => Str::upper($data['code']),
            'description' => $data['description'] ?? null,
            'duration' => $data['duration'] ?? null,
            'status' => $data['status'],
        ]);

        return ['message' => 'Course updated.', 'data' => $course->refresh()->load('subjects')];
    }

    public function destroyCourse(Request $request, Course $course): array
    {
        $this->authorizeAdmin($request);
        $course->delete();

        return ['message' => 'Course deleted.'];
    }

    public function subjects(Request $request): array
    {
        return ['data' => Subject::with('course', 'teacher.user')->withCount(['materials', 'questions'])->latest()->paginate($this->perPage($request))];
    }

    public function storeSubject(Request $request): array
    {
        $this->authorizeAdmin($request);
        $data = $this->validateSubject($request);
        $course = Course::findOrFail($data['course_id']);
        $subject = Subject::create([
            'course_id' => $course->id,
            'teacher_profile_id' => $data['teacher_profile_id'] ?? null,
            'name' => $data['name'],
            'code' => Str::upper($data['code'] ?: Str::slug($course->code.'-'.$data['name'].'-'.($course->subjects()->count() + 1))),
            'status' => 'active',
        ]);

        return ['message' => 'Subject created.', 'data' => $subject->load('course', 'teacher.user')];
    }

    public function updateSubject(Request $request, Subject $subject): array
    {
        $this->authorizeAdmin($request);
        $data = $this->validateSubject($request, $subject);
        $subject->update([
            'course_id' => $data['course_id'],
            'teacher_profile_id' => $data['teacher_profile_id'] ?? null,
            'name' => $data['name'],
            'code' => Str::upper($data['code'] ?: $subject->code),
            'status' => $data['status'] ?? 'active',
        ]);

        return ['message' => 'Subject updated.', 'data' => $subject->refresh()->load('course', 'teacher.user')];
    }

    public function destroySubject(Request $request, Subject $subject): array
    {
        $this->authorizeAdmin($request);
        $subject->delete();

        return ['message' => 'Subject deleted.'];
    }

    public function questions(Request $request): array
    {
        $this->authorizeStaff($request);
        $query = Question::with('course', 'subject', 'pattern')->latest();
        $query->when($request->integer('course_id'), fn ($q, $id) => $q->where('course_id', $id));
        $query->when($request->integer('subject_id'), fn ($q, $id) => $q->where('subject_id', $id));

        return ['data' => $query->paginate($this->perPage($request))];
    }

    public function storeQuestion(Request $request): array
    {
        $this->authorizeStaff($request);
        $data = $this->validateQuestion($request);
        $question = Question::create($data + ['status' => $data['status'] ?? 'active']);

        return ['message' => 'Question created.', 'data' => $question->load('course', 'subject', 'pattern')];
    }

    public function updateQuestion(Request $request, Question $question): array
    {
        $this->authorizeStaff($request);
        $data = $this->validateQuestion($request);
        $question->update($data);

        return ['message' => 'Question updated.', 'data' => $question->refresh()->load('course', 'subject', 'pattern')];
    }

    public function destroyQuestion(Request $request, Question $question): array
    {
        $this->authorizeStaff($request);
        $question->delete();

        return ['message' => 'Question deleted.'];
    }

    public function exams(Request $request): array
    {
        $query = Exam::supported()->with('course', 'creator', 'pattern.sections')->latest();
        if ($request->user()->role !== 'student') $query->with('questions');
        if ($request->user()->role === 'student') {
            $courseIds = $request->user()->studentProfile?->courses()->pluck('courses.id') ?? collect();
            $query->whereIn('course_id', $courseIds)->where('status', 'published');
        }

        return ['data' => $query->paginate($this->perPage($request))];
    }

    public function storeExam(Request $request, ExamGeneratorService $generator): array
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'dest_passage' => ['nullable', 'string', 'max:2100'],
            'course_id' => ['required', 'exists:courses,id'],
            'exam_pattern_id' => ['required', 'exists:exam_patterns,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:daily_quiz,weekly_test,monthly_test,mock_test'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'passing_marks' => ['nullable', 'numeric', 'min:0'],
            'question_ids' => ['nullable', 'array'],
            'question_ids.*' => ['exists:questions,id'],
        ]);
        $pattern = ExamPattern::with('sections')->whereIn('code', Exam::SUPPORTED_PATTERN_CODES)->findOrFail($data['exam_pattern_id']);
        if ($pattern->settings['official_mock'] ?? false) {
            $exam = $generator->createOfficial($data, $request->user()->id);
            return ['message' => 'Official-pattern mock created.', 'data' => $exam->load('course', 'pattern.sections')];
        }
        $exam = Exam::create($data + [
            'created_by' => $request->user()->id,
            'status' => 'published',
            'duration_minutes' => $pattern->duration_minutes ?? (int) $pattern->sections->sum('duration_minutes'),
            'total_questions' => (int) $pattern->sections->sum('question_count'),
            'negative_marking' => $pattern->sections->contains(fn ($section) => (float) $section->negative_marks > 0),
        ]);
        if (! empty($data['question_ids'])) {
            $questions = Question::where('course_id', $exam->course_id)->whereIn('id', $data['question_ids'])->get();
            $exam->questions()->sync($questions->values()->mapWithKeys(fn (Question $question, int $index) => [
                $question->id => ['position' => $index + 1, 'marks' => $question->marks],
            ])->all());
            $exam->forceFill(['total_questions' => $questions->count()])->save();
        } else {
            $generator->attachBalancedQuestions($exam, (int) $exam->total_questions);
        }

        return ['message' => 'Exam created.', 'data' => $exam->load('course', 'pattern.sections', 'questions')];
    }

    public function updateExam(Request $request, Exam $exam): array
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:daily_quiz,weekly_test,monthly_test,mock_test'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'passing_marks' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:draft,published,archived'],
        ]);
        $exam->update($data);

        return ['message' => 'Exam updated.', 'data' => $exam->refresh()->load('course', 'pattern.sections', 'questions')];
    }

    public function destroyExam(Request $request, Exam $exam): array
    {
        $this->authorizeStaff($request);
        $exam->delete();

        return ['message' => 'Exam deleted.'];
    }

    public function attendance(Request $request): array
    {
        $query = Attendance::with('student.user', 'student.courses')->latest('attendance_date');
        if ($request->user()->role === 'student') {
            $query->where('student_profile_id', $request->user()->studentProfile?->id);
        }
        $query->when($request->integer('course_id'), fn ($q, $id) => $q->where('course_id', $id));
        $query->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status));

        return ['data' => $query->paginate($this->perPage($request))];
    }

    public function storeAttendance(Request $request): array
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'student_profile_id' => ['required', 'exists:student_profiles,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,late,leave'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);
        $attendance = Attendance::updateOrCreate(
            collect($data)->only(['student_profile_id', 'course_id', 'attendance_date'])->all(),
            $data + ['marked_by' => $request->user()->id]
        );

        return ['message' => 'Attendance saved.', 'data' => $attendance->load('student.user')];
    }

    public function materials(Request $request): array
    {
        $query = StudyMaterial::with('course', 'subject', 'uploader')->latest();
        if ($request->user()->role === 'student') {
            $courseIds = $request->user()->studentProfile?->courses()->pluck('courses.id') ?? collect();
            $query->whereIn('course_id', $courseIds)->where('status', 'published');
        }

        return ['data' => $query->paginate($this->perPage($request))];
    }

    public function storeMaterial(Request $request): array
    {
        $this->authorizeStaff($request);
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:note,pdf,word,previous_question'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);
        $file = $request->file('file');
        $path = $file ? $file->store('materials', 'public') : '';
        $material = StudyMaterial::create($data + [
            'uploaded_by' => $request->user()->id,
            'file_path' => $path,
            'file_name' => $file?->getClientOriginalName() ?? '',
            'mime_type' => $file?->getMimeType(),
            'size_bytes' => $file?->getSize() ?? 0,
            'status' => 'published',
        ]);

        return ['message' => 'Material saved.', 'data' => $material->load('course', 'subject')];
    }

    public function destroyMaterial(Request $request, StudyMaterial $material): array
    {
        $this->authorizeStaff($request);
        if ($material->file_path) {
            Storage::disk('public')->delete($material->file_path);
        }
        $material->delete();

        return ['message' => 'Material deleted.'];
    }

    private function validateStudent(Request $request, ?StudentProfile $student = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($student?->user_id)],
            'mobile' => ['nullable', 'string', 'max:30'],
            'password' => [$student ? 'nullable' : 'required', 'string', 'min:6'],
            'admission_number' => ['required', 'string', 'max:255', Rule::unique('student_profiles', 'admission_number')->ignore($student?->id)],
            'gender' => ['nullable', 'string', 'max:30'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'aadhaar_number' => ['nullable', 'string', 'max:30'],
            'course_id' => ['required', 'exists:courses,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'parent_mobile' => ['nullable', 'string', 'max:30'],
            'parent_email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }

    private function validateTeacher(Request $request, ?TeacherProfile $teacher = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($teacher?->user_id)],
            'mobile' => ['nullable', 'string', 'max:30'],
            'password' => [$teacher ? 'nullable' : 'required', 'string', 'min:6'],
            'employee_id' => ['required', 'string', 'max:255', Rule::unique('teacher_profiles', 'employee_id')->ignore($teacher?->id)],
            'department' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['exists:courses,id'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }

    private function validateSubject(Request $request, ?Subject $subject = null): array
    {
        return $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40', Rule::unique('subjects', 'code')->ignore($subject?->id)],
            'teacher_profile_id' => ['nullable', 'exists:teacher_profiles,id'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }

    private function validateQuestion(Request $request): array
    {
        return $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'exam_pattern_id' => ['nullable', 'exists:exam_patterns,id'],
            'section_key' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'in:english,hindi'],
            'type' => ['required', 'in:mcq,yes_no'],
            'question' => ['required', 'string'],
            'options' => ['nullable', 'array'],
            'correct_answer' => ['required', 'string', 'max:255'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'marks' => ['nullable', 'numeric', 'min:0'],
            'negative_marks' => ['nullable', 'numeric', 'min:0'],
            'explanation' => ['nullable', 'string'],
            'topic' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }

    private function syncStudentRelations(StudentProfile $student, array $data): void
    {
        ParentProfile::updateOrCreate(['student_profile_id' => $student->id], [
            'father_name' => $data['father_name'] ?? null,
            'mother_name' => $data['mother_name'] ?? null,
            'guardian_name' => $data['guardian_name'] ?? null,
            'mobile' => $data['parent_mobile'] ?? null,
            'email' => $data['parent_email'] ?? null,
            'address' => $data['address'] ?? null,
        ]);
        $student->courses()->sync([$data['course_id']]);
        empty($data['batch_id']) ? $student->batches()->detach() : $student->batches()->sync([$data['batch_id']]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->role === 'super_admin', 403, 'Only admins can do this.');
    }

    public function studentPerformance(Request $request, StudentProfile $student, SubjectPerformanceService $performance): array
    {
        $this->authorizeStaff($request);

        return ['student' => $student->load('user'), 'data' => $performance->forStudent($student)];
    }

    public function subjectPerformance(Request $request, Subject $subject, SubjectPerformanceService $performance): array
    {
        $this->authorizeStaff($request);

        return ['subject' => $subject->load('course'), 'data' => $performance->forSubject($subject)];
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['super_admin', 'teacher'], true), 403, 'Only staff can do this.');
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 20), 1), 100);
    }

    private function nextStudentId(): string
    {
        return 'STD-'.str_pad((string) (StudentProfile::count() + 1001), 4, '0', STR_PAD_LEFT);
    }
}
