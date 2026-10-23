@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Exams</h1>
    @if(auth()->user()->role !== 'student')
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createExamModal">Create Exam</button>
    @endif
</div>

@if(auth()->user()->role !== 'student')
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Filter Question Bank</h2>
                <form method="get" action="{{ route('exams.index') }}" class="row g-3">
                    <div class="col-md-6">
                        <select class="form-select" name="course_id">
                            <option value="">All courses</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}" @selected((string) $selectedCourseId === (string) $course->id)>{{ $course->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" name="subject_id">
                            <option value="">All subjects</option>
                            @foreach($courses->flatMap->subjects as $subject)
                                <option value="{{ $subject->id }}" @selected((string) $selectedSubjectId === (string) $subject->id)>{{ $subject->course->name ?? '' }} - {{ $subject->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6"><button class="btn btn-outline-primary w-100">Apply Filter</button></div>
                    <div class="col-md-6"><a class="btn btn-outline-secondary w-100" href="{{ route('exams.index') }}">Clear</a></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-2">
                    <div>
                        <h2 class="h5 mb-1">Bulk Upload Questions</h2>
                        <p class="text-secondary small mb-0">Upload MCQ and yes/no questions from an Excel file (.xlsx). Keep "Custom / general" to use the questions in every exam pattern.</p>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('questions.template') }}">Download Excel Format</a>
                </div>
                <form method="post" action="{{ route('questions.bulk-upload') }}" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <div class="col-md-4">
                        <select class="form-select" name="course_id" required>
                            @foreach($courses as $course)<option value="{{ $course->id }}">{{ $course->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="subject_id" required>
                            @foreach($courses->flatMap->subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->course->name ?? '' }} - {{ $subject->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4"><input class="form-control" type="file" name="file" accept=".xlsx,.csv,.txt,.xls" required></div>
                    <div class="col-md-4">
                        <select class="form-select pattern-select" name="exam_pattern_id">
                            <option value="">Custom / general</option>
                            @foreach($patterns as $pattern)<option value="{{ $pattern->id }}">{{ $pattern->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select section-select" name="section_key">
                            <option value="">Section from file</option>
                            @foreach($patterns as $pattern)@foreach($pattern->sections as $section)
                                <option value="{{ $section->key }}" data-pattern="{{ $pattern->id }}">{{ $pattern->code }} · {{ $section->name }}</option>
                            @endforeach @endforeach
                        </select>
                    </div>
                    <div class="col-md-4"><select class="form-select" name="language"><option value="english">English</option><option value="hindi">Hindi</option></select></div>
                    <div class="col-12">
                        <button class="btn btn-primary">Upload Questions</button>
                        <span class="text-secondary small ms-2">Columns: question, type, option_a, option_b, option_c, option_d, option_e (optional), answer, section, language, difficulty, marks, explanation, topic</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<form method="post" action="{{ route('exams.store') }}" id="createExamForm">
    @csrf
    <div class="modal fade" id="createExamModal" tabindex="-1" aria-labelledby="createExamModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="createExamModalLabel">Create Exam From Selected Questions</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4"><input class="form-control" name="title" placeholder="Exam title" required></div>
                        <div class="col-md-3"><select class="form-select" name="course_id">@foreach($courses as $course)<option value="{{ $course->id }}" @selected((string) $selectedCourseId === (string) $course->id)>{{ $course->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><select class="form-select" name="type"><option value="daily_quiz">Daily Quiz</option><option value="weekly_test">Weekly Test</option><option value="monthly_test">Monthly Test</option><option value="mock_test">Mock Test</option></select></div>
                        <div class="col-md-4"><select class="form-select" name="exam_pattern_id" required><option value="">Choose SSC/RRB pattern</option>@foreach($patterns as $pattern)<option value="{{ $pattern->id }}">{{ $pattern->name }}</option>@endforeach</select></div>
                        <div class="col-md-2"><input class="form-control" type="number" name="total_questions" value="10" min="1"></div>
                        <div class="col-md-3"><input class="form-control" type="number" name="duration_minutes" value="30" min="1"></div>
                        <div class="col-md-3"><input class="form-control" type="datetime-local" name="starts_at"></div>
                        <div class="col-md-3"><input class="form-control" type="datetime-local" name="ends_at"></div>
                        <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="passing_marks" placeholder="Passing"></div>
                        <div class="col-md-1"><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="negative_marking" value="1"> Neg.</label></div>
                        <div class="col-12"><label for="destPassage" class="form-label">Tier 2 typing passage (1,900-2,100 characters)</label><textarea id="destPassage" name="dest_passage" class="form-control" rows="5" maxlength="2100"></textarea></div>
                        <div class="col-12" id="patternDetails" aria-live="polite"></div>
                    </div>
                    <hr>
                    <p class="text-secondary mb-2">Select questions from the question bank below before opening this popup. Selected questions are included in the exam. If none are selected, the system will auto-pick random balanced questions.</p>
                    <div id="selectedQuestionsPreview" class="small text-secondary">No questions selected yet.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Create Exam</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h2 class="h5 mb-1">Question Bank</h2>
                <p class="text-secondary mb-0">Choose existing questions, then click Create Exam.</p>
            </div>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createExamModal">Create With Selected</button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th></th><th>Question</th><th>Course</th><th>Subject</th><th>Answer</th><th>Marks</th></tr></thead>
                <tbody>
                @foreach($questions as $question)
                    <tr>
                        <td><input class="form-check-input question-picker" form="createExamForm" type="checkbox" name="question_ids[]" value="{{ $question->id }}" data-question="{{ Str::limit($question->question, 80) }}"></td>
                        <td>{{ Str::limit($question->question, 110) }}</td>
                        <td>{{ $question->course->name }}</td>
                        <td>{{ $question->subject->name }}</td>
                        <td>{{ $question->correct_answer }}</td>
                        <td>{{ $question->marks }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            {{ $questions->links() }}
        </div>
    </div>
</form>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Pattern</th>
                    <th>Course</th>
                    <th>Starts</th>
                    <th>Questions</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($exams as $exam)
                    <tr>
                        <td>{{ $exam->title }}</td>
                        <td>{{ $exam->pattern?->name ?? str_replace('_', ' ', $exam->type) }}</td>
                        <td>{{ $exam->course->name }}</td>
                        <td>{{ $exam->starts_at ? $exam->starts_at->format('d M Y, H:i') : 'Any time' }}@if($exam->ends_at)<div class="small text-secondary">to {{ $exam->ends_at->format('d M Y, H:i') }}</div>@endif</td>
                        <td>{{ $exam->total_questions }}</td>
                        <td><span class="badge {{ ['upcoming' => 'text-bg-warning', 'closed' => 'text-bg-secondary', 'draft' => 'text-bg-light'][$exam->state] ?? 'text-bg-success' }}">{{ $exam->state === 'available' ? 'open' : $exam->state }}</span></td>
                        <td>
                            @if(auth()->user()->role === 'student')
                                @if($exam->state === 'available')
                                <form method="post" action="{{ route('exams.start', $exam) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-primary">Start / Resume</button>
                                </form>
                                @elseif($exam->state === 'upcoming')
                                <span class="small text-secondary">Opens {{ $exam->starts_at->format('d M, H:i') }}</span>
                                @else
                                <span class="small text-secondary">Closed</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        {{ $exams->links() }}
    </div>
</div>

<script>
const examPatterns = @json($patterns);
const patternPicker = document.querySelector('#createExamForm [name=exam_pattern_id]');
patternPicker?.addEventListener('change', function () {
    const pattern = examPatterns.find(item => String(item.id) === this.value);
    if (!pattern) return;
    const form = document.getElementById('createExamForm');
    form.elements.total_questions.value = pattern.sections.reduce((sum, section) => sum + section.question_count, 0);
    form.elements.duration_minutes.value = pattern.duration_minutes;
    form.elements.total_questions.readOnly = true;
    form.elements.duration_minutes.readOnly = true;
    form.elements.negative_marking.checked = pattern.sections.some(section => Number(section.negative_marks) > 0);
    const official = pattern.settings?.official_mock;
    document.getElementById('patternDetails').textContent = (official ? 'Official-pattern mock. Standard timing. ' : 'Practice pattern. ')
        + pattern.sections.map(section => section.name + ': ' + section.question_count + ' questions'
            + (section.duration_minutes ? ', ' + section.duration_minutes + ' min' : '')
            + (section.question_count ? ', +' + section.positive_marks + ' / -' + (pattern.settings?.rrb_third_penalty ? '1/3' : section.negative_marks) : '')).join(' | ');
    const passage = document.getElementById('destPassage');
    passage.required = !!pattern.settings?.dest;
    passage.parentElement.hidden = !pattern.settings?.dest;
});
document.addEventListener('change', function (event) {
    if (!event.target.matches('.question-picker')) return;
    const selected = Array.from(document.querySelectorAll('.question-picker:checked')).map(function (input) {
        return input.dataset.question;
    });
    const preview = document.getElementById('selectedQuestionsPreview');
    if (!preview) return;
    preview.textContent = selected.length ? selected.length + ' selected: ' + selected.join(' | ') : 'No questions selected yet.';
});
</script>
@endsection
