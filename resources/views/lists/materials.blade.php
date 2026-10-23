@extends('layouts.app')

@section('content')
@php($isPrevious = $tab === 'previous')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0">{{ $isPrevious ? 'Previous Year Question Papers' : 'Study Materials' }}</h1>
    <div class="btn-group" role="tablist" aria-label="Material type">
        <a class="btn {{ $isPrevious ? 'btn-outline-primary' : 'btn-primary' }}" href="{{ route('materials.index') }}">Notes</a>
        <a class="btn {{ $isPrevious ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('materials.index', ['tab' => 'previous']) }}">Previous Year Papers</a>
    </div>
</div>

@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@if(auth()->user()->role !== 'student')
<div class="card border-0 shadow-sm mb-4"><div class="card-body">
<h2 class="h6 text-secondary mb-3">{{ $isPrevious ? 'Upload a previous year question paper (with answers if you have them)' : 'Upload a note' }}</h2>
<form method="post" action="{{ route('materials.store') }}" enctype="multipart/form-data" class="row g-3">@csrf
<div class="col-md-3"><input class="form-control" name="title" placeholder="{{ $isPrevious ? 'e.g. SSC CGL Tier 1 - 2024 - Shift 1' : 'Title' }}" required></div>
<div class="col-md-2"><select class="form-select" name="course_id">@foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->name }}</option>@endforeach</select></div>
<div class="col-md-2"><select class="form-select" name="subject_id">@foreach($courses->flatMap->subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->course->name ?? '' }} - {{ $subject->name }}</option>@endforeach</select></div>
@if($isPrevious)
<input type="hidden" name="type" value="previous_question">
<div class="col-md-1"><input class="form-control" type="number" name="year" placeholder="Year" min="1990" max="2100" value="{{ now()->year - 1 }}" required></div>
@else
<div class="col-md-1"><select class="form-select" name="type"><option value="note">Note</option><option value="pdf">PDF</option><option value="word">Word</option></select></div>
@endif
<div class="col-md-3"><input class="form-control" type="file" name="file" accept=".pdf,.doc,.docx" required></div>
<div class="col-md-1"><button class="btn btn-primary w-100">Upload</button></div>
</form>
<p class="small text-secondary mb-0 mt-2">PDF or Word, up to 10 MB.</p>
</div></div>
@endif

@if($materials->isEmpty())
    <div class="card border-0 shadow-sm"><div class="card-body text-center text-secondary py-5">
        {{ $isPrevious ? 'No previous year papers uploaded yet.' : 'No study materials uploaded yet.' }}
    </div></div>
@elseif($isPrevious)
    @foreach($materials->groupBy(fn ($m) => $m->year ?: 'Year not set') as $year => $papers)
    <h2 class="h5 mt-3 mb-2">{{ $year }}</h2>
    <div class="row g-3 mb-2">
        @foreach($papers as $material)
        <div class="col-md-6 col-xl-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h3 class="h6 mb-1">{{ $material->title }}</h3>
            <p class="text-secondary small mb-1">{{ $material->course?->name }} &middot; {{ $material->subject?->name }}</p>
            <p class="text-secondary small mb-3">{{ $material->file_name }}</p>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('materials.download', $material) }}"><i data-lucide="download"></i> Download</a>
        </div></div></div>
        @endforeach
    </div>
    @endforeach
@else
    <div class="row g-3">
        @foreach($materials as $material)
        <div class="col-md-6 col-xl-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h5">{{ $material->title }}</h2>
            <p class="text-secondary mb-1">{{ $material->subject?->name }}</p>
            <p class="text-secondary mb-2">{{ $material->file_name }}</p>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('materials.download', $material) }}"><i data-lucide="download"></i> Download</a>
        </div></div></div>
        @endforeach
    </div>
@endif
<div class="mt-3">{{ $materials->links() }}</div>
@endsection
