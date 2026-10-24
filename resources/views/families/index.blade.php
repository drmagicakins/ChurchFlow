<x-layout title="Families · ChurchFlow">
    @php $canManage = auth()->user()->hasPermission('families.manage'); @endphp

    <div class="cf-page-head">
        <div>
            <h1>Families</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                {{ number_format($families->total()) }} {{ \Illuminate\Support\Str::plural('family', $families->total()) }} ·
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

    {{-- The church's member directory, filtered by household. This is the table
         and the count that matter on this page: a family with nobody linked to
         it is still a family, but a MEMBER is always a member of the church
         whether or not anyone has put them in a household yet. Counting only
         linked members made an upload of new people look like it had done
         nothing at all. --}}
    <div class="cf-card cf-card--flush" style="margin-bottom:1.4rem" id="members">
        <div class="cf-card__head" style="display:flex;gap:1rem;align-items:center;justify-content:space-between;flex-wrap:wrap">
            <div>
                <h2 style="margin:0">Members</h2>
                <span class="cf-tiny cf-muted">
                    {{ number_format($totalMembers) }} {{ \Illuminate\Support\Str::plural('member', $totalMembers) }} in the church
                    · {{ number_format($unassignedMembers) }} not yet in a family
                </span>
            </div>

            <form method="GET" action="{{ route('families.index') }}#members"
                style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
                <div class="cf-field" style="margin:0;min-width:14rem">
                    <label class="cf-label" for="family-filter">Family</label>
                    <select name="family" id="family-filter" class="cf-select">
                        <option value="">All families members</option>
                        @foreach($families as $option)
                            <option value="{{ $option->id }}" @selected($selectedFamilyId === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cf-field" style="margin:0;min-width:12rem">
                    <label class="cf-label" for="family-filter-q">Search</label>
                    <input type="search" name="q" id="family-filter-q" class="cf-input" value="{{ request('q') }}"
                        placeholder="Name, email or phone">
                </div>

                <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm">
                    <x-ui.icon name="search" class="h-4 w-4" /> Apply
                </button>
                @if($selectedFamilyId !== null || request()->filled('q'))
                    <a href="{{ route('families.index') }}#members" class="cf-btn cf-btn--secondary cf-btn--sm">Clear</a>
                @endif
            </form>
        </div>

        @if($members->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">
                    {{ $selectedFamilyId !== null || request()->filled('q') ? 'No members match that filter' : 'No members yet' }}
                </p>
                <p class="cf-small cf-muted">
                    @if($selectedFamilyId !== null)
                        Nobody has been linked to this household yet.
                        <a href="{{ route('families.index') }}#members">Show every member</a>.
                    @elseif(request()->filled('q'))
                        <a href="{{ route('families.index') }}#members">Clear the search</a> to see everyone.
                    @else
                        Members you add or import will appear here.
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
                            <th>Family</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $person)
                            <tr>
                                <td class="cf-mono cf-small" style="white-space:nowrap">{{ $person->membership_number }}</td>
                                <td><a href="{{ route('members.show', $person) }}">{{ $person->full_name }}</a></td>
                                <td class="cf-small cf-muted">
                                    @php $households = $person->families->pluck('name'); @endphp
                                    @if($households->isNotEmpty())
                                        {{ $households->join(', ') }}
                                    @else
                                        <span class="cf-badge">Not assigned</span>
                                    @endif
                                </td>
                                <td class="cf-small cf-muted">{{ $person->organizationalUnit?->name ?? '—' }}</td>
                                <td>
                                    @php
                                        $tone = match ($person->membership_status) {
                                            'active' => 'cf-badge--ok',
                                            'visitor' => 'cf-badge--brand',
                                            'transferred', 'deceased' => 'cf-badge--danger',
                                            default => '',
                                        };
                                    @endphp
                                    <span class="cf-badge {{ $tone }}">{{ str_replace('_', ' ', ucfirst($person->membership_status ?? 'unknown')) }}</span>
                                </td>
                                <td style="text-align:right;white-space:nowrap">
                                    <a href="{{ route('members.show', $person) }}"
                                        class="cf-btn cf-btn--secondary cf-btn--sm">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($members->hasPages())
                <div style="padding:1rem 1.35rem">{{ $members->links() }}</div>
            @endif
        @endif
    </div>

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
                                <td class="cf-mono" style="text-align:right">{{ $item->members_count ?? $item->members->count() }}</td>
                                <td style="text-align:right">
                                    <div style="display:inline-flex;gap:.4rem;align-items:center">
                                        <a href="{{ route('families.show', $item) }}" class="cf-btn cf-btn--secondary cf-btn--sm">View</a>
                                        @if($canManage)
                                            <form method="POST" action="{{ route('families.destroy', $item) }}"
                                                data-confirm="Remove the {{ $item->name }} family? The members themselves are not deleted — they simply stop being grouped as a household."
                                                data-confirm-title="Remove family?" data-confirm-ok="Remove family" style="display:inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm">Remove</button>
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

    @if($families->hasPages())
        <div style="padding:1rem 1.35rem">{{ $families->links() }}</div>
    @endif

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
