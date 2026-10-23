@extends('layouts.app')

@section('content')
@php
    $sessionBreak = app(\App\Services\ExamClock::class)->isBreak($attempt);
    $official = $attempt->exam->pattern?->settings['official_mock'] ?? false;
    $typing = $official && $attempt->current_section_key === 'dest';
@endphp
<div class="d-flex justify-content-between align-items-start gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">{{ $attempt->exam->title }}</h1>
        <div class="text-secondary">
            @if($currentSection)
                {{ $currentSection->name }} - Section {{ $currentSection->position }} of {{ $sections->count() }}
            @else
                All questions
            @endif
        </div>
    </div>
    <div class="border bg-white px-3 py-2 text-center" style="min-width:140px">
        <div class="small text-secondary">Time remaining</div>
        <strong class="fs-4" id="examTimer" data-seconds="{{ $remainingSeconds }}">00:00</strong>
    </div>
</div>

@if($sections->isNotEmpty())
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($sections as $section)
        @if(! $currentSection && $section->question_count)
            <a class="badge text-bg-light text-dark text-decoration-none" href="#section-{{ $section->key }}">{{ $section->name }}</a>
            @continue
        @endif
        <span class="badge {{ $section->key === $attempt->current_section_key ? 'text-bg-primary' : (in_array($section->key, $attempt->locked_sections ?? [], true) ? 'text-bg-secondary' : 'text-bg-light text-dark') }}">
            {{ $section->name }}
            @if(in_array($section->key, $attempt->locked_sections ?? [], true))
                - Locked
            @endif
        </span>
    @endforeach
</div>
@endif

<form method="post" action="{{ route('attempts.save', $attempt) }}" id="attemptForm">
    @csrf
    <input type="hidden" name="section_key" value="{{ $attempt->current_section_key }}">
    @if($sessionBreak)
        <p>Session I complete. Session II is a 15-minute typing test.</p>
        <button name="action" value="start_dest" class="btn btn-primary">Start typing session</button>
    @elseif($typing)
        <p style="white-space:pre-wrap">{{ $attempt->exam->dest_passage }}</p>
        <label for="destText" class="form-label">Type the passage</label>
        <textarea id="destText" name="dest_text" class="form-control mb-3" rows="10" maxlength="10000" spellcheck="false" autocomplete="off">{{ $attempt->dest_text }}</textarea>
    @endif
    @if(!$typing)
    @forelse($questions as $index => $question)
        {{-- One-timer papers show every section on the page, so each section gets a heading. --}}
        @if(! $currentSection && $sections->isNotEmpty() && ($index === 0 || $questions[$index - 1]->section_key !== $question->section_key) && ($heading = $sections->firstWhere('key', $question->section_key)))
            <h2 class="h5 mt-4 mb-3" id="section-{{ $heading->key }}">{{ $heading->name }} <span class="text-secondary fw-normal small">{{ $questions->where('section_key', $heading->key)->count() }} questions</span></h2>
        @endif
        <section class="card border-0 shadow-sm mb-3 question-card" data-question-id="{{ $question->id }}">
            <div class="card-body">
                <input class="question-time" type="hidden" name="question_times[{{ $question->id }}]" value="{{ optional($answers->get($question->id))->time_seconds ?? 0 }}">
                <div class="d-flex justify-content-between gap-3">
                    <h2 class="h6 lh-base">{{ $index + 1 }}. {{ $question->question }}</h2>
                    <span class="badge text-bg-light">{{ ucfirst($question->language) }}</span>
                </div>
                <div class="row g-2 mt-1">
                    @foreach(array_filter($question->options ?? []) as $key => $option)
                        <div class="col-md-6">
                            <label class="border bg-white d-flex gap-2 align-items-start p-3 w-100">
                                <input class="form-check-input mt-1" type="radio" name="answers[{{ $question->id }}]" value="{{ $key }}" @checked(optional($answers->get($question->id))->answer === $key)>
                                <span><strong>{{ $key }}.</strong> {{ $option }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @empty
        <div class="alert alert-warning">No questions are assigned to this section. Continue to the next section.</div>
    @endforelse
    @endif

    @if(!$sessionBreak)
    <div class="d-flex justify-content-between align-items-center border-top pt-3">
        <button class="btn btn-outline-secondary" name="action" value="save">Save Answers</button>
        <button class="btn btn-primary" name="action" value="continue" @disabled($official && $currentSection)>
            {{ $currentSection && $currentSection->position < $sections->count() ? 'Lock Section & Continue' : 'Submit Exam' }}
        </button>
    </div>
    @endif
</form>

<script>
(function () {
    const timer = document.getElementById('examTimer');
    const form = document.getElementById('attemptForm');
    if (@json($sessionBreak)) { timer.textContent = 'Break'; return; }
    let saving = Promise.resolve();
    let debounce;
    const status = document.createElement('span');
    status.setAttribute('role', 'status');
    form.appendChild(status);
    function autosave() {
        const data = new FormData(form);
        data.set('action', 'save');
        saving = saving.catch(() => {}).then(async () => {
            const response = await fetch(form.action, {method: 'POST', body: data, credentials: 'same-origin'});
            if (!response.ok) throw new Error('Save failed');
            status.textContent = 'Saved';
        }).catch(() => { status.textContent = 'Not saved. Check your connection and save again.'; });
    }
    form.addEventListener('change', autosave);
    form.addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(autosave, 300); });
    document.getElementById('destText')?.addEventListener('paste', event => event.preventDefault());
    document.getElementById('destText')?.addEventListener('drop', event => event.preventDefault());
    const timeInputs = new Map(Array.from(document.querySelectorAll('.question-card')).map(function (card) {
        return [card.dataset.questionId, card.querySelector('.question-time')];
    }));
    let activeQuestion = timeInputs.keys().next().value;
    let seconds = Number(timer.dataset.seconds || 0);
    const deadline = Date.now() + seconds * 1000;
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function (entries) {
            const visible = entries.filter(function (entry) { return entry.isIntersecting; })
                .sort(function (a, b) { return b.intersectionRatio - a.intersectionRatio; });
            if (visible[0]) activeQuestion = visible[0].target.dataset.questionId;
        }, { threshold: [0.25, 0.5, 0.75] });
        document.querySelectorAll('.question-card').forEach(function (card) { observer.observe(card); });
    }
    function render() {
        const minutes = Math.floor(seconds / 60);
        timer.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');
        if (seconds <= 60) timer.classList.add('text-danger');
    }
    render();
    const tick = window.setInterval(function () {
        const timeInput = timeInputs.get(activeQuestion);
        if (timeInput) timeInput.value = Number(timeInput.value || 0) + 1;
        seconds = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        render();
        if (seconds <= 0) {
            window.clearInterval(tick);
            const action = document.createElement('input');
            action.type = 'hidden';
            action.name = 'action';
            action.value = 'continue';
            form.appendChild(action);
            saving.finally(() => form.submit());
        }
    }, 1000);
})();
</script>
@endsection
