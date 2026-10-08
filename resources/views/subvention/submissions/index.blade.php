<x-layout>
    <h1>Subvention Submissions</h1>
    <table>
        <thead><tr><th>Branch</th><th>Period</th><th>Status</th><th>Remittance</th></tr></thead>
        <tbody>
        @forelse($submissions as $s)
            <tr>
                <td><a href="{{ route('subvention.submissions.show', $s) }}">{{ $s->organizationalUnit->name }}</a></td>
                <td>{{ $s->period->name }}</td>
                <td>{{ $s->status }}</td>
                <td>{{ $s->latestCalculation->remittance_amount ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">No submissions yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</x-layout>
