<x-layout>
    <h1>Feature Flags</h1>
    <table>
        <thead><tr><th>Key</th><th>Global</th><th>Overrides</th><th></th></tr></thead>
        <tbody>
        @foreach($flags as $flag)
            <tr>
                <td>{{ $flag->key }}</td>
                <td>{{ $flag->is_globally_enabled ? 'On' : 'Off' }}</td>
                <td>
                    @foreach($flag->churchOverrides as $override)
                        {{ $override->church->name }}: {{ $override->is_enabled ? 'On' : 'Off' }}<br>
                    @endforeach
                </td>
                <td>
                    <form method="POST" action="{{ route('platform-admin.feature-flags.toggle', $flag) }}">@csrf
                        <button>Toggle global</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-layout>
