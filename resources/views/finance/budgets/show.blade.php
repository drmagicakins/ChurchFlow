<x-layout>
    <h1>{{ $budget->name }}</h1>
    <table>
        <thead><tr><th>Category</th><th>Planned</th><th>Actual</th><th>Variance</th></tr></thead>
        <tbody>
        @foreach($variance as $row)
            <tr>
                <td>{{ $row['category'] }}</td>
                <td>{{ $row['planned'] }}</td>
                <td>{{ $row['actual'] }}</td>
                <td>{{ $row['variance'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-layout>
