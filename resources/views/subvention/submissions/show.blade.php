<x-layout>
    <h1>{{ $submission->organizationalUnit->name }} — {{ $submission->period->name }}</h1>
    <p>Status: {{ $submission->status }}</p>

    @if($submission->latestCalculation)
        <h2>Latest Calculation</h2>
        <p>Retention: {{ $submission->latestCalculation->retention_total }}</p>
        <p>Deductions: {{ $submission->latestCalculation->deduction_total }}</p>
        <p>Shortfall: {{ $submission->latestCalculation->shortfall }}</p>
        <p>Loan deduction applied: {{ $submission->latestCalculation->loan_deduction_applied }}</p>
        <p><strong>Remittance due: {{ $submission->latestCalculation->remittance_amount }}</strong></p>
    @endif

    <form method="POST" action="{{ route('subvention.submissions.submit', $submission) }}">@csrf
        <button>Submit for Review</button>
    </form>
</x-layout>
