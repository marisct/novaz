<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Ranking;
use App\Models\StudyMaterial;
use App\Services\AnalyticsService;
use App\Services\DashboardService;
use App\Services\SubjectPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InstituteApiController extends Controller
{
    public function profile(Request $request): array
    {
        return ['user' => $request->user()->load('studentProfile', 'teacherProfile')];
    }

    public function home(Request $request, DashboardService $service): array
    {
        return [
            'profile' => $this->profile($request),
            'dashboard' => $this->dashboard($request, $service),
        ];
    }

    public function dashboard(Request $request, DashboardService $service): array
    {
        $user = $request->user();

        return match ($user->role) {
            'student' => $service->student($user->studentProfile),
            'teacher' => $service->teacher((int) $user->teacherProfile?->id),
            default => $service->admin(),
        };
    }

    public function courses(): array
    {
        return ['data' => Cache::remember(
            'api.courses.active',
            now()->addSeconds(60),
            fn () => Course::with('subjects')->active()->get()
        )];
    }

    public function notes(Request $request): array
    {
        $type = $request->query('type');

        return ['data' => $this->materials($request)
            ->when($type, fn ($q) => $q->where('type', $type), fn ($q) => $q->where('type', '!=', 'previous_question'))
            ->latest()->paginate(20)->through(fn (StudyMaterial $m) => $this->withDownloadUrl($m))];
    }

    /** Previous year question papers, newest year first. */
    public function previousPapers(Request $request): array
    {
        return ['data' => $this->materials($request)->where('type', 'previous_question')
            ->orderByDesc('year')->latest()->paginate(20)->through(fn (StudyMaterial $m) => $this->withDownloadUrl($m))];
    }

    private function materials(Request $request)
    {
        $user = $request->user();

        return StudyMaterial::query()->with('subject:id,name', 'course:id,name')->where('status', 'published')
            ->when($user->role === 'student', fn ($q) => $q->whereIn('course_id', $user->studentProfile?->courses()->pluck('courses.id') ?? []))
            ->when($request->course_id, fn ($q) => $q->where('course_id', $request->course_id))
            ->when($request->subject_id, fn ($q) => $q->where('subject_id', $request->subject_id));
    }

    private function withDownloadUrl(StudyMaterial $material): StudyMaterial
    {
        // Signed link that works in a browser without the app token; valid for 12 hours.
        $material->setAttribute('download_url', \Illuminate\Support\Facades\URL::temporarySignedRoute('materials.file', now()->addHours(12), ['material' => $material->id]));

        return $material;
    }

    public function attendance(Request $request): array
    {
        return ['data' => Attendance::where('student_profile_id', $request->user()->studentProfile?->id)->latest('attendance_date')->paginate(31)];
    }

    public function exams(Request $request, ?string $type = null): array
    {
        $page = (int) $request->query('page', 1);
        $student = $request->user()->studentProfile;
        $audience = $student
            ? 'student-'.$request->user()->id.'.v'.\App\Models\ExamAttempt::listVersion($student->id)
            : 'staff';

        return ['data' => Cache::remember(
            'api.exams.'.($type ?? 'all').".{$audience}.page.{$page}",
            now()->addSeconds(45),
            fn () => Exam::supported()->with('course', 'pattern.sections')->when($type, fn ($q) => $q->where('type', $type))->where('status', 'published')
                ->when($request->user()->role === 'student', fn ($q) => $q->whereIn('course_id', $student?->courses()->pluck('courses.id') ?? []))
                // the student's own attempt, so the app can show "View Result" instead of "Start Test"
                ->when($student, fn ($q) => $q->with(['attempts' => fn ($a) => $a->where('student_profile_id', $student->id)->select('id', 'exam_id', 'status', 'score', 'submitted_at')]))
                ->latest('starts_at')->latest('id')->paginate(20)
        )];
    }

    public function results(Request $request): array
    {
        $student = $request->user()->studentProfile;

        if (! $student) {
            return ['data' => []];
        }

        return ['data' => $student->attempts()->with('exam')->latest()->paginate(20)];
    }

    public function subjectPerformance(Request $request, SubjectPerformanceService $performance): array
    {
        $student = $request->user()->studentProfile;

        return ['data' => $student ? $performance->forStudent($student) : []];
    }

    public function analytics(Request $request, AnalyticsService $analytics): array
    {
        $student = $request->user()->studentProfile;

        if (! $student) {
            return ['data' => [
                'average_score' => 0,
                'accuracy_percent' => 0,
                'speed_seconds' => 0,
                'tests_attempted' => 0,
                'tests_skipped' => 0,
                'strong_areas' => [],
                'weak_areas' => [],
                'recommendations' => [],
            ]];
        }

        return ['data' => $analytics->refreshStudent($student)];
    }

    public function notifications(Request $request): array
    {
        $exams = $request->user()->studentProfile
            ? app(\App\Services\StudentProgressService::class)->exams($request->user()->studentProfile) : [];
        return ['data' => array_merge($exams, Cache::remember(
            'api.notifications.published',
            now()->addSeconds(60),
            fn () => Announcement::where('status', 'published')->latest('publish_at')->take(25)->get()
        )->toArray())];
    }

    public function leaderboard(): array
    {
        return ['data' => Cache::remember(
            'api.leaderboard.top50',
            now()->addSeconds(60),
            fn () => Ranking::with('student.user')->orderBy('rank')->take(50)->get()
        )];
    }
}
