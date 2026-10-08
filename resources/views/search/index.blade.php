<x-layout title="Search · ChurchFlow">
    <div class="cf-page-head">
        <div>
            <h1>Search</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                @if ($term !== '' && ! $tooShort) Results for “{{ $term }}” @else Find members, families, events, accounts and more. @endif
            </p>
        </div>
    </div>

    <form method="GET" action="{{ route('search.index') }}" class="cfd-search" role="search" style="max-width:none;margin-bottom:1.2rem">
        <x-ui.icon name="search" class="h-4 w-4" />
        <input type="search" name="q" value="{{ $term }}" placeholder="Search ChurchFlow..." aria-label="Search ChurchFlow" autofocus>
    </form>

    @if ($tooShort)
        <p class="cfd-empty">Type at least {{ \App\Domains\Search\Services\GlobalSearchService::MIN_LENGTH }} characters to search.</p>
    @elseif ($term !== '' && $groups === [])
        <div class="cfd-panel"><p class="cfd-empty">No results for “{{ $term }}”. Try a name, phone number, email or membership number.</p></div>
    @else
        <div class="cfd-stack">
            @foreach ($groups as $group)
                <section class="cfd-panel">
                    <div class="cfd-panel__head">
                        <h2><span class="cfd-ico cfd-ico--sm cfd-ico--blue"><x-ui.icon :name="$group['icon']" class="h-4 w-4" /></span>{{ $group['label'] }}</h2>
                        <span class="cf-tiny cf-muted">{{ count($group['items']) }}</span>
                    </div>
                    @foreach ($group['items'] as $item)
                        <a href="{{ $item['url'] }}" class="cfd-row">
                            <div><p class="cfd-row__title">{{ $item['title'] }}</p>@if ($item['subtitle'] !== '')<p class="cfd-row__sub">{{ $item['subtitle'] }}</p>@endif</div>
                        </a>
                    @endforeach
                </section>
            @endforeach
        </div>
    @endif
</x-layout>
