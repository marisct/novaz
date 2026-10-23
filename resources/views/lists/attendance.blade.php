@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Attendance</h1>
</div>

@if(auth()->user()->role !== 'student')
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="post" action="{{ route('attendance.store') }}" class="row g-3">
            @csrf
            <div class="col-md-3"><select class="form-select" name="student_profile_id">@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->user->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select class="form-select" name="course_id">@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><input class="form-control" type="date" name="attendance_date" value="{{ now()->toDateString() }}"></div>
            <div class="col-md-2"><select class="form-select" name="status"><option>present</option><option>absent</option><option>late</option><option>leave</option></select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Save</button></div>
        </form>
    </div>
</div>
@endif

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="{{ route('attendance.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-secondary">Search</label>
                <input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Student name, ID, admission, email, mobile">
            </div>
            @if(auth()->user()->role !== 'student')
                <div class="col-md-2">
                    <label class="form-label small text-secondary">Course</label>
                    <select class="form-select" name="course_id">
                        <option value="">All courses</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}" @selected((string) ($filters['course_id'] ?? '') === (string) $course->id)>{{ $course->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2">
                <label class="form-label small text-secondary">Status</label>
                <select class="form-select" name="status">
                    <option value="">All status</option>
                    @foreach(['present', 'absent', 'late', 'leave'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-secondary">Date</label>
                <input class="form-control" type="date" name="attendance_date" value="{{ $filters['attendance_date'] ?? '' }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill">Search</button>
                <a class="btn btn-outline-secondary" href="{{ route('attendance.index') }}">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Admission</th>
                    <th>Course</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendance as $row)
                    <tr>
                        <td>{{ $row->attendance_date->toDateString() }}</td>
                        <td>{{ $row->student->user->name }}</td>
                        <td>{{ $row->student->admission_number }}</td>
                        <td>{{ $row->student->courses->firstWhere('id', $row->course_id)?->name ?? '-' }}</td>
                        <td><span class="badge text-bg-info">{{ $row->status }}</span></td>
                        <td>{{ $row->remarks }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-secondary">No attendance records found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $attendance->links() }}</div>
</div>
@endsection
