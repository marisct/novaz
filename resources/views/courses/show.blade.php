@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">{{ $course->name }}</h1>
        <p class="text-secondary mb-0">{{ $course->code }} @if($course->duration) &middot; {{ $course->duration }} @endif</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('courses.index') }}">Back to Courses</a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Create Subject</h2>
        <form method="post" action="{{ route('subjects.store', $course) }}" class="row g-3">
            @csrf
            <div class="col-md-5"><input class="form-control" name="name" placeholder="Subject name" required></div>
            <div class="col-md-3"><input class="form-control" name="code" placeholder="Subject code"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Create Subject</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach($course->subjects as $subject)
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h2 class="h5 mb-1">{{ $subject->name }}</h2>
                            <div class="text-secondary small">{{ $subject->code }}</div>
                        </div>
                        <span class="badge text-bg-success">{{ $subject->status }}</span>
                    </div>
                    <form method="post" action="{{ route('subjects.materials.store', $subject) }}" enctype="multipart/form-data" class="row g-2">
                        @csrf
                        <div class="col-md-5"><input class="form-control" name="title" placeholder="Note title" required></div>
                        <div class="col-md-3"><select class="form-select" name="type"><option value="pdf">PDF Note</option><option value="word">Word Note</option></select></div>
                        <div class="col-md-4"><input class="form-control" type="file" name="file" accept=".pdf,.doc,.docx" required></div>
                        <div class="col-md-4"><input class="form-control" type="number" name="year" placeholder="Year"></div>
                        <div class="col-md-4"><button class="btn btn-outline-primary w-100">Upload Note</button></div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h5">Subject Notes</h2>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Title</th><th>Subject</th><th>Type</th><th>File</th><th></th></tr></thead>
            <tbody>
            @forelse($materials as $material)
                <tr>
                    <td>{{ $material->title }}</td>
                    <td>{{ $material->subject->name }}</td>
                    <td>{{ strtoupper($material->type) }}</td>
                    <td>{{ $material->file_name }}</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('materials.download', $material) }}">Download</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-secondary">No subject notes uploaded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $materials->links() }}</div>
</div>
@endsection
