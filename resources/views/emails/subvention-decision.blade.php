<!doctype html>
<html>
<body>
<p>
    Your subvention submission for {{ $submission->organizationalUnit->name }}
    ({{ $submission->period->name }}) has been <strong>{{ $decision }}</strong>.
</p>

@if($decision === 'returned' && $submission->return_reason)
    <p>Reason: {{ $submission->return_reason }}</p>
@endif

@if($decision === 'approved' && $submission->latestCalculation)
    <p>Remittance due: {{ $submission->latestCalculation->remittance_amount }}</p>
@endif
</body>
</html>
