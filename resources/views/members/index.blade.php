<x-layout>
    <h1>Members</h1>
    <form method="GET">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search members...">
        <button type="submit">Search</button>
    </form>
    <table>
        <thead><tr><th>#</th><th>Name</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($members as $member)
            <tr>
                <td>{{ $member->membership_number }}</td>
                <td><a href="{{ route('members.show', $member) }}">{{ $member->full_name }}</a></td>
                <td>{{ $member->membership_status }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Your church doesn't have any members yet. <a href="#">Add Member</a></td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $members->links() }}
</x-layout>
