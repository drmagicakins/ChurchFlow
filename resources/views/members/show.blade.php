<x-layout :title="$member->full_name.' · Members · ChurchFlow'">
    @php
        $initials = \Illuminate\Support\Str::of($member->full_name)
            ->explode(' ')->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
        $photo = $member->photoUrl();
        $statusTone = match ($member->membership_status) {
            'active' => 'cf-badge--ok',
            'visitor' => 'cf-badge--brand',
            'inactive' => 'cf-badge--warn',
            'transferred', 'deceased' => 'cf-badge--danger',
            default => '',
        };
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('members.index') }}">← All members</a></p>
            <h1 style="margin:.35rem 0 0">{{ $member->full_name }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                <span class="cf-mono">{{ $member->membership_number }}</span>
                <span class="cf-badge {{ $statusTone }}" style="margin-left:.5rem">{{ str_replace('_', ' ', ucfirst($member->membership_status ?? 'unknown')) }}</span>
                @if($member->is_worker)
                    <span class="cf-badge cf-badge--brand" style="margin-left:.35rem">Worker</span>
                @endif
            </p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
            @if(auth()->user()->hasPermission('members.edit'))
                <a href="{{ route('members.index', ['q' => $member->membership_number]) }}" class="cf-btn cf-btn--secondary cf-btn--sm">Find in list</a>
            @endif
            @if(auth()->user()->hasPermission('members.delete'))
                <form method="POST" action="{{ route('members.destroy', $member) }}"
                    onsubmit="return confirm('Remove {{ $member->full_name }}? This can be restored by an administrator.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Remove member</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    <div class="cf-grid cf-grid--2">
        {{-- Identity --}}
        <div class="cf-card">
            <div class="cf-card__head"><h2>Profile</h2></div>
            <div class="cf-card__body" style="display:flex;gap:1.1rem;align-items:flex-start">
                <span class="cfd-user__avatar" style="width:64px;height:64px;font-size:1.4rem;flex-shrink:0;overflow:hidden" aria-hidden="true">
                    @if($photo)
                        <img src="{{ $photo }}" alt="" style="width:100%;height:100%;object-fit:cover">
                    @else
                        {{ $initials }}
                    @endif
                </span>

                <dl style="display:grid;grid-template-columns:auto 1fr;gap:.45rem 1rem;font-size:.9rem;margin:0;flex:1">
                    <dt class="cf-muted">Email</dt>
                    <dd style="margin:0">{{ $member->email ?: '—' }}</dd>

                    <dt class="cf-muted">Phone</dt>
                    <dd style="margin:0">{{ $member->phone ?: '—' }}</dd>

                    <dt class="cf-muted">Gender</dt>
                    <dd style="margin:0">{{ $member->gender ? ucfirst($member->gender) : '—' }}</dd>

                    <dt class="cf-muted">Date of birth</dt>
                    <dd style="margin:0">{{ $member->date_of_birth?->toFormattedDateString() ?? '—' }}</dd>

                    <dt class="cf-muted">Joined</dt>
                    <dd style="margin:0">{{ $member->date_joined?->toFormattedDateString() ?? '—' }}</dd>

                    <dt class="cf-muted">Baptised</dt>
                    <dd style="margin:0">{{ $member->baptism_date?->toFormattedDateString() ?? '—' }}</dd>

                    <dt class="cf-muted">Branch</dt>
                    <dd style="margin:0">{{ $member->organizationalUnit?->name ?? '—' }}</dd>
                </dl>
            </div>

            @if(filled($member->address) || filled($member->notes) || $member->emergency_contact_name)
                <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb);display:grid;gap:.9rem">
                    @if(filled($member->address))
                        <div>
                            <p class="cf-tiny cf-muted" style="margin:0 0 .2rem">Home address</p>
                            <p style="margin:0;white-space:pre-line">{{ $member->address }}</p>
                        </div>
                    @endif

                    @if($member->emergency_contact_name)
                        <div>
                            <p class="cf-tiny cf-muted" style="margin:0 0 .2rem">Emergency contact</p>
                            <p style="margin:0">
                                {{ $member->emergency_contact_name }}
                                @if($member->emergency_contact_phone) · {{ $member->emergency_contact_phone }} @endif
                            </p>
                        </div>
                    @endif

                    @if(filled($member->notes))
                        <div>
                            <p class="cf-tiny cf-muted" style="margin:0 0 .2rem">Notes</p>
                            <p style="margin:0;white-space:pre-line">{{ $member->notes }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Belonging: families, departments, groups --}}
        <div style="display:grid;gap:1.2rem">
            <div class="cf-card">
                <div class="cf-card__head"><h2>Family</h2></div>
                <div class="cf-card__body">
                    @if($member->families->isEmpty())
                        <p class="cf-small cf-muted" style="margin:0">Not linked to a family yet.</p>
                    @else
                        <ul style="margin:0;padding-left:1.1rem;display:grid;gap:.35rem">
                            @foreach($member->families as $family)
                                <li>
                                    <span>{{ $family->name }}</span>
                                    @if($family->pivot?->relationship)
                                        <span class="cf-tiny cf-muted">· {{ $family->pivot->relationship }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="cf-card">
                <div class="cf-card__head"><h2>Departments</h2></div>
                <div class="cf-card__body">
                    @if($member->departments->isEmpty())
                        <p class="cf-small cf-muted" style="margin:0">Not serving in a department yet.</p>
                    @else
                        <div style="display:flex;flex-wrap:wrap;gap:.4rem">
                            @foreach($member->departments as $department)
                                <span class="cf-badge cf-badge--brand">
                                    {{ $department->name }}@if($department->pivot?->role) · {{ $department->pivot->role }}@endif
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="cf-card">
                <div class="cf-card__head"><h2>Groups</h2></div>
                <div class="cf-card__body">
                    @if($member->groups->isEmpty())
                        <p class="cf-small cf-muted" style="margin:0">Not in a group yet.</p>
                    @else
                        <div style="display:flex;flex-wrap:wrap;gap:.4rem">
                            @foreach($member->groups as $group)
                                <span class="cf-badge">{{ $group->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layout>
