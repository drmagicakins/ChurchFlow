<x-layout>
    <h1>Attendance</h1>
    <ul>
    @forelse($sessions as $session)
        <li><a href="{{ route('attendance.show', $session) }}">{{ $session->name }} — {{ $session->session_date }}</a></li>
    @empty
        <li>No attendance sessions recorded yet.</li>
    @endforelse
    </ul>
</x-layout>
