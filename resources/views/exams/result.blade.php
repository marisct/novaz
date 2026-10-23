@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-4">
    <div><h1 class="h3 mb-1">{{ $attempt->exam->title }} Scorecard</h1><p class="text-secondary mb-0">Submitted {{ $attempt->submitted_at?->format('d M Y, h:i A') }}</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('exams.index') }}">Back to Exams</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-secondary">Score</div><div class="h2 mb-0">{{ number_format((float) $attempt->score, 2) }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-secondary">Accuracy</div><div class="h2 mb-0">{{ $attempt->accuracy_percent }}%</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-secondary">Correct</div><div class="h2 text-success mb-0">{{ $attempt->correct_count }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-secondary">Wrong</div><div class="h2 text-danger mb-0">{{ $attempt->wrong_count }}</div></div></div></div>
    <div class="col-md-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-secondary">Skipped</div><div class="h2 mb-0">{{ $attempt->skipped_count }}</div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white"><h2 class="h5 mb-0">Section Breakdown</h2></div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Section</th><th>Score</th><th>Correct</th><th>Wrong</th><th>Skipped</th><th>Accuracy</th><th>Time</th></tr></thead>
        <tbody>@foreach($attempt->section_scores ?? [] as $section)@continue(!array_key_exists('score', $section))<tr>
            <td>{{ $section['name'] }}</td><td>{{ number_format($section['score'], 2) }}</td><td>{{ $section['correct'] }}</td>
            <td>{{ $section['wrong'] }}</td><td>{{ $section['skipped'] }}</td><td>{{ $section['accuracy'] }}%</td>
            <td>{{ gmdate('i:s', $section['time_seconds']) }}</td>
        </tr>@endforeach</tbody>
    </table></div>
</div>

<h2 class="h4 mb-3">Answer Review</h2>
@if(isset($attempt->section_scores['dest']))
    <p>Typing practice: {{ $attempt->section_scores['dest']['characters_typed'] }} / {{ $attempt->section_scores['dest']['target_characters'] }} characters. No official qualification verdict.</p>
@endif
@foreach($attempt->exam->questions->sortBy('pivot.position')->values() as $index => $question)
    @php($answer = $attempt->answers->firstWhere('question_id', $question->id))
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between gap-3">
                <h3 class="h6 lh-base">{{ $index + 1 }}. {{ $question->question }}</h3>
                <span class="badge {{ $answer?->is_correct ? 'text-bg-success' : (blank($answer?->answer) ? 'text-bg-secondary' : 'text-bg-danger') }}">
                    {{ $answer?->is_correct ? 'Correct' : (blank($answer?->answer) ? 'Skipped' : 'Wrong') }}
                </span>
            </div>
            <p class="mb-1">Your answer: <strong>{{ $answer?->answer ?? 'Not attempted' }}</strong></p>
            <p class="mb-1">Correct answer: <strong>{{ $question->correct_answer }}</strong></p>
            @if($question->explanation)<p class="text-secondary mb-0">{{ $question->explanation }}</p>@endif
        </div>
    </div>
@endforeach
@endsection
