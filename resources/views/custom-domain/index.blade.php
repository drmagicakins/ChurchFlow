<x-layout title="Custom domain · ChurchFlow">
    <div class="cf-page-head">
        <div>
            <h1>Custom domain</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Serve your workspace on your own hostname, such as
                <code class="cf-mono">app.gracechapel.org</code>.
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

    @if (session('error'))
        <div class="cf-alert cf-alert--warn" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="info" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    @if (!$enabled)
        <div class="cf-alert cf-alert--warn" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="lock" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">A custom domain is not included in your current plan</p>
                <p class="cf-small" style="margin:.3rem 0 0">
                    @if ($requiredPlan)
                        It is included from the <strong>{{ $requiredPlan }}</strong> plan.
                    @endif
                    <a href="{{ route('billing.plans') }}">View plans</a> to upgrade.
                </p>
            </div>
        </div>
    @elseif($domain)
        <div class="cf-card">
            <div class="cf-card__head">
                <h2>{{ $domain->hostname }}</h2>
                @if ($domain->isVerified())
                    <span class="cf-badge cf-badge--ok">Verified</span>
                @elseif($domain->last_checked_at)
                    <span class="cf-badge cf-badge--warn">Not verified yet</span>
                @else
                    <span class="cf-badge">Awaiting DNS</span>
                @endif
            </div>
            <div class="cf-card__body" style="display:grid;gap:1rem">
                <p class="cf-small cf-muted" style="margin:0">
                    @if ($domain->isVerified())
                        This domain is verified and will serve your workspace once its CNAME points at
                        <code class="cf-mono">{{ $target }}</code>.
                    @else
                        Add the two DNS records below at your domain provider, then check verification.
                        DNS changes can take a few minutes to propagate.
                    @endif
                </p>

                <div class="cf-table-wrap">
                    <table class="cf-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Name / host</th>
                                <th>Value</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="cf-badge">TXT</span></td>
                                <td class="cf-mono cf-small">{{ $domain->verificationRecordName() }}</td>
                                <td class="cf-mono cf-small" style="word-break:break-all">
                                    {{ $domain->verification_token }}</td>
                                <td class="cf-small cf-muted">Proves you control the domain.</td>
                            </tr>
                            <tr>
                                <td><span class="cf-badge">CNAME</span></td>
                                <td class="cf-mono cf-small">{{ $domain->hostname }}</td>
                                <td class="cf-mono cf-small">{{ $target }}</td>
                                <td class="cf-small cf-muted">Routes traffic to ChurchFlow.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if ($domain->last_error)
                    <div class="cf-alert cf-alert--warn" role="status">
                        <div class="cf-alert__body">
                            <p class="cf-small" style="margin:0">{{ $domain->last_error }}</p>
                            <p class="cf-tiny cf-muted" style="margin:.25rem 0 0">
                                Last checked {{ $domain->last_checked_at?->diffForHumans() }}.
                            </p>
                        </div>
                    </div>
                @endif

                <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                    <form method="POST" action="{{ route('custom-domain.verify') }}">
                        @csrf
                        <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm">
                            <x-ui.icon name="check" class="h-4 w-4" /> Check verification
                        </button>
                    </form>

                    <form method="POST" action="{{ route('custom-domain.destroy') }}"
                        onsubmit="return confirm('Release {{ $domain->hostname }}? Visitors using it will stop reaching your workspace.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Release domain</button>
                    </form>
                </div>

                @if ($domain->verified_at)
                    <p class="cf-tiny cf-muted" style="margin:0">Verified
                        {{ $domain->verified_at->format('d M Y, H:i') }}.</p>
                @endif
            </div>
        </div>
    @else
        <div class="cf-card">
            <div class="cf-card__head">
                <h2>Claim a domain</h2>
            </div>
            <div class="cf-card__body">
                <x-form.card title="Your domain"
                    description="Enter the bare hostname — no https:// and no path. You will prove you own it with a DNS record before it goes live."
                    :action="route('custom-domain.store')" submit="Claim domain" submit-icon="globe" layout="inline">
                    <x-form.input name="hostname" label="Domain" required maxlength="255"
                        placeholder="e.g. app.gracechapel.org"
                        hint="A subdomain is recommended so your main website can stay where it is." />
                </x-form.card>
            </div>
        </div>
    @endif
</x-layout>
