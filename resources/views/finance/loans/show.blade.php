<x-layout>
    <h1>{{ $loan->borrowerLabel() }}</h1>
    <p>Principal: {{ $loan->principal_amount }}</p>
    <p>Outstanding: {{ $outstanding }}</p>
    <p>Months paid: {{ $monthsPaid }}</p>
</x-layout>
