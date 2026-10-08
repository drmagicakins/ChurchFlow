<x-layout>
    <h1>{{ $campaign->name }}</h1>
    <p>{{ $campaign->message }}</p>
    <p>Status: {{ $campaign->status }}</p>

    @if($estimate)
        <h2>Before you send</h2>
        <p>Recipients: {{ $estimate['recipient_count'] }}</p>
        <p>SMS segments per recipient: {{ $estimate['segments_per_message'] }}</p>
        <p>Total SMS units required: {{ $estimate['total_units'] }}</p>
        <p>Available credits: {{ $estimate['available_units'] }}</p>
        <p>Remaining after send: {{ $estimate['remaining_after_send'] }}</p>

        @if($estimate['sufficient_credits'])
            <form method="POST" action="{{ route('sms.campaigns.confirm', $campaign) }}">@csrf
                <button>Confirm & Send</button>
            </form>
        @else
            <p><strong>Insufficient credits — <a href="{{ route('sms.wallet.show') }}">buy more SMS credits</a>.</strong></p>
        @endif
    @endif
</x-layout>
