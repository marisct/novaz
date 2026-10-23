<?php

namespace App\Http\Controllers;

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
use App\Services\XlsxReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InstituteController extends Controller
{
    public function students(): View
    {
        return view('lists.students', [
            'students' => StudentProfile::with('user', 'courses', 'batches', 'parent')->latest()->paginate(15),
            'courses' => Course::active()->get(),
            'batches' => Batch::active()->get(),
        ]);
    }

    public function storeStudent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
            'admission_number' => ['required', 'string', 'max:255', 'unique:student_profiles,admission_number'],
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
        ]);

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

        ParentProfile::create([
            'student_profile_id' => $student->id,
            'father_name' => $data['father_name'] ?? null,
            'mother_name' => $data['mother_name'] ?? null,
            'guardian_name' => $data['guardian_name'] ?? null,
            'mobile' => $data['parent_mobile'] ?? null,
            'email' => $data['parent_email'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        $student->courses()->sync([$data['course_id']]);
        if (! empty($data['batch_id'])) {
            $student->batches()->sync([$data['batch_id']]);
        }

        return back()->with('status', 'Student created. Login password is '.($data['password'] ?? 'password').'.');
    }

    public function updateStudent(Request $request, StudentProfile $student): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$student->user_id],
            'mobile' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
            'admission_number' => ['required', 'string', 'max:255', 'unique:student_profiles,admission_number,'.$student->id],
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
            'status' => ['required', 'in:active,inactive'],
        ]);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'status' => $data['status'],
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
            'status' => $data['status'],
        ]);

        $student->courses()->sync([$data['course_id']]);
        empty($data['batch_id']) ? $student->batches()->detach() : $student->batches()->sync([$data['batch_id']]);

        ParentProfile::updateOrCreate(
            ['student_profile_id' => $student->id],
            [
                'father_name' => $data['father_name'] ?? null,
                'mother_name' => $data['mother_name'] ?? null,
                'guardian_name' => $data['guardian_name'] ?? null,
                'mobile' => $data['parent_mobile'] ?? null,
                'email' => $data['parent_email'] ?? null,
                'address' => $data['address'] ?? null,
            ]
        );

        return back()->with('status', 'Student updated.');
    }

    public function destroyStudent(StudentProfile $student): RedirectResponse
    {
        $student->user()->delete();

        return back()->with('status', 'Student deleted.');
    }

    public function studentPerformance(StudentProfile $student, SubjectPerformanceService $performance): View
    {
        return view('performance.student', [
            'student' => $student->load('user', 'courses'),
            'rows' => $performance->forStudent($student),
        ]);
    }

    public function subjectPerformance(Request $request, SubjectPerformanceService $performance): View
    {
        $subjects = Subject::with('course')->orderBy('course_id')->orderBy('name')->get();
        $subject = $request->filled('subject_id') ? $subjects->firstWhere('id', (int) $request->input('subject_id')) : null;

        return view('performance.index', [
            'subjects' => $subjects,
            'subject' => $subject,
            'rows' => $subject ? $performance->forSubject($subject) : collect(),
        ]);
    }

    public function teachers(): View
    {
        return view('lists.teachers', [
            'teachers' => TeacherProfile::with('user', 'courses')->latest()->paginate(15),
            'courses' => Course::active()->get(),
        ]);
    }

    public function storeTeacher(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
            'employee_id' => ['required', 'string', 'max:255', 'unique:teacher_profiles,employee_id'],
            'department' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['exists:courses,id'],
        ]);

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

        return back()->with('status', 'Teacher created. Login password is '.($data['password'] ?? 'password').'.');
    }

    public function updateTeacher(Request $request, TeacherProfile $teacher): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$teacher->user_id],
            'mobile' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:6'],
            'employee_id' => ['required', 'string', 'max:255', 'unique:teacher_profiles,employee_id,'.$teacher->id],
            'department' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['exists:courses,id'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'status' => $data['status'],
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
            'status' => $data['status'],
        ]);
        $teacher->courses()->sync($data['course_ids'] ?? []);

        return back()->with('status', 'Teacher updated.');
    }

    public function destroyTeacher(TeacherProfile $teacher): RedirectResponse
    {
        $teacher->user()->delete();

        return back()->with('status', 'Teacher deleted.');
    }

    public function courses(): View
    {
        return view('lists.courses', ['courses' => Course::withCount(['subjects', 'students', 'teachers'])->latest()->paginate(15)]);
    }

    public function subjects(): View
    {
        return view('lists.subjects', [
            'subjects' => Subject::with('course', 'teacher.user')
                ->withCount(['materials', 'questions'])
                ->latest()
                ->paginate(15),
            'courses' => Course::active()->get(),
            'teachers' => TeacherProfile::with('user')->active()->get(),
        ]);
    }

    public function showCourse(Course $course): View
    {
        return view('courses.show', [
            'course' => $course->load(['subjects' => fn ($query) => $query->latest()]),
            'materials' => StudyMaterial::with('subject')
                ->where('course_id', $course->id)
                ->latest()
                ->paginate(20),
        ]);
    }

    public function storeCourse(Request $request): RedirectResponse
    {
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

        return redirect()->route('courses.show', $course)->with('status', 'Course created. Add subjects inside this course.');
    }

    public function updateCourse(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:40', 'unique:courses,code,'.$course->id],
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

        return back()->with('status', 'Course updated.');
    }

    public function destroyCourse(Course $course): RedirectResponse
    {
        $course->delete();

        return back()->with('status', 'Course deleted.');
    }

    public function storeStandaloneSubject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40', 'unique:subjects,code'],
            'teacher_profile_id' => ['nullable', 'exists:teacher_profiles,id'],
        ]);

        $course = Course::findOrFail($data['course_id']);
        $this->createSubject($course, $data);

        return back()->with('status', 'Subject created.');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40', 'unique:subjects,code,'.$subject->id],
            'teacher_profile_id' => ['nullable', 'exists:teacher_profiles,id'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $course = Course::findOrFail($data['course_id']);
        $subject->update([
            'course_id' => $course->id,
            'teacher_profile_id' => $data['teacher_profile_id'] ?? null,
            'name' => $data['name'],
            'code' => Str::upper($data['code'] ?: $subject->code),
            'status' => $data['status'],
        ]);

        return back()->with('status', 'Subject updated.');
    }

    public function destroySubject(Subject $subject): RedirectResponse
    {
        $subject->delete();

        return back()->with('status', 'Subject deleted.');
    }

    public function storeSubject(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40', 'unique:subjects,code'],
            'teacher_profile_id' => ['nullable', 'exists:teacher_profiles,id'],
        ]);

        $this->createSubject($course, $data);

        return back()->with('status', 'Subject created.');
    }

    public function storeSubjectMaterial(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:pdf,word'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('materials/'.$subject->course_id.'/'.$subject->id, 'public');

        StudyMaterial::create($data + [
            'course_id' => $subject->course_id,
            'subject_id' => $subject->id,
            'uploaded_by' => $request->user()->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return back()->with('status', 'Subject note uploaded.');
    }

    public function exams(): View
    {
        $query = Exam::supported()->with('course', 'creator', 'pattern')->latest();

        if (request()->user()->role === 'student') {
            $courseIds = request()->user()->studentProfile?->courses()->pluck('courses.id') ?? collect();
            $query->whereIn('course_id', $courseIds)->where('status', 'published');
        }

        $questionQuery = Question::with('course', 'subject')->latest();
        if ($courseId = request('course_id')) {
            $questionQuery->where('course_id', $courseId);
        }
        if ($subjectId = request('subject_id')) {
            $questionQuery->where('subject_id', $subjectId);
        }

        return view('lists.exams', [
            'exams' => $query->paginate(15),
            'courses' => Course::with('subjects')->active()->get(),
            'patterns' => ExamPattern::with('sections')
                ->whereIn('code', Exam::SUPPORTED_PATTERN_CODES)
                ->where('is_active', true)
                ->get(),
            'questions' => $questionQuery->paginate(25)->withQueryString(),
            'selectedCourseId' => request('course_id'),
            'selectedSubjectId' => request('subject_id'),
        ]);
    }

    public function storeExam(Request $request, ExamGeneratorService $generator): RedirectResponse
    {
        $data = $request->validate([
            'dest_passage' => ['nullable', 'string', 'max:2100'],
            'course_id' => ['required', 'exists:courses,id'],
            'exam_pattern_id' => ['required', 'exists:exam_patterns,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:daily_quiz,weekly_test,monthly_test,mock_test'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'total_questions' => ['required', 'integer', 'min:1'],
            'passing_marks' => ['nullable', 'numeric', 'min:0'],
            'negative_marking' => ['nullable', 'boolean'],
            'question_ids' => ['nullable', 'array'],
            'question_ids.*' => ['exists:questions,id'],
        ]);

        $pattern = ExamPattern::with('sections')
            ->whereIn('code', Exam::SUPPORTED_PATTERN_CODES)
            ->findOrFail($data['exam_pattern_id']);
        if ($pattern->settings['official_mock'] ?? false) {
            $generator->createOfficial($data, $request->user()->id);
            return back()->with('status', 'Official-pattern mock created.');
        }
        $data['duration_minutes'] = $pattern->duration_minutes
            ?? (int) $pattern->sections->sum('duration_minutes');
        $data['total_questions'] = (int) $pattern->sections->sum('question_count');
        $data['negative_marking'] = $pattern->sections->contains(fn ($section) => (float) $section->negative_marks > 0);

        $exam = Exam::create($data + ['created_by' => $request->user()->id, 'status' => 'published']);

        if (! empty($data['question_ids'])) {
            $questions = Question::where('course_id', $exam->course_id)
                ->whereIn('id', $data['question_ids'])
                ->get();

            $exam->questions()->sync(
                $questions->values()->mapWithKeys(fn (Question $question, int $index) => [
                    $question->id => ['position' => $index + 1, 'marks' => $question->marks],
                ])->all()
            );

            $exam->forceFill(['total_questions' => $questions->count()])->save();
        } else {
            $generator->attachBalancedQuestions($exam, (int) $data['total_questions']);
        }

        return back()->with('status', ! empty($data['question_ids']) ? 'Exam created with selected questions.' : 'Exam created with balanced random questions.');
    }

    public function bulkUploadQuestions(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'exam_pattern_id' => ['nullable', 'exists:exam_patterns,id'],
            'section_key' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'in:english,hindi'],
            'file' => ['required', 'file', 'extensions:csv,txt,xls,xlsx', 'max:10240'],
        ]);

        $subject = Subject::where('course_id', $data['course_id'])->findOrFail($data['subject_id']);
        @set_time_limit(300);
        try {
            $rows = $this->readQuestionRows($request->file('file')->getRealPath());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        // Questions already in this course, so uploading the same file twice adds nothing.
        $seen = [];
        Question::where('course_id', $data['course_id'])->toBase()->select('question', 'options')->orderBy('id')
            ->each(function ($existing) use (&$seen) {
                $seen[md5($existing->question.'|'.$existing->options)] = true;
            }, 2000);

        $now = now();
        $batch = [];
        $created = $invalid = $duplicates = 0;

        foreach ($rows as $row) {
            $row = $this->normalizeQuestionRow($row);
            if (blank($row['question'] ?? null) && blank($row['correct_answer'] ?? null)) {
                continue;
            }
            $options = json_encode([
                'A' => $row['option_a'] ?? null,
                'B' => $row['option_b'] ?? null,
                'C' => $row['option_c'] ?? null,
                'D' => $row['option_d'] ?? null,
            ] + (filled($row['option_e'] ?? null) ? ['E' => $row['option_e']] : []));
            // The answer must be one of the options that is actually filled in.
            if (blank($row['question'] ?? null) || blank($row['option_'.strtolower($row['correct_answer'])] ?? null)) {
                $invalid++;
                continue;
            }
            $hash = md5($row['question'].'|'.$options);
            if (isset($seen[$hash])) {
                $duplicates++;
                continue;
            }
            $seen[$hash] = true;

            $batch[] = [
                'course_id' => $data['course_id'],
                'subject_id' => $subject->id,
                'exam_pattern_id' => $data['exam_pattern_id'] ?? null,
                'section_key' => $data['section_key'] ?? (filled($row['section'] ?? null) ? Str::of($row['section'])->trim()->lower()->replace([' ', '-'], '_')->toString() : null),
                'language' => in_array($language = strtolower($row['language'] ?? ''), ['english', 'hindi'], true) ? $language : ($data['language'] ?? 'english'),
                'type' => $row['type'],
                'question' => $row['question'],
                'options' => $options,
                'correct_answer' => $row['correct_answer'],
                'difficulty' => in_array($difficulty = strtolower($row['difficulty'] ?? ''), ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium',
                'marks' => (float) (($row['marks'] ?? null) ?: 1),
                'explanation' => $row['explanation'] ?? null,
                'topic' => filled($row['topic'] ?? null) ? Str::limit($row['topic'], 250, '') : null,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $created++;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($batch) {
            foreach (array_chunk($batch, 200) as $chunk) {
                Question::insert($chunk);
            }
        });

        $message = "{$created} questions uploaded for {$subject->name}.";
        if ($duplicates) {
            $message .= " {$duplicates} skipped because they are already in the question bank.";
        }
        if ($invalid) {
            $message .= " {$invalid} skipped because the question or the correct answer is missing.";
        }

        return back()->with('status', $message);
    }

    public function downloadQuestionTemplate()
    {
        $headers = [
            'question', 'type', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e',
            'answer', 'section', 'language', 'difficulty', 'marks', 'explanation', 'topic',
        ];

        // option_e is optional: leave it empty for four-option questions.
        $rows = [
            ['What is the next number in the series 2, 4, 8, 16?', 'mcq', '18', '24', '32', '36', '', 'C', 'reasoning', 'english', 'easy', '2', 'Each term is multiplied by 2.', 'Series'],
            ['The Constitution of India came into force in 1950.', 'yes_no', 'Yes', 'No', '', '', '', 'Yes', 'general_awareness', 'english', 'easy', '2', 'The Constitution came into force on 26 January 1950.', 'Polity'],
            ['If 20% of a number is 40, what is the number?', 'mcq', '100', '160', '200', '240', '', 'C', 'quant', 'english', 'medium', '2', '40 divided by 0.20 equals 200.', 'Percentage'],
            ['Choose the correctly spelled word.', 'mcq', 'Accomodate', 'Acommodate', 'Accommodate', 'Accomdate', '', 'C', 'english', 'english', 'easy', '2', 'Accommodate has double c and double m.', 'Spelling'],
            ['A sum doubles in 8 years at simple interest. What is the rate per annum?', 'mcq', '10%', '12%', '12.5%', '15%', 'None of these', 'C', 'quant', 'english', 'medium', '1', '100% interest in 8 years is 12.5% a year.', 'Simple Interest'],
            ['Which number replaces the question mark: 3, 9, 27, 81, ?', 'mcq', '162', '216', '224', '240', '243', 'E', 'reasoning', 'english', 'easy', '1', 'Each term is multiplied by 3.', 'Series'],
        ];

        $lines = [implode("\t", $headers)];
        foreach ($rows as $row) {
            $lines[] = collect($row)
                ->map(fn (string $value) => str_replace(["\t", "\r", "\n"], ' ', $value))
                ->implode("\t");
        }

        return response("\xEF\xBB\xBF".implode("\r\n", $lines), 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="nonax-question-upload-format.xls"',
        ]);
    }

    public function attendance(Request $request): View
    {
        $query = Attendance::with('student.user', 'student.courses')->latest('attendance_date');

        if ($request->user()->role === 'student') {
            $query->where('student_profile_id', $request->user()->studentProfile?->id);
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->whereHas('student', function ($studentQuery) use ($search) {
                $studentQuery
                    ->where('student_id', 'like', "%{$search}%")
                    ->orWhere('admission_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    });
            });
        }

        if ($courseId = $request->integer('course_id')) {
            $query->where('course_id', $courseId);
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        if ($date = $request->date('attendance_date')) {
            $query->whereDate('attendance_date', $date);
        }

        return view('lists.attendance', [
            'attendance' => $query->paginate(25)->withQueryString(),
            'students' => $request->user()->role === 'student' ? collect() : StudentProfile::with('user')->active()->get(),
            'courses' => $request->user()->role === 'student' ? collect() : Course::active()->get(),
            'filters' => [
                'search' => $request->input('search'),
                'course_id' => $request->input('course_id'),
                'status' => $request->input('status'),
                'attendance_date' => $request->input('attendance_date'),
            ],
        ]);
    }

    public function storeAttendance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_profile_id' => ['required', 'exists:student_profiles,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,late,leave'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        Attendance::updateOrCreate(
            collect($data)->only(['student_profile_id', 'course_id', 'attendance_date'])->all(),
            $data + ['marked_by' => $request->user()->id]
        );

        return back()->with('status', 'Attendance saved.');
    }

    public function materials(Request $request): View
    {
        $query = StudyMaterial::latest();
        if ($request->user()->role === 'student') {
            $courseIds = $request->user()->studentProfile?->courses()->pluck('courses.id') ?? collect();
            $query->whereIn('course_id', $courseIds)->where('status', 'published');
        }

        $tab = $request->query('tab') === 'previous' ? 'previous' : 'notes';
        $query->where('type', $tab === 'previous' ? '=' : '!=', 'previous_question');
        if ($tab === 'previous') {
            $query->reorder()->orderByDesc('year')->latest();
        }

        return view('lists.materials', [
            'tab' => $tab,
            'materials' => $query->paginate(24)->withQueryString(),
            'courses' => Course::with('subjects')->active()->get(),
        ]);
    }

    public function storeMaterial(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:note,pdf,word,previous_question'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $path = $request->file('file')->store('materials', 'public');
        $file = $request->file('file');

        StudyMaterial::create($data + [
            'uploaded_by' => $request->user()->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return back()->with('status', 'Material uploaded.');
    }

    public function downloadMaterial(StudyMaterial $material)
    {
        abort_unless(Storage::disk('public')->exists($material->file_path), 404);

        return Storage::disk('public')->download($material->file_path, $material->file_name);
    }

    private function nextStudentId(): string
    {
        return 'STD-'.str_pad((string) (StudentProfile::count() + 1001), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{name: string, code?: string|null, teacher_profile_id?: int|null}  $data
     */
    private function createSubject(Course $course, array $data): Subject
    {
        return Subject::create([
            'course_id' => $course->id,
            'teacher_profile_id' => $data['teacher_profile_id'] ?? null,
            'name' => $data['name'],
            'code' => Str::upper($data['code'] ?: Str::slug($course->code.'-'.$data['name'].'-'.($course->subjects()->count() + 1))),
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, string|null>
     */
    private function normalizeQuestionRow(array $row): array
    {
        $row['correct_answer'] = $row['correct_answer'] ?? $row['answer'] ?? null;
        $type = Str::of($row['type'] ?? 'mcq')->trim()->lower()->replace('-', '_')->toString();
        $row['type'] = in_array($type, ['yes_no', 'yesno', 'true_false'], true) ? 'yes_no' : 'mcq';

        if ($row['type'] === 'yes_no') {
            $row['option_a'] = ($row['option_a'] ?? null) ?: 'Yes';
            $row['option_b'] = ($row['option_b'] ?? null) ?: 'No';
            $row['option_c'] = null;
            $row['option_d'] = null;
            $row['option_e'] = null;
        }

        $row['correct_answer'] = $this->normalizeCorrectAnswer(
            (string) $row['correct_answer'],
            [
                'A' => $row['option_a'] ?? null,
                'B' => $row['option_b'] ?? null,
                'C' => $row['option_c'] ?? null,
                'D' => $row['option_d'] ?? null,
                'E' => $row['option_e'] ?? null,
            ],
            $row['type']
        );

        return $row;
    }

    /**
     * @param  array<string, string|null>  $options
     */
    private function normalizeCorrectAnswer(string $answer, array $options, string $type): string
    {
        $answer = trim($answer);
        $upper = strtoupper($answer);
        if (in_array($upper, ['A', 'B', 'C', 'D', 'E'], true)) {
            return $upper;
        }

        $lower = Str::of($answer)->lower()->trim()->toString();
        if ($type === 'yes_no') {
            if (in_array($lower, ['yes', 'y', 'true', '1'], true)) {
                return 'A';
            }
            if (in_array($lower, ['no', 'n', 'false', '0'], true)) {
                return 'B';
            }
        }

        foreach ($options as $key => $option) {
            if ($option !== null && Str::of($option)->lower()->trim()->toString() === $lower) {
                return $key;
            }
        }

        return $upper;
    }

    /**
     * Reads an Excel .xlsx workbook (its "Questions" sheet, or the first sheet),
     * or a CSV / tab-delimited file exported from Excel.
     *
     * @return array<int, array<string, string|null>>
     */
    private function readQuestionRows(string $path): array
    {
        if (XlsxReader::isXlsx($path)) {
            $lines = app(XlsxReader::class)->rows($path);
        } else {
            $sample = file_get_contents($path, false, null, 0, 4096) ?: '';
            $delimiter = substr_count($sample, "\t") > substr_count($sample, ',') ? "\t" : ',';
            $handle = fopen($path, 'r');
            if (! $handle) {
                return [];
            }
            $lines = [];
            while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($line !== [null]) {
                    $lines[] = $line;
                }
            }
            fclose($handle);
        }

        $headers = [];
        $rows = [];
        foreach ($lines as $line) {
            if ($headers === []) {
                $headers = collect($line)
                    ->map(fn (?string $header) => Str::of($header ?? '')->replace("\xEF\xBB\xBF", '')->trim()->lower()->replace([' ', '-'], '_')->toString())
                    ->all();
                continue;
            }

            $rows[] = collect($headers)
                ->mapWithKeys(fn (string $header, int $index) => [$header => isset($line[$index]) && trim((string) $line[$index]) !== '' ? trim((string) $line[$index]) : null])
                ->all();
        }

        return $rows;
    }
}
