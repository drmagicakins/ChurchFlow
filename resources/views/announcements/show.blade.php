<x-layout :title="$announcement->title . ' · Announcements · ChurchFlow'">
    @php
        $canManage = auth()->user()->hasPermission('announcements.manage');

        // Human labels for the stored audience_type, so the page reads the way
        // the composer phrased the choice rather than showing the raw enum.
        $audienceLabels = [
            'church' => 'Entire church',
            'church_wide' => 'Entire church',
            'branch' => 'A specific branch',
            'department' => 'A department',
            'group' => 'A group',
        ];

        $audienceName = match ($announcement->audience_type) {
            'branch' => $announcement->organizationalUnit?->name,
            'department' => $announcement->department?->name,
            'group' => $announcement->group?->name,
            default => null,
        };
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('announcements.index') }}">← All announcements</a></p>
            <h1 style="margin:.35rem 0 0">{{ $announcement->title }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                <span class="cf-badge">{{ $audienceLabels[$announcement->audience_type] ?? str_replace('_', ' ', $announcement->audience_type) }}</span>
                @if ($audienceName)
                    <span style="margin-left:.4rem">{{ $audienceName }}</span>
                @endif
                <span style="margin-left:.4rem">· posted {{ $announcement->created_at?->diffForHumans() }}</span>
            </p>
        </div>
        @if ($canManage)
            <form method="POST" action="{{ route('announcements.destroy', $announcement) }}"
                data-confirm="Delete the announcement “{{ $announcement->title }}”? This cannot be undone."
                data-confirm-title="Delete announcement?" data-confirm-ok="Delete">
                @csrf
                @method('DELETE')
                <button type="submit" class="cf-btn cf-btn--danger cf-btn--sm">Delete announcement</button>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">{{ session('status') }}</p>
            </div>
        </div>
    @endif

    <div class="cf-card">
        <div class="cf-card__head">
            <h2>Message</h2>
            <span class="cf-tiny cf-muted">
                Published
                @if ($announcement->publish_at)
                    {{ $announcement->publish_at->format('d M Y, H:i') }}
                @else
                    immediately
                @endif
            </span>
        </div>
        <div class="cf-card__body">
            {{-- nl2br + e() so a plain-text announcement keeps its paragraphing
                 without ever rendering stored HTML. --}}
            <div style="white-space:pre-line;line-height:1.6">{!! nl2br(e($announcement->body)) !!}</div>
        </div>
    </div>

    <div class="cf-grid cf-grid--2" style="margin-top:1.2rem">
        <div class="cf-card">
            <div class="cf-card__head">
                <h2>Who received it</h2>
            </div>
            <div class="cf-card__body">
                <p class="cf-small" style="margin:0">
                    <strong>{{ $audienceLabels[$announcement->audience_type] ?? str_replace('_', ' ', $announcement->audience_type) }}</strong>
                    @if ($audienceName)
                        — {{ $audienceName }}
                    @endif
                </p>
                <p class="cf-tiny cf-muted" style="margin:.4rem 0 0">
                    Every member in this audience with an email address on file was emailed when the announcement was posted.
                </p>
            </div>
        </div>

        <div class="cf-card">
            <div class="cf-card__head">
                <h2>Details</h2>
            </div>
            <div class="cf-card__body">
                <table class="cf-table" style="width:100%">
                    <tbody>
                        <tr>
                            <th scope="row" style="text-align:left;font-weight:500">Posted by</th>
                            <td style="text-align:right">{{ $announcement->creator?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th scope="row" style="text-align:left;font-weight:500">Created</th>
                            <td style="text-align:right">{{ $announcement->created_at?->format('d M Y, H:i') ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th scope="row" style="text-align:left;font-weight:500">Scheduled for</th>
                            <td style="text-align:right">
                                {{ $announcement->publish_at?->format('d M Y, H:i') ?? 'Immediately' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layout>
