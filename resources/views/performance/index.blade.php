@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <div class="eyebrow">Subject-wise performance</div>
        <h1 class="h3 mb-1">{{ $subject ? $subject->name : 'Choose a subject' }}</h1>
        <p class="text-secondary mb-0">{{ $subject ? ($subject->course->name ?? '') : 'See how every student is doing in one subject.' }}</p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <form method="get" action="{{ route('performance.index') }}" class="row g-3 align-items-end">
        <div class="col-md-8">
            <label class="form-label" for="subject_id">Subject</label>
            <select class="form-select" id="subject_id" name="subject_id" onchange="this.form.submit()">
                <option value="">Select a subject</option>
                @foreach($subjects as $option)
                    <option value="{{ $option->id }}" @selected($subject?->id === $option->id)>{{ $option->course->name ?? '' }} - {{ $option->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4"><button class="btn btn-primary w-100">Show students</button></div>
    </form>
</div></div>

@if($subject)
<div class="weekly-stats mb-3">
    <div><strong>{{ $rows->count() }}</strong><small>Students</small></div>
    <div><strong>{{ $rows->where('attempted', '>', 0)->count() }}</strong><small>Have answered</small></div>
    <div><strong>{{ $rows->sum('attempted') ? round($rows->sum('correct') / $rows->sum('attempted') * 100, 1).'%' : '--' }}</strong><small>Class accuracy</small></div>
</div>
<div class="card border-0 shadow-sm"><div class="card-body">
    @include('performance._table', ['rows' => $rows, 'first' => 'student'])
    <p class="small text-secondary mt-3 mb-0">Students are ordered by accuracy. Click a name to see that student across all subjects.</p>
</div></div>
@endif
@endsection
