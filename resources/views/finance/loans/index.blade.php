<x-layout>
    <h1>Loans</h1>
    <ul>
    @forelse($loans as $loan)
        <li>
            <a href="{{ route('finance.loans.show', $loan) }}">{{ $loan->borrowerLabel() }}</a>
            — outstanding: {{ $loanService->outstandingBalance($loan) }}
        </li>
    @empty
        <li>No loans yet.</li>
    @endforelse
    </ul>
</x-layout>
