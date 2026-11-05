<x-layout title="Branches · ChurchFlow">
    <div class="cf-page-head">
        <div>
            <h1>{{ $pluralLabel }}</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                {{ number_format($units->total()) }} {{ \Illuminate\Support\Str::plural($label, $units->total()) }} ·
                {{ $pluralLabel }} are the top level of your church's structure — departments, members, events and
                announcements can all be scoped to one.
            </p>
        </div>
        @if ($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="branch-create"
                aria-expanded="false">
                <x-ui.icon name="branches" class="h-4 w-4" /> Add {{ $label }}
            </button>
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

    @if ($errors->any() && $canManage)
        <div class="cf-alert cf-alert--error" role="alert">
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

    @if ($canManage)
        <div data-panel="branch-create" hidden style="margin-bottom:1.2rem">
            <x-form.card title="Create a {{ strtolower($label) }}"
                description="Your plan sets how many {{ strtolower($pluralLabel) }} you can add; the limit is checked here before anything is saved."
                :action="route('branches.store')" submit="Create {{ $label }}" submit-icon="branches" layout="inline">
                <x-form.input name="name" label="{{ $label }} name" required
                    placeholder="e.g. Ikeja, Surulere, Main Campus" />
                <x-form.input name="code" label="Code" placeholder="Optional — e.g. AB001"
                    hint="A short external code, useful when migrating from another system." />

                @if ($unitTypes->isNotEmpty())
                    <x-form.select name="unit_type_id" label="Type" :value="old('unit_type_id')"
                        placeholder="Use the default ({{ $label }})" :options="$unitTypes->map(fn($t) => ['value' => $t->id, 'label' => $t->name])->all()" />
                @endif

                @if ($units->isNotEmpty())
                    <x-form.select name="parent_id" label="Parent" :value="old('parent_id')" placeholder="No parent (top level)"
                        :options="$units->map(fn($u) => ['value' => $u->id, 'label' => $u->name])->all()" />
                @endif
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if ($units->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No {{ strtolower($pluralLabel) }} yet</p>
                <p class="cf-small cf-muted">
                    @if ($canManage)
                        Create your first {{ strtolower($label) }} above. Once it exists you can scope members,
                        departments, events and announcements to it.
                    @else
                        {{ \Illuminate\Support\Str::plural($label) }} created by your team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>{{ $label }}</th>
                            <th>Type</th>
                            <th>Parent</th>
                            <th style="text-align:right">Members</th>
                            <th style="text-align:right">Departments</th>
                            @if ($canManage)
                                <th style="text-align:right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($units as $unit)
                            <tr>
                                <td>
                                    {{ $unit->name }}
                                    @if ($unit->code)
                                        <span class="cf-badge" style="margin-left:.4rem">{{ $unit->code }}</span>
                                    @endif
                                </td>
                                <td class="cf-small cf-muted">{{ $unit->unitType?->name ?? '—' }}</td>
                                <td class="cf-small cf-muted">{{ $unit->parent?->name ?? '—' }}</td>
                                <td class="cf-mono" style="text-align:right">
                                    {{ number_format($unit->members_count ?? 0) }}</td>
                                <td class="cf-mono" style="text-align:right">
                                    {{ number_format($unit->departments_count ?? 0) }}</td>
                                @if ($canManage)
                                    <td style="text-align:right">
                                        <form method="POST" action="{{ route('branches.destroy', ['branch' => $unit]) }}"
                                            onsubmit="return confirm('Remove {{ e($unit->name) }}? Records pointing at it will simply become unscoped.')">
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
            <div style="padding:1rem 1.35rem">{{ $units->links() }}</div>
        @endif
    </div>

    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function() {
                var btn = document.querySelector('[data-toggle="branch-create"]');
                var panel = document.querySelector('[data-panel="branch-create"]');
                if (!btn || !panel) return;
                btn.addEventListener('click', function() {
                    var isOpen = !panel.hasAttribute('hidden');
                    if (isOpen) {
                        panel.setAttribute('hidden', '');
                    } else {
                        panel.removeAttribute('hidden');
                        panel.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                    btn.setAttribute('aria-expanded', String(!isOpen));
                });
                if (panel.querySelector('[aria-invalid="true"]')) {
                    panel.removeAttribute('hidden');
                }
            })();
        </script>
    @endpush
</x-layout>
