<x-layout :title="$family->name . ' · Families · ChurchFlow'">
    @php
        $canManage = auth()->user()->hasPermission('families.manage');
        $initials = fn($name) => \Illuminate\Support\Str::of($name)
            ->explode(' ')
            ->filter()
            ->map(fn($w) => mb_substr($w, 0, 1))
            ->take(2)
            ->implode('');

        // §11: the relationship a member has to the household. Kept as a map so
        // the label and the stored value stay in step in one place.
        $relationships = [
            'head' => 'Head of household',
            'spouse' => 'Spouse',
            'child' => 'Child',
            'dependent' => 'Dependent',
            'other' => 'Other',
        ];
        $head = $family->members->firstWhere('pivot.relationship', 'head');
    @endphp

    <div class="cf-page-head">
        <div>
            <p class="cf-small cf-muted" style="margin:0"><a href="{{ route('families.index') }}">← All families</a></p>
            <h1 style="margin:.35rem 0 0">{{ $family->name }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.35rem">
                {{ $family->members->count() }}
                {{ \Illuminate\Support\Str::plural('member', $family->members->count()) }} in this household
                @if ($head)
                    · head: <strong>{{ $head->full_name }}</strong>
                @endif
            </p>
        </div>
        @if ($canManage)
            <form method="POST" action="{{ route('families.destroy', $family) }}"
                data-confirm="Remove the {{ $family->name }} family? The members themselves are not deleted — they simply stop being grouped as a household."
                data-confirm-title="Remove family?" data-confirm-ok="Remove family">
                @csrf
                @method('DELETE')
                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Remove family</button>
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

    <div class="cf-grid cf-grid--2">
        {{-- Household members --}}
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Household members</h2>
                <span class="cf-tiny cf-muted">{{ $family->members->count() }}
                    {{ \Illuminate\Support\Str::plural('member', $family->members->count()) }}</span>
            </div>

            @if ($family->members->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">No members linked yet</p>
                    <p class="cf-small cf-muted">
                        @if ($canManage)
                            Use the form beside this to add the first member to the household.
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
                                <th>Relationship</th>
                                @if ($canManage)
                                    <th style="text-align:right">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($family->members as $member)
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
                                    <td>
                                        <span
                                            class="cf-badge {{ $member->pivot->relationship === 'head' ? 'cf-badge--brand' : '' }}">
                                            {{ $relationships[$member->pivot->relationship] ?? ucfirst((string) $member->pivot->relationship) }}
                                        </span>
                                    </td>
                                    @if ($canManage)
                                        <td style="text-align:right">
                                            <form method="POST"
                                                action="{{ route('families.members.detach', [$family, $member]) }}"
                                                data-confirm="Remove {{ $member->full_name }} from this household? The member's record itself is kept."
                                                data-confirm-title="Remove from family?" data-confirm-ok="Remove">
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
                    <h2>Add a member to this household</h2>
                </div>
                <div class="cf-card__body">
                    @if ($availableMembers->isEmpty())
                        <p class="cf-small cf-muted" style="margin:0">
                            Every member is already part of this household.
                            <a href="{{ route('members.index') }}">Add a new member</a> first if someone is missing.
                        </p>
                    @else
                        <form method="POST" action="{{ route('families.members.attach', $family) }}" class="cf-form">
                            @csrf
                            <div class="cf-field">
                                <label class="cf-label" for="family-member">Member <span class="cf-label__req"
                                        aria-hidden="true">*</span></label>
                                <select name="member_id" id="family-member" class="cf-select" required>
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

                            <div class="cf-field">
                                <label class="cf-label" for="family-relationship">Relationship <span
                                        class="cf-label__req" aria-hidden="true">*</span></label>
                                <select name="relationship" id="family-relationship" class="cf-select" required>
                                    @foreach ($relationships as $value => $label)
                                        <option value="{{ $value }}" @selected(old('relationship') === $value)>
                                            {{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('relationship')
                                    <p class="cf-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="cf-form__actions" style="display:flex;justify-content:flex-end">
                                <button type="submit" class="cf-btn cf-btn--primary">
                                    <x-ui.icon name="family" class="h-4 w-4" /> Add to family
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
                <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb)">
                    <p class="cf-tiny cf-muted" style="margin:0">
                        A household is how the church sees a family for pastoral follow-up. Linking members here never
                        changes their own record.
                    </p>
                </div>
            </div>
        @endif
    </div>
</x-layout>
