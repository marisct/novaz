<?php

use App\Models\StudentProfile;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('analytics:refresh-students', function (AnalyticsService $analytics) {
    StudentProfile::query()->each(fn (StudentProfile $student) => $analytics->refreshStudent($student));
    $analytics->rebuildOverallRankings();

    $this->info('Student analytics and rankings refreshed.');
})->purpose('Recalculate student analytics and rankings');

Schedule::command('analytics:refresh-students')->dailyAt('01:00');
