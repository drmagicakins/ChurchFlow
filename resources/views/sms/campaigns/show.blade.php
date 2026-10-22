<x-layout :title="$campaign->name . ' · SMS campaign · ChurchFlow'">
    @php
        $canSend = auth()->user()->hasPermission('sms.manage');
        $audienceLabels = [
            'church' => 'Entire church',
            'church_wide' => 'Entire church',
            'branch' => 'A specific branch',
            'department' => 'A department',
            'group' => 'A group',
        ];
        $audienceName = match ($campaign->audience_type) {
            'branch' => \App\Models\OrganizationalUnit::find($campaign->organizational_unit_id)?->name,
            'department' => \App\Models\Department::find($campaign->department_id)?->name,
            'group' => \App\Models\Group::find($campaign->group_id)?->name,
            default => null,
        };
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('sms.campaigns.index') }}">← All campaigns</a></p>
            <h1 style="margin:.35rem 0 0">{{ $campaign->name }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                @php $tone = match ($campaign->status) {
                    'completed', 'sent' => 'cf-badge--ok',
                    'partially_failed' => 'cf-badge--warn',
                    'failed' => 'cf-badge--danger',
                    default => 'cf-badge--brand',
                }; @endphp
                <span class="cf-badge {{ $tone }}">{{ str_replace('_', ' ', ucfirst($campaign->status)) }}</span>
                · {{ $audienceLabels[$campaign->audience_type] ?? str_replace('_', ' ', $campaign->audience_type) }}
                @if($audienceName) · {{ $audienceName }} @endif
            </p>
        </div>
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    <div class="cf-grid cf-grid--2">
        {{-- The message --}}
        <div class="cf-card">
            <div class="cf-card__head">
                <h2>Message</h2>
                <span class="cf-tiny cf-muted">{{ mb_strlen($campaign->message) }} characters</span>
            </div>
            <div class="cf-card__body">
                <div style="white-space:pre-line;line-height:1.6">{{ $campaign->message }}</div>
            </div>
        </div>

        {{-- Estimate / confirmation --}}
        @if($estimate)
            <div class="cf-card">
                <div class="cf-card__head">
                    <h2>Before you send</h2>
                    <span class="cf-tiny cf-muted">Nothing is charged until you confirm</span>
                </div>
                <div class="cf-card__body">
                    <table class="cf-table" style="width:100%">
                        <tbody>
                            <tr><th scope="row" style="text-align:left;font-weight:500">Recipients with a phone number</th>
                                <td class="cf-mono" style="text-align:right">{{ number_format($estimate['recipient_count']) }}</td></tr>
                            <tr><th scope="row" style="text-align:left;font-weight:500">SMS segments per message</th>
                                <td class="cf-mono" style="text-align:right">{{ $estimate['segments_per_message'] }}</td></tr>
                            <tr style="font-weight:700"><th scope="row" style="text-align:left">Total SMS units required</th>
                                <td class="cf-mono" style="text-align:right">{{ number_format($estimate['total_units']) }}</td></tr>
                            <tr><th scope="row" style="text-align:left;font-weight:500">Available credits</th>
                                <td class="cf-mono" style="text-align:right">{{ number_format($estimate['available_units']) }}</td></tr>
                            <tr><th scope="row" style="text-align:left;font-weight:500">Remaining after send</th>
                                <td class="cf-mono" style="text-align:right">{{ number_format($estimate['remaining_after_send']) }}</td></tr>
                        </tbody>
                    </table>

                    <div style="margin-top:1rem">
                        @if($estimate['sufficient_credits'])
                            @if($canSend)
                                <form method="POST" action="{{ route('sms.campaigns.confirm', $campaign) }}"
                                    data-confirm="Confirm and send this campaign to {{ number_format($estimate['recipient_count']) }} recipient(s)? {{ number_format($estimate['total_units']) }} SMS units will be reserved immediately."
                                    data-confirm-title="Send this campaign?" data-confirm-ok="Confirm &amp; send">
                                    @csrf
                                    <button type="submit" class="cf-btn cf-btn--primary">
                                        <x-ui.icon name="chat" class="h-4 w-4" /> Confirm &amp; send
                                    </button>
                                </form>
                                <p class="cf-tiny cf-muted" style="margin:.5rem 0 0">
                                    Confirming reserves the exact units shown — the number you approve is the number charged,
                                    even if membership changes afterwards.
                                </p>
                            @else
                                <p class="cf-small cf-muted" style="margin:0">You do not have permission to send SMS campaigns.</p>
                            @endif
                        @else
                            <div class="cf-alert cf-alert--warn" role="status">
                                <span class="cf-alert__icon"><x-ui.icon name="info" class="h-4 w-4" /></span>
                                <div class="cf-alert__body">
                                    <p class="cf-alert__title">Not enough SMS credits</p>
                                    <p class="cf-small" style="margin:.3rem 0 0">
                                        This campaign needs {{ number_format($estimate['total_units']) }} units but the wallet
                                        holds {{ number_format($estimate['available_units']) }}.
                                        <a href="{{ route('sms.wallet.show') }}">Top up your SMS credits</a>.
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="cf-card">
                <div class="cf-card__head"><h2>Delivery</h2></div>
                <div class="cf-card__body">
                    <table class="cf-table" style="width:100%">
                        <tbody>
                            <tr><th scope="row" style="text-align:left;font-weight:500">Reserved units</th>
                                <td class="cf-mono" style="text-align:right">{{ number_format((int) $campaign->reserved_units) }}</td></tr>
                            <tr><th scope="row" style="text-align:left;font-weight:500">Sent at</th>
                                <td style="text-align:right">{{ $campaign->sent_at?->format('d M Y, H:i') ?? 'Queued' }}</td></tr>
                            <tr><th scope="row" style="text-align:left;font-weight:500">Recipients</th>
                                <td class="cf-mono" style="text-align:right">{{ number_format($recipients->total()) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- Recipient snapshot --}}
    @if($recipients->total() > 0)
        <div class="cf-card cf-card--flush" style="margin-top:1.2rem">
            <div class="cf-card__head">
                <h2>Recipients</h2>
                <span class="cf-tiny cf-muted">{{ number_format($recipients->total()) }} snapshotted at confirm time</span>
            </div>
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr><th>Member</th><th>Phone</th><th style="text-align:right">Segments</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach($recipients as $recipient)
                            <tr>
                                <td>{{ $recipient->member?->full_name ?? '—' }}</td>
                                <td class="cf-mono cf-small">{{ $recipient->phone }}</td>
                                <td class="cf-mono" style="text-align:right">{{ $recipient->segments }}</td>
                                <td>
                                    @php $rtone = match ($recipient->status) {
                                        'sent', 'delivered' => 'cf-badge--ok',
                                        'failed' => 'cf-badge--danger',
                                        default => 'cf-badge--warn',
                                    }; @endphp
                                    <span class="cf-badge {{ $rtone }}">{{ ucfirst($recipient->status) }}</span>
                                    @if($recipient->failed_reason)
                                        <div class="cf-tiny cf-muted">{{ \Illuminate\Support\Str::limit($recipient->failed_reason, 50) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($recipients->hasPages())
            <div style="padding:1rem 1.35rem">{{ $recipients->links() }}</div>
        @endif
    @endif
</x-layout>
