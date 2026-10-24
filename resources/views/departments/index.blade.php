<x-layout title="Departments · ChurchFlow">
    @php
        $canManage = auth()->user()->hasPermission('departments.manage');
        // A parish-scoped user must never be offered another parish in the
        // picker; TenantScope already fills theirs in on save.
        $branches = auth()->user()->isParishScoped()
            ? \App\Models\OrganizationalUnit::query()->whereKey(auth()->user()->organizational_unit_id)->get()
            : \App\Models\OrganizationalUnit::query()->orderBy('name')->get();
    @endphp

    <div class="cf-page-head">
        <div>
            <h1>Departments</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                {{ number_format($departments->total()) }} {{ \Illuminate\Support\Str::plural('department', $departments->total()) }} ·
                a department can belong to a specific branch, or to the whole church.
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="dept-create" aria-expanded="false">
                <x-ui.icon name="users" class="h-4 w-4" /> Add Department
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
        <div data-panel="dept-create" hidden style="margin-bottom:1.2rem">
            <x-form.card
                title="Create a department"
                description="Departments group members for announcements, SMS targeting and attendance."
                :action="route('departments.store')"
                submit="Create department"
                layout="inline"
            >
                <x-form.input name="name" label="Department name" required placeholder="e.g. Ushering, Choir, Media" />

                @if($branches->isNotEmpty() && ! auth()->user()->isParishScoped())
                    <x-form.select name="organizational_unit_id" label="Branch" placeholder="Whole church"
                        :options="$branches->map(fn ($b) => ['value' => $b->id, 'label' => $b->name])->all()"
                        hint="Leave as whole church unless this department belongs to one branch." />
                @endif

                <x-form.textarea name="description" label="What this department does" rows="2" wide />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($departments->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No departments yet</p>
                <p class="cf-small cf-muted">
                    @if($canManage)
                        Create your first department above. It will immediately be available for targeting announcements and SMS.
                    @else
                        Departments created by your team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr><th>Department</th><th>Branch</th><th style="text-align:right">Members</th><th style="text-align:right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach($departments as $item)
                            <tr>
                                <td><a href="{{ route('departments.show', $item) }}">{{ $item->name }}</a></td>
                                <td class="cf-small cf-muted">{{ $item->organizationalUnit?->name ?? 'Whole church' }}</td>
                                <td class="cf-mono" style="text-align:right">{{ $item->members_count ?? $item->members->count() }}</td>
                                <td style="text-align:right">
                                    <a href="{{ route('departments.show', $item) }}" class="cf-btn cf-btn--secondary cf-btn--sm">View</a>
                                    @if($canManage)
                                        <form method="POST" action="{{ route('departments.destroy', $item) }}" style="display:inline"
                                            data-confirm="Remove the {{ $item->name }} department? Its members stay on record — they simply stop being grouped here."
                                            data-confirm-title="Remove department?" data-confirm-ok="Remove department">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Remove</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($departments->hasPages())
        <div style="padding:1rem 1.35rem">{{ $departments->links() }}</div>
    @endif

    @push('scripts')
        <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
            (function () {
                var btn = document.querySelector('[data-toggle="dept-create"]');
                var panel = document.querySelector('[data-panel="dept-create"]');
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
