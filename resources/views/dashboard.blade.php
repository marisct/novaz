@extends('layouts.app')
@section('content')
@php
    $isStudent = $role === 'student';
    $roleTitle = ucfirst(str_replace('_', ' ', $role));
    $metrics = $role === 'super_admin' ? [
        ['Students',$payload['cards']['students'],'users','students','Student profiles'],
        ['Teachers',$payload['cards']['teachers'],'graduation-cap','teachers','Faculty members'],
        ['Courses',$payload['cards']['courses'],'book-open','courses','Learning programs'],
        ['Subjects',$payload['cards']['subjects'],'library','subjects','Course subjects'],
        ['Exams',$payload['cards']['exams'],'clipboard-list','exams','Practice exams'],
        ['Today attendance',$payload['cards']['today_attendance'],'calendar-check','attendance','Records today'],
        ['Today tests',$payload['cards']['today_tests'],'timer','exams','Scheduled today'],
        ['Materials',$payload['cards']['materials'],'folder-open','materials','Learning resources'],
        ['Average score',$payload['cards']['average_score'],'chart-no-axes-combined','exams','Across test attempts'],
        ['Questions',$payload['cards']['questions'],'circle-help','exams','In the question bank'],
    ] : ($isStudent ? [
        ['Completed tests',$payload['completed_tests'],'clipboard-check','exams','Your submitted attempts'],
        ['Average score',$payload['average_score'],'chart-no-axes-combined','exams','Across completed tests'],
        ['Attendance',$payload['attendance_percent'].'%','calendar-check','attendance','Present and late days'],
        ['Rank',$payload['rank']->rank ?? '--','award','dashboard','Your recorded rank'],
    ] : [
        ['Assigned courses',$payload['assigned_courses']->count(),'book-open','dashboard','Your learning groups'],
        ['Upcoming exams',$payload['upcoming_exams']->count(),'clipboard-list','exams','Scheduled practice'],
        ['Today attendance',$payload['today_attendance'],'calendar-check','attendance','Records today'],
    ]);
@endphp
<div class="page-heading">
    <div><div class="eyebrow">{{ $isStudent ? 'Your learning, in focus' : 'Institute overview' }}</div><h1>{{ $roleTitle }} Dashboard</h1><p>Welcome back, {{ auth()->user()->name }}. {{ $isStudent ? 'Make a little progress today.' : 'Here is what is happening at NOVA Z.' }}</p></div>
    <div class="date-tag"><i data-lucide="calendar-days"></i>{{ now()->format('D, d M Y') }}</div>
</div>
@if($isStudent)
<section class="learning-banner"><span class="eyebrow">Keep your momentum</span><h2>Your next chapter<br>starts with practice.</h2><p>A fresh test. A clearer understanding. One step closer to your goal.</p><a class="btn btn-primary" href="{{ route('exams.index') }}">Find a practice test <i data-lucide="arrow-right"></i></a></section>
@endif
<div class="metric-grid {{ $isStudent ? 'student-metrics' : '' }}">
    @foreach($metrics as [$label,$value,$icon,$path,$note])
        <a class="metric-tile" href="{{ url($path) }}" style="--accent:{{ ['#1d4ed8','#b1892c','#ba725d','#7c3aed'][$loop->index % 4] }}"><div class="metric-label">{{ $label }}<i data-lucide="{{ $icon }}"></i></div><strong class="metric-number">{{ $value }}</strong><div class="metric-note">{{ $note }}</div></a>
    @endforeach
</div>
@if($isStudent)
    @include('students.progress', ['progress' => $payload['weekly_performance'], 'notifications' => $payload['exam_notifications']])
    <section class="data-section mt-4" id="subject-performance" aria-label="Subject-wise performance">
        <div class="section-title"><h2>Subject-wise performance</h2><span class="badge text-bg-light">All submitted tests</span></div>
        <div class="card border-0 shadow-sm"><div class="card-body">@include('performance._table', ['rows' => $payload['subject_performance'], 'first' => 'subject'])</div></div>
    </section>
@else
    <div class="workspace-columns">
        <section class="data-section">
            <div class="section-title"><h2>{{ $role === 'super_admin' ? 'Recent students' : 'Your courses' }}</h2><a href="{{ route('students.index') }}">View students <i data-lucide="arrow-up-right"></i></a></div>
            @if($role === 'super_admin')
                @forelse($payload['recent_students'] as $student)<div class="activity-row"><div class="avatar">{{ mb_substr($student->user->name,0,1) }}</div><div class="row-copy"><strong>{{ $student->user->name }}</strong><small>{{ $student->student_id }}</small></div><span class="badge text-bg-light">{{ ucfirst($student->status) }}</span></div>@empty<p class="empty-copy">No student profiles yet.</p>@endforelse
            @else
                @forelse($payload['assigned_courses'] as $course)<div class="activity-row"><div class="row-icon"><i data-lucide="book-open"></i></div><div class="row-copy"><strong>{{ $course->name }}</strong><small>{{ $course->students_count }} students</small></div></div>@empty<p class="empty-copy">Your assigned courses will appear here.</p>@endforelse
            @endif
        </section>
        <section class="data-section"><div class="section-title"><h2>Student highlights</h2><i data-lucide="award"></i></div>
            @forelse($payload['top_students'] as $ranking)<div class="activity-row"><div class="row-icon"><i data-lucide="award"></i></div><div class="row-copy"><strong>{{ $ranking->student?->user?->name ?? 'Student' }}</strong><small>Recorded leaderboard</small></div><span class="badge text-bg-primary">#{{ $ranking->rank }}</span></div>@empty<div class="empty-copy"><i data-lucide="chart-no-axes-combined"></i><p class="mt-3">Student rankings will appear as performance records become available.</p></div>@endforelse
        </section>
    </div>
@endif
<div class="section-title"><h2>{{ $isStudent ? 'Your study essentials' : 'Quick actions' }}</h2></div>
<div class="quick-actions">
    <a class="quick-action" href="{{ route('exams.index') }}"><i data-lucide="clipboard-list"></i>{{ $isStudent ? 'My Exams' : 'Schedule Exam' }}</a>
    <a class="quick-action" href="{{ route('attendance.index') }}"><i data-lucide="calendar-check"></i>{{ $isStudent ? 'My Attendance' : 'Mark Attendance' }}</a>
    <a class="quick-action" href="{{ route('materials.index') }}"><i data-lucide="folder-open"></i>{{ $isStudent ? 'Study Materials' : 'Upload Materials' }}</a>
</div>
@endsection
