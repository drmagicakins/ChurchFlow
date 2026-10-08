<x-layout title="Feature Flags · ChurchFlow">
    <div class="cf-page-head">
        <div>
            <h1>Feature flags</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Roll a feature out globally, or per church. A per-church override wins in both
                directions — it can put a church ahead of a global rollout, or hold one back after it.
            </p>
        </div>
        <a href="{{ route('platform-admin.dashboard') }}" class="cf-btn cf-btn--secondary cf-btn--sm">← Overview</a>
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    <div data-panel="flag-create" hidden style="margin-bottom:1.2rem">
        <x-form.card
            title="Create a feature flag"
            description="An unknown flag key fails closed when read — a typo in a template hides a feature rather than breaking the page — so creating the flag first is what makes a key resolvable."
            :action="route('platform-admin.feature-flags.store')"
            submit="Create flag"
            layout="inline"
        >
            <x-form.input name="key" label="Key" required placeholder="e.g. advanced_reporting"
                hint="Lowercase, underscores. This is the string code checks with isEnabled()." />
            <x-form.input name="name" label="Display name" placeholder="e.g. Advanced reporting" />
            <x-form.checkbox name="is_globally_enabled" label="Enable globally"
                hint="Leave off to create it dark, then enable per church." />
            <x-form.textarea name="description" label="What it controls" rows="2" wide />
        </x-form.card>
    </div>

    <div class="cf-card cf-card--flush">
        <div class="cf-card__head">
            <h2>Flags</h2>
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-toggle="flag-create" aria-expanded="false">
                New flag
            </button>
        </div>

        @if($flags->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No feature flags yet</p>
                <p class="cf-small cf-muted">Create one above to gate a feature behind a switch instead of a deploy.</p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Flag</th>
                            <th>Global</th>
                            <th>Per-church overrides</th>
                            <th style="text-align:right">Global state</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($flags as $flag)
                            <tr>
                                <td>
                                    <strong>{{ $flag->name ?: $flag->key }}</strong>
                                    <div class="cf-tiny cf-muted cf-mono">{{ $flag->key }}</div>
                                </td>
                                <td>
                                    <span class="cf-badge {{ $flag->is_globally_enabled ? 'cf-badge--ok' : '' }}">
                                        {{ $flag->is_globally_enabled ? 'On' : 'Off' }}
                                    </span>
                                </td>
                                <td class="cf-small">
                                    @forelse($flag->churchOverrides as $override)
                                        <div style="display:flex;align-items:center;gap:.35rem;margin-bottom:.2rem">
                                            <span>{{ $override->church?->name ?? 'Church #'.$override->church_id }}</span>
                                            <span class="cf-badge {{ $override->is_enabled ? 'cf-badge--ok' : 'cf-badge--danger' }}" style="font-size:.7rem">
                                                {{ $override->is_enabled ? 'On' : 'Off' }}
                                            </span>
                                        </div>
                                    @empty
                                        <span class="cf-muted">None — global value applies</span>
                                    @endforelse
                                </td>
                                <td style="text-align:right">
                                    <form method="POST" action="{{ route('platform-admin.feature-flags.toggle', $flag) }}">
                                        @csrf
                                        <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">
                                            Turn {{ $flag->is_globally_enabled ? 'off' : 'on' }}
                                        </button>
                                    </form>
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
                var btn = document.querySelector('[data-toggle="flag-create"]');
                var panel = document.querySelector('[data-panel="flag-create"]');
                if (!btn || !panel) return;
                btn.addEventListener('click',   function () {
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
