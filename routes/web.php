<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamAttemptController;
use App\Http\Controllers\InstituteController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Signed download link used by the mobile app (no session needed, expires).
Route::get('/materials/{material}/file', [InstituteController::class, 'downloadMaterial'])->middleware('signed')->name('materials.file');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
});

Route::middleware(['auth', 'session.timeout'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/exams', [InstituteController::class, 'exams'])->name('exams.index');
    Route::get('/attendance', [InstituteController::class, 'attendance'])->name('attendance.index');
    Route::get('/materials', [InstituteController::class, 'materials'])->name('materials.index');
    Route::middleware('role:student')->group(function () {
        Route::post('/exams/{exam}/start', [ExamAttemptController::class, 'start'])->name('exams.start');
        Route::get('/attempts/{attempt}', [ExamAttemptController::class, 'show'])->name('attempts.show');
        Route::post('/attempts/{attempt}', [ExamAttemptController::class, 'save'])->name('attempts.save');
        Route::get('/attempts/{attempt}/result', [ExamAttemptController::class, 'result'])->name('attempts.result');
    });

    Route::middleware('role:super_admin,teacher')->group(function () {
        Route::get('/students', [InstituteController::class, 'students'])->name('students.index');
        Route::post('/students', [InstituteController::class, 'storeStudent'])->name('students.store');
        Route::put('/students/{student}', [InstituteController::class, 'updateStudent'])->name('students.update');
        Route::delete('/students/{student}', [InstituteController::class, 'destroyStudent'])->name('students.destroy');
        Route::get('/students/{student}/performance', [InstituteController::class, 'studentPerformance'])->name('students.performance');
        Route::get('/performance', [InstituteController::class, 'subjectPerformance'])->name('performance.index');
        Route::post('/exams', [InstituteController::class, 'storeExam'])->name('exams.store');
        Route::post('/questions/bulk-upload', [InstituteController::class, 'bulkUploadQuestions'])->name('questions.bulk-upload');
        Route::get('/questions/template', [InstituteController::class, 'downloadQuestionTemplate'])->name('questions.template');
        Route::post('/attendance', [InstituteController::class, 'storeAttendance'])->name('attendance.store');
        Route::post('/materials', [InstituteController::class, 'storeMaterial'])->name('materials.store');
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/teachers', [InstituteController::class, 'teachers'])->name('teachers.index');
        Route::post('/teachers', [InstituteController::class, 'storeTeacher'])->name('teachers.store');
        Route::put('/teachers/{teacher}', [InstituteController::class, 'updateTeacher'])->name('teachers.update');
        Route::delete('/teachers/{teacher}', [InstituteController::class, 'destroyTeacher'])->name('teachers.destroy');
        Route::get('/courses', [InstituteController::class, 'courses'])->name('courses.index');
        Route::post('/courses', [InstituteController::class, 'storeCourse'])->name('courses.store');
        Route::put('/courses/{course}', [InstituteController::class, 'updateCourse'])->name('courses.update');
        Route::delete('/courses/{course}', [InstituteController::class, 'destroyCourse'])->name('courses.destroy');
        Route::get('/subjects', [InstituteController::class, 'subjects'])->name('subjects.index');
        Route::post('/subjects', [InstituteController::class, 'storeStandaloneSubject'])->name('subjects.standalone.store');
        Route::put('/subjects/{subject}', [InstituteController::class, 'updateSubject'])->name('subjects.update');
        Route::delete('/subjects/{subject}', [InstituteController::class, 'destroySubject'])->name('subjects.destroy');
        Route::get('/courses/{course}', [InstituteController::class, 'showCourse'])->name('courses.show');
        Route::post('/courses/{course}/subjects', [InstituteController::class, 'storeSubject'])->name('subjects.store');
        Route::post('/subjects/{subject}/materials', [InstituteController::class, 'storeSubjectMaterial'])->name('subjects.materials.store');
    });

    Route::get('/materials/{material}/download', [InstituteController::class, 'downloadMaterial'])->name('materials.download');
});
