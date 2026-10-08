<x-layout title="Groups · ChurchFlow">
    @php $canManage = auth()->user()->hasPermission('groups.manage'); @endphp

    <div class="cf-page-head">
        <div>
            <h1>Groups</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                {{ number_format($groups->count()) }} {{ \Illuminate\Support\Str::plural('group', $groups->count()) }} ·
                groups are looser than departments — fellowships, cells, ministry teams.
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="group-create" aria-expanded="false">
                <x-ui.icon name="users" class="h-4 w-4" /> Add Group
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
        <div data-panel="group-create" hidden style="margin-bottom:1.2rem">
            <x-form.card
                title="Create a group"
                :action="route('groups.store')"
                submit="Create group"
                layout="inline"
            >
                <x-form.input name="name" label="Group name" required placeholder="e.g. Young Adults Fellowship" />
                <x-form.textarea name="description" label="Description" rows="2" wide />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($groups->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No groups yet</p>
                <p class="cf-small cf-muted">
                    @if($canManage)
                        Create a group above, then attach members to it from each member's page.
                    @else
                        Groups created by your team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr><th>Group</th><th style="text-align:right">Members</th><th style="text-align:right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach($groups as $item)
                            <tr>
                                <td><a href="{{ route('groups.show', $item) }}">{{ $item->name }}</a></td>
                                <td class="cf-mono" style="text-align:right">{{ $item->members->count() }}</td>
                                <td style="text-align:right">
                                    <a href="{{ route('groups.show', $item) }}" class="cf-btn cf-btn--secondary cf-btn--sm">View</a>
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
                var btn = document.querySelector('[data-toggle="group-create"]');
                var panel = document.querySelector('[data-panel="group-create"]');
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
