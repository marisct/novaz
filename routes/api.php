<?php

use App\Http\Controllers\Api\InstituteApiController;
use App\Http\Controllers\Api\ExamAttemptApiController;
use App\Http\Controllers\Api\ManagementApiController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'apiLogin'])->middleware('throttle:10,1');

Route::middleware('api.token')->group(function () {
    Route::get('/home', [InstituteApiController::class, 'home']);
    Route::get('/exam-patterns', fn () => ['data' => \App\Models\ExamPattern::with('sections')->whereIn('code', \App\Models\Exam::SUPPORTED_PATTERN_CODES)->where('is_active', true)->get()]);
    Route::get('/profile', [InstituteApiController::class, 'profile']);
    Route::get('/dashboard', [InstituteApiController::class, 'dashboard']);
    Route::get('/courses', [InstituteApiController::class, 'courses']);
    Route::get('/subjects', [InstituteApiController::class, 'courses']);
    Route::get('/notes', [InstituteApiController::class, 'notes']);
    Route::get('/previous-papers', [InstituteApiController::class, 'previousPapers']);
    Route::get('/exams', fn (InstituteApiController $api, \Illuminate\Http\Request $request) => $api->exams($request, null));
    Route::get('/attendance', [InstituteApiController::class, 'attendance']);
    Route::get('/daily-quiz', fn (InstituteApiController $api, \Illuminate\Http\Request $request) => $api->exams($request, 'daily_quiz'));
    Route::get('/mock-test', fn (InstituteApiController $api, \Illuminate\Http\Request $request) => $api->exams($request, 'mock_test'));
    Route::get('/monthly-test', fn (InstituteApiController $api, \Illuminate\Http\Request $request) => $api->exams($request, 'monthly_test'));
    Route::get('/results', [InstituteApiController::class, 'results']);
    Route::get('/analytics', [InstituteApiController::class, 'analytics']);
    Route::get('/subject-performance', [InstituteApiController::class, 'subjectPerformance']);
    Route::get('/notifications', [InstituteApiController::class, 'notifications']);
    Route::get('/leaderboard', [InstituteApiController::class, 'leaderboard']);

    Route::post('/exams/{exam}/start', [ExamAttemptApiController::class, 'start']);
    Route::get('/attempts/{attempt}', [ExamAttemptApiController::class, 'show']);
    Route::post('/attempts/{attempt}', [ExamAttemptApiController::class, 'save']);
    Route::get('/attempts/{attempt}/result', [ExamAttemptApiController::class, 'result']);

    Route::prefix('management')->controller(ManagementApiController::class)->group(function () {
        Route::get('/meta', 'meta');

        Route::get('/students', 'students');
        Route::post('/students', 'storeStudent');
        Route::put('/students/{student}', 'updateStudent');
        Route::delete('/students/{student}', 'destroyStudent');
        Route::get('/students/{student}/performance', 'studentPerformance');

        Route::get('/teachers', 'teachers');
        Route::post('/teachers', 'storeTeacher');
        Route::put('/teachers/{teacher}', 'updateTeacher');
        Route::delete('/teachers/{teacher}', 'destroyTeacher');

        Route::get('/courses', 'courses');
        Route::post('/courses', 'storeCourse');
        Route::put('/courses/{course}', 'updateCourse');
        Route::delete('/courses/{course}', 'destroyCourse');

        Route::get('/subjects', 'subjects');
        Route::post('/subjects', 'storeSubject');
        Route::put('/subjects/{subject}', 'updateSubject');
        Route::delete('/subjects/{subject}', 'destroySubject');
        Route::get('/subjects/{subject}/performance', 'subjectPerformance');

        Route::get('/questions', 'questions');
        Route::post('/questions', 'storeQuestion');
        Route::put('/questions/{question}', 'updateQuestion');
        Route::delete('/questions/{question}', 'destroyQuestion');

        Route::get('/exams', 'exams');
        Route::post('/exams', 'storeExam');
        Route::put('/exams/{exam}', 'updateExam');
        Route::delete('/exams/{exam}', 'destroyExam');

        Route::get('/attendance', 'attendance');
        Route::post('/attendance', 'storeAttendance');

        Route::get('/materials', 'materials');
        Route::post('/materials', 'storeMaterial');
        Route::delete('/materials/{material}', 'destroyMaterial');
    });
});
