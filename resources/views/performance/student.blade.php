@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <div class="eyebrow">Subject-wise performance</div>
        <h1 class="h3 mb-1">{{ $student->user->name }}</h1>
        <p class="text-secondary mb-0">{{ $student->student_id }} &middot; {{ $student->courses->pluck('name')->join(', ') ?: 'No course' }}</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('performance.index') }}">By subject</a>
        <a class="btn btn-outline-primary" href="{{ route('students.index') }}"><i data-lucide="arrow-left"></i> Students</a>
    </div>
</div>

<div class="weekly-stats mb-3">
    <div><strong>{{ $rows->max('tests') ?? 0 }}</strong><small>Tests submitted</small></div>
    <div><strong>{{ $rows->sum('questions') }}</strong><small>Questions faced</small></div>
    <div><strong>{{ $rows->sum('attempted') ? round($rows->sum('correct') / $rows->sum('attempted') * 100, 1).'%' : '--' }}</strong><small>Overall accuracy</small></div>
    <div><strong>{{ $student->attendancePercent() !== null ? $student->attendancePercent().'%' : '--' }}</strong><small>Attendance</small></div>
</div>

<div class="card border-0 shadow-sm"><div class="card-body">
    @include('performance._table', ['rows' => $rows, 'first' => 'subject'])
    <p class="small text-secondary mt-3 mb-0">Counts every question in the student's submitted tests. Accuracy is correct answers out of answered questions; skipped questions are shown separately.</p>
</div></div>
@endsection
