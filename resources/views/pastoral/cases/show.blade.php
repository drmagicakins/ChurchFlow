<x-layout>
    <h1>{{ $case->member->full_name }} — {{ $case->type }}</h1>
    <p>Status: {{ $case->status }}</p>
    <h2>Notes</h2>
    <ul>
    @foreach($case->notes as $note)
        <li>{{ $note->created_at }}: {{ $note->note }}</li>
    @endforeach
    </ul>
</x-layout>
