<x-layout title="Settings · ChurchFlow">
    @php
        // $tab comes from the controller. It is NOT computed here: this body runs
        // after <x-layout> has already captured and rendered its slot, so a value
        // assigned at this point would be too late for the tab strip above and the
        // @if below would silently fall through to the Profile branch.
        $tab = (string) ($tab ?? request('tab', 'profile'));

        $initials = fn($name) => \Illuminate\Support\Str::of($name)
            ->explode(' ')
            ->filter()
            ->map(fn($w) => mb_substr($w, 0, 1))
            ->take(2)
            ->implode('');
        $me = auth()->id();
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Settings</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Your church's profile, the people who can sign in, and exactly what each role may do.
            </p>
        </div>
    </div>

    {{-- No status block here: the layout already renders session('status') above
         the page content. Having one here as well printed every confirmation
         twice — once over the page title and once below it. --}}

    {{-- Tabs are plain links with a ?tab= query, so each section is a URL a
         person can bookmark or send to a colleague — and it survives a
         validation redirect back to the page. --}}
    <nav class="cf-tabs" aria-label="Settings sections">
        <a href="{{ route('settings.index', ['tab' => 'profile']) }}"
            class="cf-tab {{ $tab === 'profile' ? 'is-active' : '' }}">
            <x-ui.icon name="building" class="h-4 w-4" /> Profile
        </a>
        <a href="{{ route('settings.index', ['tab' => 'team']) }}"
            class="cf-tab {{ $tab === 'team' ? 'is-active' : '' }}">
            <x-ui.icon name="users" class="h-4 w-4" /> Team &amp; credentials
        </a>
        <a href="{{ route('settings.index', ['tab' => 'roles']) }}"
            class="cf-tab {{ $tab === 'roles' ? 'is-active' : '' }}">
            <x-ui.icon name="shield" class="h-4 w-4" /> Roles &amp; permissions
        </a>
    </nav>

    @if ($tab === 'team')
        {{-- ============================ TEAM ============================ --}}
        {{-- Existing registrars keep this flag only if it was set at signup. The
             church's FIRST user is the registrar (ActivateChurchFromCheckout),
             so the owner sees the create form and a branch pastor does not. --}}
        @php
            $canCreateAccounts = auth()->user()->isRegistrar();
            // Parish and role are the OWNER's decisions: the controller refuses
            // them for anyone else, so the screen must not offer an editor a
            // pastor cannot use. Everyone else sees the value, not a control.
            $canAssign = $canCreateAccounts;
            $permissionGroups = $permissionGroups ?? collect();
        @endphp

        {{-- The "Set permissions" link in a row's Manage menu targets
             #permissions-<id>; clicking it should OPEN that pastor's <details>
             rather than only scroll to a collapsed summary.

             This is a real <script> element carrying the per-response CSP nonce,
             matching every other inline block in the app. It previously shipped
             as a bare <script> with no nonce, and the browser REFUSED to execute
             it ("Executing inline script violates the following Content Security
             Policy directive 'script-src ..."), so the link scrolled to a closed
             panel and appeared to do nothing. --}}
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            document.addEventListener('click', function (event) {
                const link = event.target.closest('a[href^="#permissions-"]');
                if (!link) {
                    return;
                }
                const target = document.querySelector(link.getAttribute('href'));
                if (target) {
                    target.open = true;
                }
            });
        </script>

        {{-- The create form sits on its own, full width.

             It used to share a two-column grid with the team table. Adding the
             Permissions column made that table wider than the half-width
             column it was given (measured: 1059px of table inside a 478px
             container), so the last two columns — Permissions and Actions — were
             pushed out of view. The grid is `minmax(24rem, 1fr)`, so that broke
             at exactly the widths where it still fitted before. The table needs
             the full width whatever else is on the page, and the form reads
             better at this size anyway. --}}
        <div>
            {{-- §14/§37: the add-team-member form used to sit on the PARISH
                 tab too, where the team table it creates rows for is not even
                 shown — and worse, a saved role/parish assignment redirected
                 back to the parish form instead of the team list. The form is
                 only meaningful beside the table, so it stays on this tab. --}}
            @if ($canCreateAccounts && $tab === 'team')
            <x-form.card title="Add a team member"
                description="Create a sign-in for someone at your church. The password is set here — share it with them directly; changing it is a click from the list."
                :action="route('settings.team.store')" submit="Create account" submit-icon="users">
                <x-form.input name="name" label="Full name" required placeholder="e.g. Pastor John Adeyemi"
                    autocomplete="off" />
                <x-form.input name="email" label="Email address" type="email" required
                    placeholder="name@example.com" hint="This is the sign-in username." autocomplete="off" />
                <x-form.input name="password" label="Password" type="password" required hint="At least 8 characters."
                    autocomplete="new-password" />
                <x-form.input name="password_confirmation" label="Confirm password" type="password" required
                    autocomplete="new-password" />

                <x-form.select name="role_id" label="Role" required placeholder="Choose a role" :options="$roles
                    ->map(
                        fn($r) => [
                            'value' => $r->id,
                            'label' => $r->name . ' · ' . $r->permissions->count() . ' permissions',
                        ],
                    )
                    ->all()"
                    hint="What this account may do. You can change it later." />

                {{-- §14/§37: the parish this user belongs to. A user attached
                     to a parish sees only that parish's information; leave blank
                     for a church-wide account. --}}
                <x-form.select name="organizational_unit_id" label="Parish (branch)" placeholder="Church-wide (all parishes)"
                    :options="$parishes->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])->all()"
                    hint="Restrict this user to one parish's information. Leave blank for church-wide access." />

                <x-form.checkbox name="is_registrar" label="Registrar (super-admin)"
                    hint="The registrar receives and approves all subventions from every parish, and may delete users." />
            </x-form.card>
            @else
                <div class="cf-card">
                    <div class="cf-card__body">
                        <p class="cf-small" style="margin:0 0 .3rem;font-weight:700">Accounts are created by the church owner</p>
                        <p class="cf-small cf-muted" style="margin:0">
                            Only the account that registered this church can add a sign-in. Ask them to create one for you.
                        </p>
                    </div>
                </div>
            @endif

            <div class="cf-card cf-card--flush">
                <div class="cf-card__head">
                    <h2>People with access</h2>
                    <span class="cf-tiny cf-muted">{{ $team->count() }}
                        {{ \Illuminate\Support\Str::plural('account', $team->count()) }}</span>
                </div>

                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Sign-in email</th>
                                <th>Parish</th>
                                <th>Role</th>
                                <th>Permissions</th>
                                <th style="text-align:right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($team as $member)
                                <tr class="{{ $member->is_active ? '' : 'is-muted' }}">
                                    <td>
                                        <div style="display:flex;align-items:center;gap:.5rem">
                                            <span class="cfd-user__avatar"
                                                style="width:28px;height:28px;font-size:.72rem"
                                                aria-hidden="true">{{ $initials($member->name) }}</span>
                                            <div>
                                                <strong>{{ $member->name }}</strong>
                                                @if ($member->id === $me)
                                                    <span class="cf-badge cf-badge--brand"
                                                        style="margin-left:.3rem">You</span>
                                                @endif
                                                @unless ($member->is_active)
                                                    <span class="cf-badge cf-badge--danger"
                                                        style="margin-left:.3rem">Deactivated</span>
                                                @endunless
                                                {{-- isRegistrar(), not the raw
                                                     column: the check the app
                                                     actually enforces must be the
                                                     one the badge reflects, or a
                                                     legacy owner shows no badge
                                                     (and no delete button below)
                                                     while holding the rights. --}}
                                                @if ($member->isRegistrar())
                                                    <span class="cf-badge cf-badge--brand"
                                                        style="margin-left:.3rem">Registrar</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="cf-small cf-mono">{{ $member->email }}</td>
                                    <td>
                                        {{-- Parish: an inline control for the owner, a
                                             plain value for everyone else. --}}
                                        @if ($canAssign && $member->id !== $me && ! $member->isRegistrar())
                                            <form method="POST" action="{{ route('settings.team.parish', $member) }}"
                                                style="display:flex;gap:.35rem;align-items:center">
                                                @csrf
                                                <select name="organizational_unit_id" class="cf-select cf-select--sm" style="min-width:0;max-width:11rem">
                                                    <option value="" @selected(! $member->organizational_unit_id)>Church-wide</option>
                                                    @foreach ($parishes as $parish)
                                                        <option value="{{ $parish->id }}" @selected($member->organizational_unit_id === $parish->id)>{{ $parish->name }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Save</button>
                                            </form>
                                        @else
                                            <span class="cf-small">{{ $member->organizationalUnit?->name ?? 'Church-wide' }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{-- Role. A pastor who has been given
                                             individual permissions holds a private
                                             per-user role, and that role is the ONLY
                                             one they hold — so a shared-role dropdown
                                             here would be a control whose "Save"
                                             silently throws away the tick boxes. Show
                                             the truth instead: the role they actually
                                             have, with no editor.

                                             Everyone else keeps the normal dropdown. --}}
                                        @php
                                            // "Has individually assigned permissions" comes
                                            // from the controller (one eager-loaded query for the
                                            // whole page), NOT from a role name read off the
                                            // model here: deciding it from the model's first role
                                            // makes the screen depend on load order, and a role
                                            // dropdown for a pastor with individual permissions is
                                            // a control whose Save would silently discard them.
                                            // The controller refuses that save (409), so the
                                            // screen must not offer it in the first place.
                                            $hasIndividualPerms = in_array($member->id, $privateRoleUsers, true);
                                            $roleName = $hasIndividualPerms
                                                ? null
                                                : $member->roles->first()?->name;
                                        @endphp
                                        @if ($canAssign && $member->id !== $me && ! $member->isRegistrar() && ! $hasIndividualPerms)
                                            <form method="POST" action="{{ route('settings.team.role', $member) }}"
                                                style="display:flex;gap:.35rem;align-items:center">
                                                @csrf
                                                <select name="role_id" class="cf-select cf-select--sm"
                                                    style="min-width:0;max-width:11rem">
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role->id }}" @selected($member->roles->pluck('id')->contains($role->id))>
                                                            {{ $role->name }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit"
                                                    class="cf-btn cf-btn--secondary cf-btn--sm">Save</button>
                                            </form>
                                        @else
                                            <span class="cf-small">
                                                @if ($member->isRegistrar())
                                                    Church Owner
                                                @elseif ($hasIndividualPerms)
                                                    {{-- The stored name is a unique key
                                                         ("Pastor · <name> · #<id>"), not
                                                         something a person should read. --}}
                                                    Individual permissions
                                                @else
                                                    {{ $roleName ?? 'No role' }}
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        {{-- What this account can actually do, read from
                                             the same permissions the policies check.

                                             MUST stay in sync with the Role cell: saving
                                             the tick boxes replaces a pastor's role with a
                                             private per-user role, so a shared role named
                                             in the Role column no longer describes what
                                             they can do. The "Set by tick boxes" line says
                                             so, rather than leaving the owner to wonder
                                             why a "Church Owner" role only grants two
                                             things. --}}
                                        @php $memberPerms = $permissionsByUser[$member->id] ?? []; @endphp
                                        <span class="cf-small cf-muted">
                                            @if ($member->isRegistrar())
                                                Full access
                                            @elseif ($memberPerms === [])
                                                No permissions
                                            @else
                                                {{ count($memberPerms) }} {{ \Illuminate\Support\Str::plural('permission', count($memberPerms)) }}
                                            @endif
                                        </span>
                                        @unless ($member->isRegistrar())
                                            @if (in_array($member->id, $privateRoleUsers, true))
                                                <div class="cf-tiny cf-muted">Set individually by the owner</div>
                                            @endif
                                        @endunless
                                    </td>
                                    <td style="text-align:right;white-space:nowrap">
                                        <details class="cf-menu">
                                            <summary class="cf-btn cf-btn--secondary cf-btn--sm"
                                                style="list-style:none">Manage</summary>
                                            <div class="cf-menu__panel">
                                                <form method="POST"
                                                    action="{{ route('settings.team.password', $member) }}"
                                                    class="cf-form">
                                                    @csrf
                                                    <p class="cf-tiny cf-muted" style="margin:0 0 .4rem">Reset password
                                                        for {{ $member->name }}</p>
                                                    <input type="password" name="password" class="cf-input"
                                                        placeholder="New password" required minlength="8"
                                                        autocomplete="new-password">
                                                    <input type="password" name="password_confirmation" class="cf-input"
                                                        placeholder="Confirm password" required
                                                        autocomplete="new-password" style="margin-top:.4rem">
                                                    <button type="submit"
                                                        class="cf-btn cf-btn--primary cf-btn--sm cf-btn--block"
                                                        style="margin-top:.5rem">Reset password</button>
                                                </form>

                                                @if ($member->id !== $me)
                                                    <form method="POST"
                                                        action="{{ route('settings.team.toggle', $member) }}"
                                                        style="margin-top:.6rem">
                                                        @csrf
                                                        <button type="submit"
                                                            class="cf-btn cf-btn--secondary cf-btn--sm cf-btn--block">
                                                            {{ $member->is_active ? 'Deactivate account' : 'Reactivate account' }}
                                                        </button>
                                                    </form>
                                                @endif

                                                {{-- §14: delete a user. Only the registrar may do
                                                     this (enforced in the controller), and never on
                                                     their own account. --}}
                                                @if ($member->id !== $me && ! $member->isRegistrar() && auth()->user()->isRegistrar())
                                                    <form method="POST"
                                                        action="{{ route('settings.team.destroy', $member) }}"
                                                        style="margin-top:.6rem"
                                                        data-confirm="Delete {{ $member->name }}'s account? They will no longer be able to sign in. Records they created stay attributed to them."
                                                        data-confirm-title="Delete user?" data-confirm-ok="Delete user">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                            class="cf-btn cf-btn--danger cf-btn--sm cf-btn--block">Delete account</button>
                                                    </form>
                                                @endif

                                                {{-- §14/§37: the tick-box permission editor,
                                                     reachable from the row it belongs to.
                                                     It is the SAME form as the panel below
                                                     (same route, same field names) — this
                                                     is a second door, not a second
                                                     implementation. Owner-only, and never
                                                     for the owner's own row. --}}
                                                @if ($canCreateAccounts && $member->id !== $me && ! $member->isRegistrar())
                                                    <a href="#permissions-{{ $member->id }}"
                                                        class="cf-btn cf-btn--secondary cf-btn--sm cf-btn--block"
                                                        style="margin-top:.6rem">Set permissions</a>
                                                @endif
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="cf-card__body" style="border-top:1px solid var(--line, #e5e7eb)">
                    <p class="cf-tiny cf-muted" style="margin:0">
                        These email addresses are the credentials used to sign in. A deactivated account keeps its
                        history (records it created stay attributed to it) but can no longer log in.
                    </p>
                </div>
            </div>
        </div>

        {{-- §14/§37: what each parish pastor is allowed to do. Owner-only, and
             shown only on the Team tab — it lists the same people as the table
             above it. A checkbox set that renders under the church-profile form
             reads as "these are the profile's permissions", which is how the
             earlier screen ended up confusing. --}}
        @if ($canCreateAccounts && $tab === 'team')
            <div style="margin-top:1.2rem">
                <div class="cf-card" id="parish-pastor-permissions">
                    <div class="cf-card__head">
                        <h2>Parish pastor permissions</h2>
                        <span class="cf-tiny cf-muted">Tick exactly what each pastor may do</span>
                    </div>
                    <div class="cf-card__body">
                        <p class="cf-small cf-muted" style="margin:0 0 1rem">
                            Each pastor gets their own set. Ticking a box grants that capability everywhere it
                            applies, always limited to their own parish. Unticking removes it. The church owner
                            always keeps full access. Parish and role are always the owner's decision — a
                            pastor cannot change either for themselves or for anyone else.
                        </p>

                        @php
                            $pastors = $team->reject(fn ($u) => $u->isRegistrar())->values();
                            $categories = [
                                'members' => 'People',
                                'activities' => 'Activities',
                                'communication' => 'Communication',
                                'finance' => 'Finance',
                                'subvention' => 'Subvention',
                                'pastoral' => 'Pastoral care',
                                'settings' => 'Settings',
                                'billing' => 'Billing',
                            ];
                        @endphp

                        @if ($pastors->isEmpty())
                            <div class="cf-empty" style="padding:1.4rem">
                                <p class="cf-empty__title">No parish pastors yet</p>
                                <p class="cf-small cf-muted">
                                    Create a sign-in above and attach it to a parish, then its permissions can be
                                    set here.
                                </p>
                            </div>
                        @else
                            @foreach ($pastors as $pastor)
                                @php $ticked = $permissionsByUser[$pastor->id] ?? []; @endphp
                                <details class="cf-menu" id="permissions-{{ $pastor->id }}" style="display:block;margin-bottom:.5rem">
                                    <summary class="cf-btn cf-btn--secondary cf-btn--sm" style="list-style:none;display:inline-flex;gap:.5rem;align-items:center">
                                        <strong>{{ $pastor->name }}</strong>
                                        <span class="cf-tiny cf-muted">
                                            {{ $pastor->organizationalUnit?->name ?? 'Church-wide' }} ·
                                            {{ count($ticked) }} {{ \Illuminate\Support\Str::plural('permission', count($ticked)) }}
                                        </span>
                                    </summary>

                                    <form method="POST" action="{{ route('settings.team.permissions', $pastor) }}"
                                        class="cf-form" style="margin-top:.8rem">
                                        @csrf

                                        @foreach ($permissionGroups as $group => $permissions)
                                            <div style="margin-bottom:.9rem">
                                                <p class="cf-tiny cf-muted" style="margin:0 0 .35rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em">
                                                    {{ $categories[$group] ?? ucfirst($group) }}
                                                </p>
                                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(15rem,1fr));gap:.35rem .9rem">
                                                    @foreach ($permissions as $permission)
                                                        <label class="cf-small" style="display:flex;align-items:flex-start;gap:.5rem">
                                                            <input type="checkbox" name="permissions[]"
                                                                value="{{ $permission->name }}"
                                                                @checked(in_array($permission->name, $ticked, true))
                                                                style="margin-top:.2rem">
                                                            <span>
                                                                {{ $permission->label ?? $permission->name }}
                                                                <span class="cf-tiny cf-muted cf-mono" style="display:block">{{ $permission->name }}</span>
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach

                                        <div class="cf-form__actions">
                                            <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm">
                                                Save permissions for {{ $pastor->name }}
                                            </button>
                                        </div>
                                    </form>
                                </details>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @elseif($tab === 'roles')
        {{-- ============================ ROLES ============================ --}}
        <div class="cf-card cf-card--flush">
            <div class="cf-card__head">
                <h2>Roles and what they can do</h2>
                <span class="cf-tiny cf-muted">{{ $permissionGroups->flatten()->count() }} permissions across
                    {{ $permissionGroups->count() }} areas</span>
            </div>

            @if ($roles->isEmpty())
                <div class="cf-empty">
                    <p class="cf-empty__title">No roles yet</p>
                    <p class="cf-small cf-muted">A new church is created with a Church Owner role that can do
                        everything.</p>
                </div>
            @else
                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Permission</th>
                                @foreach ($roles as $role)
                                    <th style="text-align:center">
                                        {{ $role->name }}
                                        <div class="cf-tiny cf-muted">{{ $role->users_count }}
                                            {{ \Illuminate\Support\Str::plural('user', $role->users_count) }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($permissionGroups as $group => $permissions)
                                <tr class="cf-table__group">
                                    <th colspan="{{ $roles->count() + 1 }}">{{ ucfirst($group) }}</th>
                                </tr>
                                @foreach ($permissions as $permission)
                                    <tr>
                                        <td>
                                            {{ $permission->label ?? $permission->name }}
                                            <div class="cf-tiny cf-muted cf-mono">{{ $permission->name }}</div>
                                        </td>
                                        @foreach ($roles as $role)
                                            <td style="text-align:center">
                                                @if ($role->permissions->contains('id', $permission->id))
                                                    {{-- App styling, not the marketing one.
                                                         This borrowed `.mk-matrix__yes`
                                                         from the public pricing table,
                                                         whose surrounding rules paint a
                                                         navy marketing header inside an
                                                         app screen that uses .cf-table.
                                                         A pill badge is what every other
                                                         app table uses for a positive
                                                         state. --}}
                                                    <span class="cf-badge cf-badge--ok" title="Granted">
                                                        <x-ui.icon name="check" class="h-3.5 w-3.5" />
                                                        <span class="cf-visually-hidden">Granted</span>
                                                    </span>
                                                @else
                                                    <span class="cf-muted" aria-hidden="true">—</span>
                                                    <span class="cf-visually-hidden">Not granted</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @else
        {{-- ========================== PROFILE =========================== --}}
        <div class="cf-grid cf-grid--2">
            <x-form.card title="Church profile"
                description="How your church is identified across the platform. The currency here is the default for money you record."
                :action="route('settings.profile.update')" submit="Save profile" submit-icon="building">
                <x-form.input name="name" label="Church name" required :value="$church?->name" />
                <x-form.input name="denomination" label="Denomination" :value="$church?->denomination" placeholder="e.g. RCCG"
                    hint="Optional. Shown on reports and invoices." />

                <x-form.select name="timezone" label="Timezone" required :value="$church?->timezone ?? 'Africa/Lagos'" :options="[
                    'Africa/Lagos' => 'Africa/Lagos (WAT)',
                    'Africa/Accra' => 'Africa/Accra (GMT)',
                    'Africa/Nairobi' => 'Africa/Nairobi (EAT)',
                    'Africa/Johannesburg' => 'Africa/Johannesburg (SAST)',
                    'UTC' => 'UTC',
                ]" />

                <x-form.select name="currency" label="Currency" required :value="$church?->currency ?? 'NGN'" :options="['NGN' => 'Naira (₦)', 'USD' => 'US Dollar ($)', 'GBP' => 'Pound (£)', 'EUR' => 'Euro (€)']" />
            </x-form.card>

            <x-form.card title="Terminology"
                description="What you call the parts of your church's structure. These labels appear throughout the app — leave a field blank to use the default."
                :action="route('settings.profile.update')" submit="Save terminology" submit-icon="sliders">
                {{-- The profile form above also posts these, so this card is the
                     same target with the same field names. To avoid two forms
                     fighting over the same name/denomination fields, this one
                     carries the current values as hidden fields. --}}
                <input type="hidden" name="name" value="{{ $church?->name }}">
                <input type="hidden" name="denomination" value="{{ $church?->denomination }}">
                <input type="hidden" name="timezone" value="{{ $church?->timezone ?? 'Africa/Lagos' }}">
                <input type="hidden" name="currency" value="{{ $church?->currency ?? 'NGN' }}">

                @foreach ($terminologyKeys as $key => $default)
                    <x-form.input :name="'terminology[' . $key . ']'" :label="$default . ' — your label'" :value="$terminology[$key] ?? ''" :placeholder="$default"
                        :hint="'Leave blank to keep calling it “' . $default . '”.'" />
                @endforeach
            </x-form.card>
        </div>
    @endif

    <style>
        /* A compact select/input for the inline team controls. Declared here
           rather than in app.css so this screen's additions stay self-contained
           and cannot shift the sizing of selects elsewhere in the app. */
        .cf-select--sm {
            padding-top: .3rem;
            padding-bottom: .3rem;
            font-size: .82rem;
        }

        .cf-table tr.is-muted td {
            opacity: .62;
        }

        .cf-tabs {
            display: flex;
            gap: .3rem;
            border-bottom: 1px solid var(--line, #e5e7eb);
            margin-bottom: 1.4rem;
            flex-wrap: wrap;
        }

        .cf-tab {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .6rem .9rem;
            font-size: .88rem;
            font-weight: 500;
            color: var(--muted, #64748b);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
        }

        .cf-tab:hover {
            color: var(--ink, #0f172a);
        }

        .cf-tab.is-active {
            color: var(--cf-brand, #1677ff);
            border-bottom-color: var(--cf-brand, #1677ff);
        }

        /* Group heading row inside the permission matrix. The markup has
           always carried `cf-table__group`, but no rule for it existed
           anywhere, so these rows rendered as unstyled table cells. Scoped
           here rather than added to app.css because only this screen uses it. */
        .cf-table__group th {
            background: var(--surface-sunken, #f8fafc);
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted, #64748b);
            text-align: left;
            padding: .5rem .8rem;
        }

        /* The granted tick: keep the icon and its pill on one line. */
        .cf-table .cf-badge {
            display: inline-flex;
            align-items: center;
            gap: .2rem;
        }

        .cf-menu {
            position: relative;
            display: inline-block;
        }

        .cf-menu>summary {
            cursor: pointer;
        }

        .cf-menu>summary::-webkit-details-marker {
            display: none;
        }

        .cf-menu__panel {
            position: absolute;
            right: 0;
            z-index: 5;
            margin-top: .35rem;
            background: var(--surface, #fff);
            border: 1px solid var(--line, #e5e7eb);
            border-radius: .6rem;
            padding: .8rem;
            width: 16rem;
            box-shadow: 0 18px 40px -18px rgba(15, 23, 42, .45);
            text-align: left;
        }
    </style>
</x-layout>
