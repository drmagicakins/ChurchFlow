<x-layout title="Families · ChurchFlow">
    @php $canManage = auth()->user()->hasPermission('families.manage'); @endphp

    <div class="cf-page-head">
        <div>
            <h1>Families</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                {{ number_format($families->count()) }} {{ \Illuminate\Support\Str::plural('family', $families->count()) }} ·
                linking members into households makes pastoral follow-up far easier.
            </p>
        </div>
        @if($canManage)
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="family-create" aria-expanded="false">
                <x-ui.icon name="family" class="h-4 w-4" /> Add Family
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
        <div data-panel="family-create" hidden style="margin-bottom:1.2rem">
            <x-form.card
                title="Create a family"
                description="Name it however your church actually refers to it — “The Adeyemi family” works as well as a surname."
                :action="route('families.store')"
                submit="Create family"
                layout="inline"
            >
                <x-form.input name="name" label="Family name" required placeholder="e.g. The Adeyemi family" />
                <x-form.textarea name="notes" label="Notes" rows="2" wide />
            </x-form.card>
        </div>
    @endif

    <div class="cf-card cf-card--flush">
        @if($families->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No families yet</p>
                <p class="cf-small cf-muted">
                    @if($canManage)
                        Create a family above, then attach members to it from each member's page.
                    @else
                        Families created by your team will appear here.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr><th>Family</th><th style="text-align:right">Members</th><th style="text-align:right">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach($families as $item)
                            <tr>
                                <td><a href="{{ route('families.show', $item) }}">{{ $item->name }}</a></td>
                                <td class="cf-mono" style="text-align:right">{{ $item->members->count() }}</td>
                                <td style="text-align:right">
                                    <a href="{{ route('families.show', $item) }}" class="cf-btn cf-btn--secondary cf-btn--sm">View</a>
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
                var btn = document.querySelector('[data-toggle="family-create"]');
                var panel = document.querySelector('[data-panel="family-create"]');
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
