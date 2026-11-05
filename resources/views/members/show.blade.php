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

        // The edit form below uses the SAME fields and the same
        // UpdateMemberRequest the PUT route already validated — nothing about
        // how a member is stored changes. Shown only to someone the policy
        // allows to update this member (members.edit), and scoped to the
        // branches this user can see, so the dropdown can never offer a branch
        // the request would reject.
        $canEdit = auth()->user()->can('update', $member);
        $branches = $canEdit
            ? \App\Models\OrganizationalUnit::query()->orderBy('name')->get()
            : collect();
        $statuses = [
            'active' => 'Active', 'inactive' => 'Inactive', 'visitor' => 'Visitor',
            'transferred' => 'Transferred', 'deceased' => 'Deceased', 'draft' => 'Draft',
        ];
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
            @if($canEdit)
                <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="member-edit"
                    aria-expanded="false" aria-controls="member-edit-panel">
                    <x-ui.icon name="users" class="h-4 w-4" /> Edit member
                </button>
            @endif
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

    @if($canEdit)
        {{-- The edit form. Hidden until "Edit member" is pressed, and opened
             automatically if the last submission failed validation, so the
             errors are never hidden behind a collapsed panel. --}}
        <div id="member-edit-panel" data-panel="member-edit"
            @if(!$errors->any()) hidden @endif style="margin-bottom:1.2rem">
            <x-form.card title="Edit {{ $member->full_name }}"
                description="Correct or complete this member's details. Saving updates this record only — nothing is removed."
                :action="route('members.update', ['member' => $member])" method="PUT"
                submit="Save changes" submit-icon="check" layout="inline">

                <x-form.input name="full_name" label="Full name" required
                    :value="old('full_name', $member->full_name)" autocomplete="name" />

                <x-form.input name="email" label="Email address" type="email"
                    :value="old('email', $member->email)" autocomplete="email"
                    placeholder="name@example.com" />

                <x-form.input name="phone" label="Phone number" type="tel"
                    :value="old('phone', $member->phone)" autocomplete="tel"
                    placeholder="+234 800 000 0000" />

                <x-form.select name="membership_status" label="Membership status"
                    :value="old('membership_status', $member->membership_status)"
                    :options="$statuses" />

                @if($branches->isNotEmpty())
                    <x-form.select name="organizational_unit_id" label="Branch"
                        :value="old('organizational_unit_id', $member->organizational_unit_id)"
                        placeholder="Not assigned"
                        :options="$branches->map(fn ($b) => ['value' => $b->id, 'label' => $b->name])->all()" />
                @endif

                <x-form.select name="gender" label="Gender"
                    :value="old('gender', $member->gender)" placeholder="Not stated"
                    :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" />

                <x-form.input name="date_of_birth" label="Date of birth" type="date"
                    :value="old('date_of_birth', $member->date_of_birth?->format('Y-m-d'))" />

                <x-form.input name="date_joined" label="Date joined" type="date"
                    :value="old('date_joined', $member->date_joined?->format('Y-m-d'))" />

                <x-form.input name="baptism_date" label="Baptism date" type="date"
                    :value="old('baptism_date', $member->baptism_date?->format('Y-m-d'))" />

                <x-form.input name="emergency_contact_name" label="Emergency contact"
                    :value="old('emergency_contact_name', $member->emergency_contact_name)"
                    placeholder="Full name" />

                <x-form.input name="emergency_contact_phone" label="Emergency contact phone" type="tel"
                    :value="old('emergency_contact_phone', $member->emergency_contact_phone)" />

                {{-- is_worker is a boolean column. The rule accepts a boolean,
                     so the hidden "0" is what an unticked box sends — without
                     it an unchecked box would simply be absent and the old
                     value could never be turned off. --}}
                <div class="cf-field cf-field--wide">
                    <input type="hidden" name="is_worker" value="0">
                    <label class="cf-label" style="display:flex;align-items:center;gap:.5rem;font-weight:500">
                        <input type="checkbox" name="is_worker" value="1"
                            style="width:1rem;height:1rem"
                            @checked((bool) old('is_worker', $member->is_worker))>
                        Serves as a worker
                    </label>
                    <p class="cf-hint">Shown as a "Worker" badge next to the name.</p>
                </div>

                <x-form.textarea name="address" label="Home address" rows="2"
                    :value="old('address', $member->address)" />

                <x-form.textarea name="notes" label="Notes" rows="2"
                    :value="old('notes', $member->notes)"
                    hint="Visible to staff who have member access." />
            </x-form.card>
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
                        <p class="cf-small cf-muted" style="margin:0">Not linked to a family yet.
                            <a href="{{ route('families.index') }}#members">All families members</a></p>
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

    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function () {
                // Opens/collapses the edit form. The script is pushed
                // unconditionally and guarded here at runtime, which is the
                // pattern used on the members list page: a conditional push is
                // fragile in Blade, and a user without members.edit has neither
                // the button nor the panel, so this is a no-op for them.
                var btn = document.querySelector('[data-toggle="member-edit"]');
                var panel = document.querySelector('[data-panel="member-edit"]');
                if (!btn || !panel) return;

                btn.addEventListener('click', function () {
                    var isOpen = !panel.hasAttribute('hidden');
                    if (isOpen) {
                        panel.setAttribute('hidden', '');
                    } else {
                        panel.removeAttribute('hidden');
                        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    btn.setAttribute('aria-expanded', String(!isOpen));
                });

                // A failed save must not leave its messages behind a collapsed
                // panel: that reads as "Saving did nothing".
                if (panel.querySelector('[aria-invalid="true"], .cf-error, .cf-alert--error')) {
                    panel.removeAttribute('hidden');
                    btn.setAttribute('aria-expanded', 'true');
                    panel.scrollIntoView({ block: 'start' });
                }
            })();
        </script>
    @endpush
</x-layout>
