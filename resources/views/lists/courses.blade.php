@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Courses</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">Add Course</button>
</div>

<div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" action="{{ route('courses.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title h5" id="addCourseModalLabel">Add Course</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-5"><input class="form-control" name="name" placeholder="Course name" required></div>
                    <div class="col-md-3"><input class="form-control" name="code" placeholder="Code" required></div>
                    <div class="col-md-4"><input class="form-control" name="duration" placeholder="Duration"></div>
                    <div class="col-12"><textarea class="form-control" name="description" rows="3" placeholder="Description"></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Create Course</button>
            </div>
        </form>
    </div>
</div>
<div class="row g-3">@foreach($courses as $course)<div class="col-md-6 col-xl-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between gap-3"><div><h2 class="h5 mb-1">{{ $course->name }}</h2><div class="text-secondary small">{{ $course->code }}</div></div><span class="badge text-bg-success align-self-start">{{ $course->status }}</span></div><p class="text-secondary mt-3">{{ $course->description }}</p><div class="d-flex flex-wrap gap-2 mb-3"><span class="badge text-bg-primary">{{ $course->subjects_count }} subjects</span><span class="badge text-bg-secondary">{{ $course->students_count }} students</span><span class="badge text-bg-dark">{{ $course->teachers_count }} teachers</span></div><div class="d-flex flex-wrap gap-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('courses.show', $course) }}">Manage</a><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editCourseModal{{ $course->id }}">Edit</button><form method="post" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Delete this course and related subjects?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></div></div></div></div>@endforeach</div>
<div class="mt-3">{{ $courses->links() }}</div>

@foreach($courses as $course)
<div class="modal fade" id="editCourseModal{{ $course->id }}" tabindex="-1" aria-labelledby="editCourseModalLabel{{ $course->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" action="{{ route('courses.update', $course) }}" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h2 class="modal-title h5" id="editCourseModalLabel{{ $course->id }}">Edit Course</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-5"><input class="form-control" name="name" value="{{ $course->name }}" placeholder="Course name" required></div>
                    <div class="col-md-3"><input class="form-control" name="code" value="{{ $course->code }}" placeholder="Code" required></div>
                    <div class="col-md-2"><input class="form-control" name="duration" value="{{ $course->duration }}" placeholder="Duration"></div>
                    <div class="col-md-2"><select class="form-select" name="status" required><option value="active" @selected($course->status === 'active')>Active</option><option value="inactive" @selected($course->status === 'inactive')>Inactive</option></select></div>
                    <div class="col-12"><textarea class="form-control" name="description" rows="3" placeholder="Description">{{ $course->description }}</textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Update Course</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endsection
