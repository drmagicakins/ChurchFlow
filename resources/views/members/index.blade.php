<x-layout title="Members · ChurchFlow">
    @php
        $canCreate = auth()->user()->hasPermission('members.create');
        $branches = \App\Models\OrganizationalUnit::query()->orderBy('name')->get();
        $departments = \App\Models\Department::query()->visibleTo(auth()->user())->orderBy('name')->get();
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
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" data-toggle="member-import" aria-expanded="false">
                    <x-ui.icon name="doc" class="h-4 w-4" /> Import from Excel
                </button>
                <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="member-create" aria-expanded="false">
                    <x-ui.icon name="users" class="h-4 w-4" /> Add Member
                </button>
            </div>
        @endif
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    @if(session('import_errors') && count(session('import_errors')))
        <div class="cf-alert cf-alert--error" role="alert">
            <span class="cf-alert__icon"><x-ui.icon name="alert" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">Some rows could not be imported</p>
                <ul class="cf-alert__list">
                    @foreach(session('import_errors') as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
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
        {{-- Bulk import. The sample sheet is generated from the same column list
             the importer validates, so the downloaded file can never describe a
             format the upload does not accept. --}}
        <div data-panel="member-import" hidden style="margin-bottom:1.2rem">
            <x-form.card
                title="Import members from a spreadsheet"
                description="Download the sample sheet, fill in one member per row, then upload it here. Column headings must stay as they are — the file is matched by name, not position."
                :action="route('members.import')"
                submit="Upload members"
                submit-icon="doc"
                method="POST"
                enctype="multipart/form-data"
                layout="inline"
                data-cf-loading-text="Importing members…"
            >
                <div class="cf-field cf-field--wide">
                    <span class="cf-label">Step 1 — the sample sheet</span>
                    <p class="cf-hint" style="margin:.25rem 0 .5rem">
                        A blank CSV with the right headings and two example rows you can replace.
                    </p>
                    <a href="{{ route('members.sample') }}" class="cf-btn cf-btn--secondary cf-btn--sm">
                        <x-ui.icon name="doc" class="h-4 w-4" /> Download sample sheet (CSV)
                    </a>
                </div>

                <div class="cf-field cf-field--wide">
                    <label class="cf-label" for="member-import-file">Step 2 — upload the filled sheet</label>
                    <input type="file" name="file" id="member-import-file" class="cf-input"
                        accept=".csv,text/csv,text/plain" required>
                    @error('file')
                        <p class="cf-error" role="alert">{{ $message }}</p>
                    @else
                        <p class="cf-hint">CSV only, up to 10 MB. In Excel: File → Save As → CSV UTF-8.</p>
                    @enderror
                </div>
            </x-form.card>
        </div>
    @endif

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
                    // Both panels share one handler: member-create and the
                    // spreadsheet import. Each toggle owns the panel named after
                    // it, and opening one closes the other so the page never
                    // shows two large forms at once.
                    var ids = ['member-create', 'member-import'];
                    var hasErrors = false;

                    ids.forEach(function (id) {
                        var btn = document.querySelector('[data-toggle="' + id + '"]');
                        var panel = document.querySelector('[data-panel="' + id + '"]');
                        if (!btn || !panel) return;

                        btn.addEventListener('click', function () {
                            var isOpen = !panel.hasAttribute('hidden');

                            ids.forEach(function (other) {
                                if (other === id) return;
                                var op = document.querySelector('[data-panel="' + other + '"]');
                                var ob = document.querySelector('[data-toggle="' + other + '"]');
                                if (op) op.setAttribute('hidden', '');
                                if (ob) ob.setAttribute('aria-expanded', 'false');
                            });

                            if (isOpen) {
                                panel.setAttribute('hidden', '');
                            } else {
                                panel.removeAttribute('hidden');
                                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                            btn.setAttribute('aria-expanded', String(!isOpen));
                        });

                        // A failed submit must not leave its message hidden
                        // behind a collapsed toggle: that looked like "the
                        // upload did nothing".
                        var err = panel.querySelector('[aria-invalid="true"], .cf-error, .cf-alert--error');
                        if (!err) return;
                        hasErrors = true;
                        panel.removeAttribute('hidden');
                        btn.setAttribute('aria-expanded', 'true');
                    });

                    if (hasErrors) {
                        var target = document.querySelector('[data-panel]:not([hidden])');
                        if (target) target.scrollIntoView({ block: 'start' });
                    }
                })();
            </script>
        @endpush
    @endif
</x-layout>
