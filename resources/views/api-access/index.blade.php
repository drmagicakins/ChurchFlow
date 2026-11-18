<x-layout title="API access · ChurchFlow">
    <div class="cf-page-head">
        <div>
            <h1>API access</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Read-only keys for your church's data. Send one as
                <code class="cf-mono">Authorization: Bearer &lt;key&gt;</code> to the endpoints below.
            </p>
        </div>
        <a href="{{ route('settings.index', ['tab' => 'profile']) }}" class="cf-btn cf-btn--secondary cf-btn--sm">←
            Settings</a>
    </div>

    @if (session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">{{ session('status') }}</p>
            </div>
        </div>
    @endif

    @if (!$enabled)
        {{-- The plan does not include API access. State it plainly and name the
             plan that does, rather than hiding the page (an owner who followed
             a link should learn why, not get a 404). --}}
        <div class="cf-alert cf-alert--warn" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="lock" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">API access is not included in your current plan</p>
                <p class="cf-small" style="margin:.3rem 0 0">
                    @if ($requiredPlan)
                        It is included from the <strong>{{ $requiredPlan }}</strong> plan.
                    @endif
                    <a href="{{ route('billing.plans') }}">View plans</a> to upgrade.
                </p>
            </div>
        </div>
    @endif

    @if ($plaintext)
        {{-- Shown exactly once. The plaintext is never stored, so this is the
             only moment it can be copied — say so instead of letting an owner
             discover it the hard way on their next visit. --}}
        <div class="cf-card" style="border-left:3px solid var(--cf-brand, #1677FF)">
            <div class="cf-card__head">
                <h2>Your new API key</h2>
                <span class="cf-badge cf-badge--warn">Copy it now</span>
            </div>
            <div class="cf-card__body">
                <p class="cf-small cf-muted" style="margin:0 0 .6rem">
                    This is the only time the key is shown. Only its hash is stored, so it cannot be
                    recovered — if you lose it, revoke it and issue a new one.
                </p>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                    <input type="text" class="cf-input cf-mono" readonly value="{{ $plaintext }}"
                        style="flex:1;min-width:18rem" onclick="this.select()" aria-label="New API key">
                    <code class="cf-tiny cf-muted">Store it in your integration's secret settings, never in a
                        repository.</code>
                </div>
            </div>
        </div>
    @endif

    @if ($enabled)
        <div class="cf-card" style="margin-top:1.2rem">
            <div class="cf-card__head">
                <h2>Issue a key</h2>
            </div>
            <div class="cf-card__body">
                <x-form.card title="New API key"
                    description="Keys are read-only at launch: they can read members, events and attendance, and cannot change anything."
                    :action="route('api-access.tokens.store')" submit="Issue key" submit-icon="key" layout="inline">
                    <x-form.input name="name" label="What is this key for?" required maxlength="255"
                        placeholder="e.g. Website events widget"
                        hint="A label so you can tell keys apart later — it has no effect on the key itself." />
                    <x-form.input name="expires_in_days" label="Expires in (days)" type="number" min="1"
                        max="730"
                        hint="Optional. Leave blank to issue a key that does not expire. Use a short-lived key for a one-off import." />
                </x-form.card>
            </div>
        </div>
    @endif

    <div class="cf-card cf-card--flush" style="margin-top:1.2rem">
        <div class="cf-card__head">
            <h2>Keys</h2>
            <span class="cf-tiny cf-muted">{{ $tokens->count() }} issued</span>
        </div>

        @if ($tokens->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">No keys yet</p>
                <p class="cf-small cf-muted">Issue one above to let a website or integration read your church's data.
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Key</th>
                            <th>Status</th>
                            <th>Last used</th>
                            <th>Expires</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tokens as $token)
                            <tr>
                                <td>
                                    <strong>{{ $token->name }}</strong>
                                    <div class="cf-tiny cf-muted">
                                        Issued by {{ $token->creator?->name ?? 'a deleted user' }}
                                        · {{ $token->created_at?->format('d M Y') }}
                                    </div>
                                </td>
                                <td class="cf-mono cf-small">{{ $token->token_prefix }}…</td>
                                <td>
                                    @if ($token->revoked_at)
                                        <span class="cf-badge cf-badge--danger">Revoked</span>
                                    @elseif(!$token->isActive())
                                        <span class="cf-badge cf-badge--warn">Expired</span>
                                    @else
                                        <span class="cf-badge cf-badge--ok">Active</span>
                                    @endif
                                </td>
                                <td class="cf-small cf-muted">
                                    {{ $token->last_used_at?->diffForHumans() ?? 'Never used' }}
                                </td>
                                <td class="cf-small cf-muted">
                                    {{ $token->expires_at?->format('d M Y') ?? 'No expiry' }}
                                </td>
                                <td style="text-align:right">
                                    @if (!$token->revoked_at)
                                        <form method="POST" action="{{ route('api-access.tokens.revoke', $token) }}"
                                            onsubmit="return confirm('Revoke this key? Any integration using it will stop working immediately.')">
                                            @csrf
                                            <button type="submit"
                                                class="cf-btn cf-btn--secondary cf-btn--sm">Revoke</button>
                                        </form>
                                    @else
                                        <span class="cf-tiny cf-muted">{{ $token->revoked_at->format('d M Y') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="cf-card cf-card--flush" style="margin-top:1.2rem">
        <div class="cf-card__head">
            <h2>Endpoints</h2>
            <span class="cf-tiny cf-muted">Versioned, read-only, JSON</span>
        </div>
        <div class="cf-card__body">
            <table class="cf-table" style="width:100%">
                <tbody>
                    @foreach ($endpoints as [$method, $path, $description])
                        <tr>
                            <td style="white-space:nowrap">
                                <span class="cf-badge">{{ $method }}</span>
                                <code class="cf-mono cf-small">{{ $path }}</code>
                            </td>
                            <td class="cf-small cf-muted">{{ $description }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="cf-tiny cf-muted" style="margin:.9rem 0 0">
                Every request is scoped to the key's church — there is no church parameter, and passing one has no
                effect.
                List endpoints accept <code class="cf-mono">per_page</code> (1–200).
            </p>
        </div>
    </div>
</x-layout>
