<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Ranking;
use App\Models\StudentProfile;
use App\Models\StudyMaterial;
use App\Models\Subject;
use App\Models\TeacherProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function admin(): array
    {
        return Cache::remember('dashboard.admin', now()->addSeconds(45), fn () => [
            'cards' => [
                'students' => StudentProfile::count(),
                'teachers' => TeacherProfile::count(),
                'courses' => Course::count(),
                'subjects' => Subject::count(),
                'today_attendance' => Attendance::whereDate('attendance_date', today())->count(),
                'today_tests' => Exam::supported()->whereDate('starts_at', today())->count(),
                'exams' => Exam::supported()->count(),
                'materials' => StudyMaterial::count(),
                'average_score' => round((float) ExamAttempt::avg('score'), 2),
                'questions' => Question::count(),
            ],
            'top_students' => Ranking::with('student.user')->where('scope', 'overall')->orderBy('rank')->take(3)->get(),
            'recent_students' => StudentProfile::with('user')->latest()->take(5)->get(),
            'attendance_chart' => Attendance::select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'exam_chart' => ExamAttempt::selectRaw('date(created_at) as day, avg(score) as score')->groupBy('day')->latest('day')->take(7)->pluck('score', 'day'),
        ]);
    }

    public function teacher(int $teacherProfileId): array
    {
        return Cache::remember("dashboard.teacher.{$teacherProfileId}", now()->addSeconds(45), fn () => [
            'assigned_courses' => Course::whereHas('teachers', fn ($query) => $query->whereKey($teacherProfileId))->withCount('students')->get(),
            'today_attendance' => Attendance::whereDate('attendance_date', today())->count(),
            'upcoming_exams' => Exam::supported()->where('starts_at', '>=', now())->orderBy('starts_at')->take(5)->get(),
            'top_students' => Ranking::with('student.user')->orderBy('rank')->take(5)->get(),
            'pending_evaluations' => ExamAttempt::where('status', 'submitted')->whereNull('submitted_at')->count(),
        ]);
    }

    public function student(StudentProfile $student): array
    {
        $attempts = $student->attempts();

        return [
            'weekly_performance' => app(StudentProgressService::class)->weekly($student),
            'subject_performance' => app(SubjectPerformanceService::class)->forStudent($student),
            'enrolled_courses' => $student->courses()->withCount('subjects')->get(),
            'exam_notifications' => app(StudentProgressService::class)->exams($student),
            'attendance_percent' => $this->attendancePercent($student->id),
            'completed_tests' => (clone $attempts)->where('status', 'submitted')->count(),
            'average_score' => round((float) (clone $attempts)->where('status', 'submitted')->avg('score'), 2),
            'rank' => Ranking::where('student_profile_id', $student->id)->orderBy('rank')->first(),
            'recent_results' => (clone $attempts)->where('status', 'submitted')->with('exam')->latest('submitted_at')->take(5)->get(),
        ];
    }

    private function attendancePercent(int $studentProfileId): float
    {
        $total = Attendance::where('student_profile_id', $studentProfileId)->count();
        if ($total === 0) {
            return 0;
        }

        $present = Attendance::where('student_profile_id', $studentProfileId)->whereIn('status', ['present', 'late'])->count();

        return round(($present / $total) * 100, 2);
    }
}
