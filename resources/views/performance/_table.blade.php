{{-- $rows: collection from SubjectPerformanceService, $first: 'subject' or 'student' --}}
<div class="table-responsive">
<table class="table align-middle mb-0 performance-table">
    <thead><tr>
        <th>{{ $first === 'subject' ? 'Subject' : 'Student' }}</th>
        <th class="text-end">Tests</th><th class="text-end">Questions</th><th class="text-end">Correct</th>
        <th class="text-end">Wrong</th><th class="text-end">Skipped</th><th class="text-end">Marks</th><th style="min-width:170px">Accuracy</th>
    </tr></thead>
    <tbody>
    @forelse($rows as $row)
        <tr>
            <td>
                @if($first === 'subject')
                    <strong>{{ $row['subject'] }}</strong><div class="small text-secondary">{{ $row['course'] }}</div>
                @else
                    <a class="fw-semibold text-decoration-none" href="{{ route('students.performance', $row['student_profile_id']) }}">{{ $row['name'] }}</a><div class="small text-secondary">{{ $row['student_id'] }}</div>
                @endif
            </td>
            <td class="text-end">{{ $row['tests'] }}</td>
            <td class="text-end">{{ $row['questions'] }}</td>
            <td class="text-end text-success">{{ $row['correct'] }}</td>
            <td class="text-end text-danger">{{ $row['wrong'] }}</td>
            <td class="text-end text-secondary">{{ $row['skipped'] }}</td>
            <td class="text-end">{{ rtrim(rtrim(number_format($row['marks'], 2), '0'), '.') }}</td>
            <td>
                @if($row['accuracy'] === null)
                    <span class="small text-secondary">No answers yet</span>
                @else
                    <div class="accuracy-meter" role="img" aria-label="{{ $row['accuracy'] }} percent accuracy"><div style="width:{{ $row['accuracy'] }}%" class="{{ $row['accuracy'] >= 70 ? 'good' : ($row['accuracy'] >= 40 ? 'fair' : 'low') }}"></div></div>
                    <span class="small fw-semibold">{{ $row['accuracy'] }}%</span>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center text-secondary py-4">{{ $first === 'subject' ? 'No subjects are linked to this student yet.' : 'No students are enrolled in this subject yet.' }}</td></tr>
    @endforelse
    </tbody>
</table>
</div>
