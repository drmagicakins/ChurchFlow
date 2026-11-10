<x-layout title="Announcements · ChurchFlow">
    @php $canManage = auth()->user()->hasPermission('announcements.manage'); @endphp

    <div class="cf-page-head">
        <div>
            <h1>Announcements</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Post once and reach the right people — email is included with your plan.
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="ann-create" aria-expanded="false">
                <x-ui.icon name="megaphone" class="h-4 w-4" /> New Announcement
            </button>
        @endif
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    @if($canManage)
        {{-- x-data carries the audience model the conditional selects below bind to.
             Initialised from old() so a failed submit keeps the audience the user chose
             (and the field that was flagged as missing stays visible). --}}
        {{-- The default audience is the first one this user may actually post
             to. It used to be hard-coded to 'church_wide', which a parish pastor
             is now refused — the form would have opened on a choice the
             controller rejects. --}}
        @php $defaultAudience = old('audience_type', array_key_first($audiences) ?? 'church_wide'); @endphp
        <div data-panel="ann-create" hidden style="margin-bottom:1.2rem"
            x-data="{ audience: '{{ $defaultAudience }}' }">
            <x-form.card
                title="Post an announcement"
                description="Choosing an audience decides who sees this and who is emailed — church-wide goes to everyone with an email address."
                :action="route('announcements.store')"
                submit="Post announcement"
                submit-icon="megaphone"
                layout="inline"
            >
                <x-form.input name="title" label="Title" required placeholder="e.g. Service time change this Sunday" />

                {{-- The options come from the controller ($audiences), which
                     offers only what this user may actually post to. A parish
                     pastor gets their own branch, not every branch; the church
                     owner gets all of them. The controller re-checks on submit,
                     so this list is convenience, not the guard. --}}
                <x-form.select name="audience_type" label="Audience" :value="$defaultAudience" :options="$audiences"
                    hint="Emails are sent to every member in this audience who has an address."
                    x-model="audience" data-audience-select />

                {{-- §19: the audience dropdown chooses WHICH extra field applies.
                     Only the matching select is ever required (the controller uses
                     required_if), and every field is present in the DOM but hidden,
                     so switching audience never loses a value the user already typed
                     and the browser's own required-attribute validation can't fire on
                     a field that is not the current audience. --}}
                <div x-show="audience === 'branch'" x-cloak>
                    <x-form.select name="organizational_unit_id" label="Branch"
                        :value="old('organizational_unit_id')" placeholder="Choose a branch..."
                        :required="old('audience_type') === 'branch'"
                        :options="$branches->map(fn ($b) => ['value' => $b->id, 'label' => $b->name])->all()" />
                </div>

                <div x-show="audience === 'department'" x-cloak>
                    <x-form.select name="department_id" label="Department"
                        :value="old('department_id')" placeholder="Choose a department..."
                        :required="old('audience_type') === 'department'"
                        :options="$departments->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->all()" />
                </div>

                <div x-show="audience === 'group'" x-cloak>
                    <x-form.select name="group_id" label="Group"
                        :value="old('group_id')" placeholder="Choose a group..."
                        :required="old('audience_type') === 'group'"
                        :options="$groups->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])->all()" />
                </div>

                <x-form.textarea name="body" label="Message" rows="5" required
                    placeholder="Write the announcement as you would say it out loud." wide />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($announcements->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No announcements yet</p>
                <p class="cf-small cf-muted">
                    @if($canManage)
                        Use <strong>New Announcement</strong> above to reach your congregation.
                    @else
                        Announcements posted by your team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Audience</th>
                            <th>Posted</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($announcements as $a)
                            <tr>
                                <td><a href="{{ route('announcements.show', $a) }}">{{ $a->title }}</a></td>
                                <td><span class="cf-badge">{{ str_replace('_', ' ', $a->audience_type) }}</span></td>
                                <td class="cf-small cf-muted" style="white-space:nowrap">{{ $a->created_at?->diffForHumans() }}</td>
                                <td style="text-align:right">
                                    <div style="display:inline-flex;gap:.4rem;align-items:center">
                                        <a href="{{ route('announcements.show', $a) }}" class="cf-btn cf-btn--secondary cf-btn--sm">View</a>
                                        @if($canManage)
                                            <form method="POST" action="{{ route('announcements.destroy', $a) }}" style="display:inline"
                                                data-confirm="Delete the announcement “{{ $a->title }}”? This cannot be undone."
                                                data-confirm-title="Delete announcement?" data-confirm-ok="Delete">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="cf-btn cf-btn--danger cf-btn--sm">Delete</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function () {
                var btn = document.querySelector('[data-toggle="ann-create"]');
                var panel = document.querySelector('[data-panel="ann-create"]');
                if (!btn || !panel) return;
                btn.addEventListener('click', function () {
                    var isOpen = !panel.hasAttribute('hidden');
                    if (isOpen) { panel.setAttribute('hidden', ''); }
                    else { panel.removeAttribute('hidden'); panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                    btn.setAttribute('aria-expanded', String(!isOpen));
                });
                if (panel.querySelector('[aria-invalid="true"]')) { panel.removeAttribute('hidden'); }
            })();
        </script>
    @endpush
</x-layout>
