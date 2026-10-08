<x-layout>
    <h1>{{ $ruleSet->name }}</h1>
    <table>
        <thead><tr><th>Rule</th><th>Type</th><th>Base</th><th>Rate</th><th>Classification</th></tr></thead>
        <tbody>
        @foreach($ruleSet->rules as $rule)
            <tr>
                <td>{{ $rule->name }}</td>
                <td>{{ $rule->type }}</td>
                <td>{{ $rule->base_field }}</td>
                <td>{{ $rule->rate }}</td>
                <td>{{ $rule->classification }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-layout>
