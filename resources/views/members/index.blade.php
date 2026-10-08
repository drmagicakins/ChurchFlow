<x-layout title="Members · ChurchFlow">
    @php
        $canCreate = auth()->user()->hasPermission('members.create');
        $branches = \App\Models\OrganizationalUnit::query()->orderBy('name')->get();
        $departments = \App\Models\Department::query()->orderBy('name')->get();
        $hasFilters = request()->hasAny(['q', 'status', 'organizational_unit_id', 'department_id']);
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Members</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                {{ number_format($members->total()) }} {{ \Illuminate\Support\Str::plural('member', $members->total()) }}
                @if($hasFilters) matching your filters @endif
            </p>
        </div>
        @if($canCreate)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="member-create" aria-expanded="false">
                <x-ui.icon name="users" class="h-4 w-4" /> Add Member
            </button>
        @endif
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    {{-- Filters as a GET form, so a filtered list is a URL someone can bookmark
         or send to a colleague — which is how a member list is actually used. --}}
    <form method="GET" action="{{ route('members.index') }}" class="cf-form" style="margin-bottom:1.2rem">
        <div class="cf-form__body cf-form__body--inline" style="padding-bottom:1.25rem">
            <x-form.input name="q" label="Search" :value="request('q')"
                placeholder="Name, email, phone or membership number" autocomplete="off" />

            <x-form.select name="status" label="Membership status" :value="request('status')" placeholder="Any status"
                :options="['active' => 'Active', 'inactive' => 'Inactive', 'visitor' => 'Visitor', 'transferred' => 'Transferred', 'deceased' => 'Deceased', 'draft' => 'Draft']" />

            @if($branches->isNotEmpty())
                <x-form.select name="organizational_unit_id" label="Branch" :value="request('organizational_unit_id')"
                    placeholder="Any branch" :options="$branches->map(fn ($b) => ['value' => $b->id, 'label' => $b->name])->all()" />
            @endif

            @if($departments->isNotEmpty())
                <x-form.select name="department_id" label="Department" :value="request('department_id')"
                    placeholder="Any department" :options="$departments->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->all()" />
            @endif

            <div class="cf-field cf-field--wide" style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.2rem">
                <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm">
                    <x-ui.icon name="search" class="h-4 w-4" /> Apply filters
                </button>
                @if($hasFilters)
                    <a href="{{ route('members.index') }}" class="cf-btn cf-btn--secondary cf-btn--sm">Clear</a>
                @endif
                <a href="{{ route('members.export', request()->query()) }}" class="cf-btn cf-btn--secondary cf-btn--sm" style="margin-left:auto">
                    <x-ui.icon name="doc" class="h-4 w-4" /> Export CSV
                </a>
            </div>
        </div>
    </form>

    @if($canCreate)
        <div data-panel="member-create" hidden style="margin-bottom:1.2rem">
            <x-form.card
                title="Add a new member"
                description="Only the name is required — everything else can be filled in later, because an incomplete record is still better than a paper one."
                :action="route('members.store')"
                submit="Add member"
                submit-icon="users"
                layout="inline"
            >
                <x-form.input name="full_name" label="Full name" required placeholder="e.g. Adaeze Okonkwo" autocomplete="name" />
                <x-form.input name="email" label="Email address" type="email" placeholder="name@example.com" autocomplete="email" />

                <x-form.select name="membership_status" label="Membership status" :value="'active'"
                    :options="['active' => 'Active', 'inactive' => 'Inactive', 'visitor' => 'Visitor', 'transferred' => 'Transferred', 'deceased' => 'Deceased', 'draft' => 'Draft']" />

                @if($branches->isNotEmpty())
                    <x-form.select name="organizational_unit_id" label="Branch" placeholder="Not assigned"
                        :options="$branches->map(fn ($b) => ['value' => $b->id, 'label' => $b->name])->all()" />
                @endif

                <x-form.select name="gender" label="Gender" placeholder="Not stated"
                    :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" />

                <x-form.input name="phone" label="Phone number" type="tel" placeholder="+234 800 000 0000" autocomplete="tel" />
                <x-form.input name="date_of_birth" label="Date of birth" type="date" hint="Used for birthdays, never shared outside your church." />
                <x-form.input name="date_joined" label="Date joined" type="date" />

                <x-form.input name="emergency_contact_name" label="Emergency contact" placeholder="Full name" />
                <x-form.input name="emergency_contact_phone" label="Emergency contact phone" type="tel" />

                <x-form.textarea name="address" label="Home address" rows="2" wide />
                <x-form.textarea name="notes" label="Notes" rows="2" wide hint="Visible to staff who have member access." />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($members->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">{{ $hasFilters ? 'No members match those filters' : 'No members yet' }}</p>
                <p class="cf-small cf-muted">
                    @if($hasFilters)
                        <a href="{{ route('members.index') }}">Clear the filters</a> to see everyone.
                    @elseif($canCreate)
                        Use <strong>Add Member</strong> above to create your first record.
                    @else
                        A member record will appear here once someone with permission adds one.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Membership #</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $member)
                            <tr>
                                <td class="cf-mono cf-small" style="white-space:nowrap">{{ $member->membership_number }}</td>
                                <td>
                                    <a href="{{ route('members.show', $member) }}">{{ $member->full_name }}</a>
                                    @if($member->is_worker)
                                        <span class="cf-badge cf-badge--brand" style="margin-left:.35rem">Worker</span>
                                    @endif
                                </td>
                                <td class="cf-small cf-muted">{{ $member->email ?: ($member->phone ?: '—') }}</td>
                                <td class="cf-small cf-muted">{{ $member->organizationalUnit?->name ?? '—' }}</td>
                                <td>
                                    @php
                                        $tone = match ($member->membership_status) {
                                            'active' => 'cf-badge--ok',
                                            'visitor' => 'cf-badge--brand',
                                            'transferred', 'deceased' => 'cf-badge--danger',
                                            default => '',
                                        };
                                    @endphp
                                    <span class="cf-badge {{ $tone }}">{{ str_replace('_', ' ', ucfirst($member->membership_status ?? 'unknown')) }}</span>
                                </td>
                                <td style="text-align:right;white-space:nowrap">
                                    <a href="{{ route('members.show', $member) }}" class="cf-btn cf-btn--secondary cf-btn--sm">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="padding:1rem 1.35rem">{{ $members->links() }}</div>
        @endif
    </div>

    @if($canCreate)
        {{-- @push inside @if is a known Blade trap: the compiler emits the push
             marker at compile time, so a conditional around it can leave a
             dangling @endpush and produce a "syntax error, unexpected end of
             file" at render time. The script is therefore pushed
             unconditionally but guarded at RUNTIME instead — the early return
             is what makes it a no-op for a user without the panel. --}}
        @push('scripts')
            <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
                (function () {
                    var btn = document.querySelector('[data-toggle="member-create"]');
                    var panel = document.querySelector('[data-panel="member-create"]');
                    // No create panel for this user — nothing to wire up.
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

                    // A validation failure must not leave the user's input and
                    // the errors hidden behind a collapsed toggle.
                    if (panel.querySelector('[aria-invalid="true"]')) {
                        panel.removeAttribute('hidden');
                        btn.setAttribute('aria-expanded', 'true');
                    }
                })();
            </script>
        @endpush
    @endif
</x-layout>
