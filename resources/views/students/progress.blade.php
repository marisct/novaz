<div class="workspace-columns">
<section class="data-section" aria-label="Weekly performance">
    <div class="section-title"><h2>Weekly performance</h2><span class="badge text-bg-light">Last 8 weeks</span></div>
    <p class="small text-secondary">Week of {{ $progress['current']['label'] }} &middot; {{ $progress['timezone'] }}</p>
    <div class="weekly-stats">
        <div><strong>{{ $progress['current']['tests'] }}</strong><small>Tests this week</small></div>
        <div><strong>{{ $progress['current']['accuracy'] !== null ? $progress['current']['accuracy'].'%' : '--' }}</strong><small>Answer accuracy</small></div>
        <div><strong>{{ $progress['accuracy_change'] !== null ? sprintf('%+.1f pp', $progress['accuracy_change']) : '--' }}</strong><small>Vs. last week</small></div>
    </div>
    <div class="week-chart">
        @foreach($progress['weeks'] as $week)
        <div class="week-column" title="{{ $week['tests'] }} tests">
            <b>{{ $week['accuracy'] !== null ? $week['accuracy'].'%' : '--' }}</b>
            <div class="week-track" role="img" aria-label="{{ $week['label'] }}: {{ $week['accuracy'] !== null ? $week['accuracy'].' percent' : 'no answers' }}"><div class="week-fill" style="height:{{ $week['accuracy'] ?? 0 }}%"></div></div>
            <span>{{ $week['label'] }}</span>
        </div>
        @endforeach
    </div>
    @if(!$progress['current']['tests'])<p class="small text-secondary">Your next completed test will bring this week into focus.</p>@endif
    @foreach($progress['sections'] as $section)<div class="activity-row"><div class="row-copy"><strong>{{ $section['name'] }}</strong></div><span>{{ $section['accuracy'] !== null ? $section['accuracy'].'%' : 'No answers' }}</span></div>@endforeach
    <div class="section-title"><h2>Recent results</h2></div>
    @forelse($payload['recent_results'] as $attempt)<a class="activity-row text-decoration-none" href="{{ route('attempts.result',$attempt) }}"><div class="row-icon"><i data-lucide="clipboard-check"></i></div><div class="row-copy"><strong>{{ $attempt->exam?->title }}</strong><small>{{ $attempt->submitted_at?->format('d M Y') }}</small></div><strong>{{ $attempt->score }}</strong><i data-lucide="chevron-right"></i></a>@empty<p class="empty-copy">No completed tests yet. Your results will be collected here.</p>@endforelse
</section>
<section class="data-section" id="exam-notifications" aria-label="Exam notifications">
    <div class="section-title"><h2>Exam notifications <span class="badge text-bg-primary">{{ count($notifications) }}</span></h2><i data-lucide="bell"></i></div>
    @forelse($notifications as $notification)
        <div class="activity-row align-items-start"><div class="row-icon"><i data-lucide="clipboard-list"></i></div><div class="row-copy">
            <span class="notice-status">{{ $notification['state'] === 'available' ? 'Ready to practice' : 'Upcoming' }}</span><strong>{{ $notification['title'] }}</strong><small>{{ $notification['course'] }} &middot; {{ $notification['duration_minutes'] }} min</small>
            <p class="small text-secondary mt-2 mb-2">{{ $notification['starts_at'] ? \Carbon\Carbon::parse($notification['starts_at'])->format('d M, H:i T') : 'Available now' }}</p>
            @if($notification['state'] === 'available')<form method="POST" action="{{ route('exams.start', $notification['exam_id']) }}">@csrf<button class="btn btn-outline-primary btn-sm">Open test <i data-lucide="arrow-right"></i></button></form>@endif
        </div></div>
    @empty<p class="empty-copy">You're all caught up. New exams for your courses will appear here.</p>@endforelse
    <div class="section-title"><h2>Your courses</h2><i data-lucide="book-open"></i></div>
    @forelse($payload['enrolled_courses'] ?? [] as $course)
        <div class="activity-row"><div class="row-icon"><i data-lucide="{{ str_contains(strtolower($course->name),'rrb') ? 'train-front' : 'book-open' }}"></i></div><div class="row-copy"><strong>{{ $course->name }}</strong><small>{{ $course->subjects_count }} subjects</small></div><a href="{{ route('materials.index', ['course_id'=>$course->id]) }}" class="icon-button" aria-label="Study materials for {{ $course->name }}" title="Study materials"><i data-lucide="arrow-up-right"></i></a></div>
    @empty<p class="empty-copy">Your enrolled courses will appear here.</p>@endforelse
</section>
</div>
