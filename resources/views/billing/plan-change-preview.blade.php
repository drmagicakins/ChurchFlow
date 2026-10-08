<x-layout>
    <h1>Change plan</h1>
    <p>{{ $subscription->plan->name }} → {{ $newPlan->name }}</p>
    <p>Days remaining in this billing period: {{ $proration['days_remaining'] }} / {{ $proration['days_in_period'] }}</p>
    <p>Credit for unused time on {{ $subscription->plan->name }}: {{ $proration['credit'] }}</p>
    <p>Charge for remaining time on {{ $newPlan->name }}: {{ $proration['charge'] }}</p>
    <p><strong>Net {{ $proration['net'] >= 0 ? 'charge' : 'credit' }}: {{ $proration['net'] }}</strong></p>

    <form method="POST" action="{{ route('billing.change-plan') }}">@csrf
        <input type="hidden" name="plan_id" value="{{ $newPlan->id }}">
        <button>Confirm plan change</button>
    </form>
</x-layout>
