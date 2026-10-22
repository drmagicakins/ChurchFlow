<x-layout :title="$group->name . ' · Groups · ChurchFlow'">
    @php
        $canManage = auth()->user()->hasPermission('groups.manage');
        $initials = fn($name) => \Illuminate\Support\Str::of($name)
            ->explode(' ')
            ->filter()
            ->map(fn($w) => mb_substr($w, 0, 1))
            ->take(2)
            ->implode('');
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('groups.index') }}">← All groups</a></p>
            <h1 style="margin:.35rem 0 0">{{ $group->name }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                <span class="cf-badge">{{ $group->organizationalUnit?->name ?? 'Whole church' }}</span>
                <span style="margin-left:.4rem">{{ $group->members->count() }}
                    {{ \Illuminate\Support\Str::plural('member', $group->members->count()) }}</span>
            </p>
        </div>
        @if ($canManage)
            <form method="POST" action="{{ route('groups.destroy', $group) }}"
                data-confirm="Remove the {{ $group->name }} group? Its members stay on record — they simply stop being grouped here."
                data-confirm-title="Remove group?" data-confirm-ok="Remove group">
                @csrf
                @method('DELETE')
                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Remove group</button>
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

    @if ($errors->any())
        <div class="cf-alert cf-alert--error" role="alert">
            <span class="cf-alert__icon"><x-ui.icon name="alert" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">There is a problem with your submission.</p>
                <ul class="cf-alert__list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($group->description)
        <div class="cf-card" style="margin-bottom:1.2rem">
            <div class="cf-card__body">
                <p class="cf-small cf-muted" style="margin:0">{{ $group->description }}</p>
            </div>
        </div>
    @endif

    <div class="cf-grid cf-grid--2">
        {{-- Members of this group --}}
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Group members</h2>
                <span class="cf-tiny cf-muted">{{ $group->members->count() }}
                    {{ \Illuminate\Support\Str::plural('member', $group->members->count()) }}</span>
            </div>

            @if ($group->members->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">No members in this group yet</p>
                    <p class="cf-small cf-muted">
                        @if ($canManage)
                            Add members from the panel beside this one.
                        @else
                            Members added by your team will appear here.
                        @endif
                    </p>
                </div>
            @else
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                @if ($canManage)
                                    <th style="text-align:right">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group->members as $member)
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:.55rem">
                                            <span class="cfd-user__avatar"
                                                style="width:30px;height:30px;font-size:.72rem"
                                                aria-hidden="true">{{ $initials($member->full_name) }}</span>
                                            <div>
                                                <a
                                                    href="{{ route('members.show', $member) }}">{{ $member->full_name }}</a>
                                                <div class="cf-tiny cf-muted cf-mono">{{ $member->membership_number }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    @if ($canManage)
                                        <td style="text-align:right">
                                            <form method="POST"
                                                action="{{ route('groups.members.detach', [$group, $member]) }}"
                                                data-confirm="Remove {{ $member->full_name }} from {{ $group->name }}? Their member record is kept."
                                                data-confirm-title="Remove from group?" data-confirm-ok="Remove">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="cf-btn cf-btn--secondary cf-btn--sm">Remove</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Add a member --}}
        @if ($canManage)
            <div class="cf-card">
                <div class="cf-card__head">
                    <h2>Add a member to this group</h2>
                </div>
                <div class="cf-card__body">
                    @if ($availableMembers->isEmpty())
                        <p class="cf-small cf-muted" style="margin:0">
                            Every member is already in this group.
                            <a href="{{ route('members.index') }}">Add a new member</a> first if someone is missing.
                        </p>
                    @else
                        <form method="POST" action="{{ route('groups.members.attach', $group) }}" class="cf-form">
                            @csrf
                            <div class="cf-field">
                                <label class="cf-label" for="group-member">Member <span class="cf-label__req"
                                        aria-hidden="true">*</span></label>
                                <select name="member_id" id="group-member" class="cf-select" required>
                                    <option value="" disabled selected>Choose a member…</option>
                                    @foreach ($availableMembers as $candidate)
                                        <option value="{{ $candidate->id }}" @selected(old('member_id') == $candidate->id)>
                                            {{ $candidate->full_name }} ({{ $candidate->membership_number }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('member_id')
                                    <p class="cf-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="cf-form__actions" style="display:flex;justify-content:flex-end">
                                <button type="submit" class="cf-btn cf-btn--primary">
                                    <x-ui.icon name="users" class="h-4 w-4" /> Add to group
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
                <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb)">
                    <p class="cf-tiny cf-muted" style="margin:0">
                        Group membership drives announcements and SMS targeting — only people listed here are counted as
                        members of this group.
                    </p>
                </div>
            </div>
        @endif
    </div>
</x-layout>
