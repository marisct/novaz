@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Subjects</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">Add Subject</button>
</div>

<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" action="{{ route('subjects.standalone.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title h5" id="addSubjectModalLabel">Add Subject</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <select class="form-select" name="course_id" required>
                            <option value="">Choose course</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}">{{ $course->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4"><input class="form-control" name="name" placeholder="Subject name" required></div>
                    <div class="col-md-3"><input class="form-control" name="code" placeholder="Code"></div>
                    <div class="col-12">
                        <select class="form-select" name="teacher_profile_id">
                            <option value="">Assign teacher later</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}">{{ $teacher->user->name }}{{ $teacher->department ? ' - '.$teacher->department : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Create Subject</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    @forelse($subjects as $subject)
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <h2 class="h5 mb-1">{{ $subject->name }}</h2>
                            <div class="text-secondary small">{{ $subject->code }}</div>
                        </div>
                        <span class="badge text-bg-success align-self-start">{{ $subject->status }}</span>
                    </div>
                    <p class="text-secondary mt-3 mb-2">{{ $subject->course?->name }}</p>
                    <div class="small text-secondary mb-3">
                        Teacher: {{ $subject->teacher?->user?->name ?? 'Not assigned' }}
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-primary">{{ $subject->materials_count }} notes</span>
                        <span class="badge text-bg-dark">{{ $subject->questions_count }} questions</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('courses.show', $subject->course) }}">Open Course</a>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editSubjectModal{{ $subject->id }}">Edit</button>
                        <form method="post" action="{{ route('subjects.destroy', $subject) }}" onsubmit="return confirm('Delete this subject?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-secondary">No subjects created yet.</div>
            </div>
        </div>
    @endforelse
</div>

<div class="mt-3">{{ $subjects->links() }}</div>

@foreach($subjects as $subject)
<div class="modal fade" id="editSubjectModal{{ $subject->id }}" tabindex="-1" aria-labelledby="editSubjectModalLabel{{ $subject->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" action="{{ route('subjects.update', $subject) }}" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h2 class="modal-title h5" id="editSubjectModalLabel{{ $subject->id }}">Edit Subject</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <select class="form-select" name="course_id" required>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}" @selected($subject->course_id === $course->id)>{{ $course->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4"><input class="form-control" name="name" value="{{ $subject->name }}" placeholder="Subject name" required></div>
                    <div class="col-md-3"><input class="form-control" name="code" value="{{ $subject->code }}" placeholder="Code"></div>
                    <div class="col-md-8">
                        <select class="form-select" name="teacher_profile_id">
                            <option value="">Assign teacher later</option>
                            @foreach($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected($subject->teacher_profile_id === $teacher->id)>{{ $teacher->user->name }}{{ $teacher->department ? ' - '.$teacher->department : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4"><select class="form-select" name="status" required><option value="active" @selected($subject->status === 'active')>Active</option><option value="inactive" @selected($subject->status === 'inactive')>Inactive</option></select></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Update Subject</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endsection
