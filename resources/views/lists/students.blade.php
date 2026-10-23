@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Students</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">Add Student</button>
</div>

<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="post" action="{{ route('students.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h2 class="modal-title h5" id="addStudentModalLabel">Add Student</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-3"><input class="form-control" name="name" placeholder="Full name" required></div>
                    <div class="col-md-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
                    <div class="col-md-2"><input class="form-control" name="mobile" placeholder="Mobile"></div>
                    <div class="col-md-2"><input class="form-control" name="admission_number" placeholder="Admission no." required></div>
                    <div class="col-md-2"><input class="form-control" name="password" placeholder="Password"></div>
                    <div class="col-md-2"><select class="form-select" name="course_id" required>@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><select class="form-select" name="batch_id"><option value="">No batch</option>@foreach($batches as $batch)<option value="{{ $batch->id }}">{{ $batch->name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><select class="form-select" name="gender"><option value="">Gender</option><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option></select></div>
                    <div class="col-md-2"><input class="form-control" type="date" name="dob"></div>
                    <div class="col-md-2"><input class="form-control" name="blood_group" placeholder="Blood group"></div>
                    <div class="col-md-2"><input class="form-control" name="aadhaar_number" placeholder="Aadhaar"></div>
                    <div class="col-md-3"><input class="form-control" name="father_name" placeholder="Father name"></div>
                    <div class="col-md-3"><input class="form-control" name="mother_name" placeholder="Mother name"></div>
                    <div class="col-md-3"><input class="form-control" name="parent_mobile" placeholder="Parent mobile"></div>
                    <div class="col-md-3"><input class="form-control" type="email" name="parent_email" placeholder="Parent email"></div>
                    <div class="col-12"><textarea class="form-control" name="address" rows="2" placeholder="Address"></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Create Student</button>
            </div>
        </form>
    </div>
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr><th>ID</th><th>Name</th><th>Admission</th><th>Courses</th><th>Attendance</th><th>Status</th><th class="text-end">Action</th></tr></thead>
<tbody>@foreach($students as $student)<tr><td>{{ $student->student_id }}</td><td>{{ $student->user->name }}</td><td>{{ $student->admission_number }}</td><td>{{ $student->courses->pluck('name')->join(', ') }}</td><td>@php($att = $student->attendancePercent())@if($att === null)<span class="text-secondary small">--</span>@else<strong class="{{ $att >= 75 ? 'text-success' : 'text-danger' }}">{{ $att }}%</strong>@endif</td><td><span class="badge bg-success">{{ $student->status }}</span></td><td class="text-end"><div class="d-inline-flex gap-2"><a class="btn btn-sm btn-outline-secondary" href="{{ route('students.performance', $student) }}">Performance</a><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editStudentModal{{ $student->id }}">Edit</button><form method="post" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Delete this student?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></div></td></tr>@endforeach</tbody>
</table></div><div class="card-body">{{ $students->links() }}</div></div>

@foreach($students as $student)
<div class="modal fade" id="editStudentModal{{ $student->id }}" tabindex="-1" aria-labelledby="editStudentModalLabel{{ $student->id }}" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="post" action="{{ route('students.update', $student) }}" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h2 class="modal-title h5" id="editStudentModalLabel{{ $student->id }}">Edit Student</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-3"><input class="form-control" name="name" value="{{ $student->user->name }}" placeholder="Full name" required></div>
                    <div class="col-md-3"><input class="form-control" type="email" name="email" value="{{ $student->user->email }}" placeholder="Email" required></div>
                    <div class="col-md-2"><input class="form-control" name="mobile" value="{{ $student->user->mobile }}" placeholder="Mobile"></div>
                    <div class="col-md-2"><input class="form-control" name="admission_number" value="{{ $student->admission_number }}" placeholder="Admission no." required></div>
                    <div class="col-md-2"><input class="form-control" name="password" placeholder="New password"></div>
                    <div class="col-md-2">
                        <select class="form-select" name="course_id" required>
                            @foreach($courses as $course)<option value="{{ $course->id }}" @selected($student->courses->contains($course->id))>{{ $course->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="batch_id">
                            <option value="">No batch</option>
                            @foreach($batches as $batch)<option value="{{ $batch->id }}" @selected($student->batches->contains($batch->id))>{{ $batch->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><select class="form-select" name="gender"><option value="">Gender</option><option value="female" @selected($student->gender === 'female')>Female</option><option value="male" @selected($student->gender === 'male')>Male</option><option value="other" @selected($student->gender === 'other')>Other</option></select></div>
                    <div class="col-md-2"><input class="form-control" type="date" name="dob" value="{{ $student->dob?->format('Y-m-d') }}"></div>
                    <div class="col-md-2"><input class="form-control" name="blood_group" value="{{ $student->blood_group }}" placeholder="Blood group"></div>
                    <div class="col-md-2"><input class="form-control" name="aadhaar_number" value="{{ $student->aadhaar_number }}" placeholder="Aadhaar"></div>
                    <div class="col-md-2"><select class="form-select" name="status" required><option value="active" @selected($student->status === 'active')>Active</option><option value="inactive" @selected($student->status === 'inactive')>Inactive</option></select></div>
                    <div class="col-md-3"><input class="form-control" name="father_name" value="{{ $student->parent?->father_name }}" placeholder="Father name"></div>
                    <div class="col-md-3"><input class="form-control" name="mother_name" value="{{ $student->parent?->mother_name }}" placeholder="Mother name"></div>
                    <div class="col-md-3"><input class="form-control" name="guardian_name" value="{{ $student->parent?->guardian_name }}" placeholder="Guardian name"></div>
                    <div class="col-md-3"><input class="form-control" name="parent_mobile" value="{{ $student->parent?->mobile }}" placeholder="Parent mobile"></div>
                    <div class="col-md-3"><input class="form-control" type="email" name="parent_email" value="{{ $student->parent?->email }}" placeholder="Parent email"></div>
                    <div class="col-12"><textarea class="form-control" name="address" rows="2" placeholder="Address">{{ $student->address }}</textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Update Student</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endsection
