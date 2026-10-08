<x-layout>
    <h1>Tasks</h1>
    <ul>
    @forelse($tasks as $task)
        <li>{{ $task->title }} — {{ $task->status }}</li>
    @empty
        <li>No tasks yet.</li>
    @endforelse
    </ul>
</x-layout>
