@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Teachers</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">Add Teacher</button>
</div>

<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" action="{{ route('teachers.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title h5" id="addTeacherModalLabel">Add Teacher</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4"><input class="form-control" name="name" placeholder="Full name" required></div>
                    <div class="col-md-4"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
                    <div class="col-md-4"><input class="form-control" name="mobile" placeholder="Mobile"></div>
                    <div class="col-md-4"><input class="form-control" name="employee_id" placeholder="Employee ID" required></div>
                    <div class="col-md-4"><input class="form-control" name="department" placeholder="Department"></div>
                    <div class="col-md-4"><input class="form-control" name="qualification" placeholder="Qualification"></div>
                    <div class="col-md-4"><input class="form-control" type="number" min="0" name="experience_years" placeholder="Experience years"></div>
                    <div class="col-md-4"><input class="form-control" name="password" placeholder="Password"></div>
                    <div class="col-md-4">
                        <select class="form-select" name="course_ids[]" multiple>
                            @foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Create Teacher</button>
            </div>
        </form>
    </div>
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr><th>Employee</th><th>Name</th><th>Department</th><th>Courses</th><th>Status</th><th class="text-end">Action</th></tr></thead>
<tbody>@foreach($teachers as $teacher)<tr><td>{{ $teacher->employee_id }}</td><td>{{ $teacher->user->name }}</td><td>{{ $teacher->department }}</td><td>{{ $teacher->courses->pluck('name')->join(', ') }}</td><td><span class="badge bg-success">{{ $teacher->status }}</span></td><td class="text-end"><div class="d-inline-flex gap-2"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTeacherModal{{ $teacher->id }}">Edit</button><form method="post" action="{{ route('teachers.destroy', $teacher) }}" onsubmit="return confirm('Delete this teacher?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></div></td></tr>@endforeach</tbody>
</table></div><div class="card-body">{{ $teachers->links() }}</div></div>

@foreach($teachers as $teacher)
<div class="modal fade" id="editTeacherModal{{ $teacher->id }}" tabindex="-1" aria-labelledby="editTeacherModalLabel{{ $teacher->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" action="{{ route('teachers.update', $teacher) }}" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h2 class="modal-title h5" id="editTeacherModalLabel{{ $teacher->id }}">Edit Teacher</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4"><input class="form-control" name="name" value="{{ $teacher->user->name }}" placeholder="Full name" required></div>
                    <div class="col-md-4"><input class="form-control" type="email" name="email" value="{{ $teacher->user->email }}" placeholder="Email" required></div>
                    <div class="col-md-4"><input class="form-control" name="mobile" value="{{ $teacher->user->mobile }}" placeholder="Mobile"></div>
                    <div class="col-md-4"><input class="form-control" name="employee_id" value="{{ $teacher->employee_id }}" placeholder="Employee ID" required></div>
                    <div class="col-md-4"><input class="form-control" name="department" value="{{ $teacher->department }}" placeholder="Department"></div>
                    <div class="col-md-4"><input class="form-control" name="qualification" value="{{ $teacher->qualification }}" placeholder="Qualification"></div>
                    <div class="col-md-4"><input class="form-control" type="number" min="0" name="experience_years" value="{{ $teacher->experience_years }}" placeholder="Experience years"></div>
                    <div class="col-md-4"><input class="form-control" name="password" placeholder="New password"></div>
                    <div class="col-md-4"><select class="form-select" name="status" required><option value="active" @selected($teacher->status === 'active')>Active</option><option value="inactive" @selected($teacher->status === 'inactive')>Inactive</option></select></div>
                    <div class="col-12">
                        <select class="form-select" name="course_ids[]" multiple>
                            @foreach($courses as $course)<option value="{{ $course->id }}" @selected($teacher->courses->contains($course->id))>{{ $course->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Update Teacher</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endsection
