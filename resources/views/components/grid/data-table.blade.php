{{--
 | A data grid for large datasets: searchable, sortable, paginated, with
 | optional bulk actions.
 |
 | WHY CLIENT-SIDE SEARCH ON TOP OF SERVER PAGINATION
 | --------------------------------------------------
 | The server already paginates (25/page) and filters by column, which is what
 | keeps a 50,000-member church from loading 50,000 rows. That is the load-
 | bearing part and it stays.
 |
 | What it could not do is the thing people actually reach for: typing a
 | fragment and watching rows narrow instantly, without a round trip. So this
 | adds a CLIENT-side filter over the rows currently loaded, and — the part
 | that matters — says so honestly. The count line reads
 | "Showing 25 of 4,312 — searching within this page" whenever a filter is
 | active, because a search box that silently only searches page 1 of 173 is
 | a lie the user will act on. The magnifier still submits the server-side
 | search for the whole dataset; the instant filter is explicitly the narrow
 | one. Getting this backwards is how a UI "loses" records that exist.
 |
 | Columns are declared once, as (key, label, sortable-field, width, align,
 | render). A cell's `render` receives the row, so a caller can drop in a
 | badge or a link without duplicating the table chrome per page.
--}}

@props([
    'rows', // LengthAwarePaginator
    'columns' => [], // [['key'=>..,'label'=>..,'sort'=>..,'align'=>..,'width'=>..,'render'=>fn], ..]
    'searchRoute' => null, // where the magnifier form posts (GET); null = no server search
    'searchName' => 'q',
    'searchValue' => null,
    'searchPlaceholder' => 'Search…',
    'bulk' => false, // enable row checkboxes + bulk bar
    'bulkRoute' => null,
    'bulkField' => 'ids',
    'bulkActions' => [], // [['name'=>..,'label'=>..,'options'=>[value=>label], ..], ..]
    'empty' => 'No records yet.',
    'emptyHint' => null,
    // NOT a prop. `rowActions` must remain an undeclared slot: declaring it as
    // a prop makes Blade hand the slot object to the component as a variable,
    // so `$rowActions` became a ComponentSlot and the per-row call on the
    // "Actions" cell threw "Object of type Illuminate\View\ComponentSlot is
    // not callable" — a 500 on every page using this grid (finance.accounts.show
    // and others). The guards below use the auto-available $rowActions slot
    // variable via isset(), which is null when the caller passes no slot.
    'counter' => null, // e.g. "4,312 members" — shown in the toolbar
    'filters' => null, // slot: toolbar selects/date inputs, rendered inline
])

@php
    $gridId = 'cfg-' . \Illuminate\Support\Str::random(6);
    $total = method_exists($rows, 'total') ? $rows->total() : $rows->count();
    $perPage = method_exists($rows, 'perPage') ? $rows->perPage() : $rows->count();

    // Row data for the client-side filter. Keyed only by the columns marked
    // searchable (or all text columns when none are marked), and pre-lowered
    // once here rather than per keystroke in the browser.
    $searchKeys = collect($columns)->filter(fn($c) => $c['search'] ?? true)->pluck('key')->all();
@endphp

<div class="cfg" id="{{ $gridId }}" x-data="cfDataGrid({ perPage: {{ (int) $perPage }} })">
    <div class="cfg-toolbar">
        {{-- Instant, client-side filter over the loaded rows. --}}
        <div class="cfg-toolbar__search">
            <x-ui.icon name="search" class="h-4 w-4" />
            <input type="search" x-model="filter" placeholder="{{ $searchPlaceholder }}"
                aria-label="{{ $searchPlaceholder }}" autocomplete="off">
            <button type="button" x-show="filter" x-cloak @click="filter = ''" class="cfg-toolbar__clear"
                aria-label="Clear filter">×</button>
        </div>

        {{-- The magnifier does the SERVER-side search across everything. --}}
        @if ($searchRoute)
            <form method="GET" action="{{ $searchRoute }}" class="cfg-toolbar__searchform" role="search">
                <input type="hidden" value="{{ $searchValue }}" name="{{ $searchName }}" x-ref="serverQuery">
                <button type="submit" class="cf-btn cf-btn--secondary cf-btn--sm"
                    title="Search all {{ number_format($total) }} records on the server">
                    Search all
                </button>
            </form>
        @endif

        {{-- $filters is a SLOT: raw toolbar markup (selects, date inputs). Its
             default is null and not [] precisely because of this line — an
             array here is echoed through e() and throws
             "htmlspecialchars(): Argument #1 ($string) must be of type string,
             array given", which 500s the whole page for every caller that
             simply did not pass a filters slot.

             Verified by re-introducing the bug: with the default back to []
             AND this guard removed, SmokeEveryPageTest fails on
             finance.accounts.show naming the exception. So both layers are
             load-bearing, and this is the second one. --}}
        @if (is_string($filters) || $filters instanceof \Illuminate\Contracts\Support\Htmlable)
            {{ $filters }}
        @endif

        @if ($counter)
            <span class="cfg-toolbar__spacer"></span>
            <span class="cf-small cf-muted cf-nowrap">{{ $counter }}</span>
        @endif
    </div>

    {{-- The honest count. It switches wording when a client-side filter is
         active, because "Showing 4 of 25" and "Showing 4 of 4,312" are very
         different statements and the difference is exactly what a user needs
         to know before they trust an empty result. --}}
    <p class="cfg-countline cf-small cf-muted" role="status" aria-live="polite">
        <template x-if="filter">
            <span>
                Showing <strong x-text="visible"></strong> of <strong>{{ number_format($rows->count()) }}</strong>
                on this page — <span class="cfg-countline__warn">filtering only the {{ number_format($rows->count()) }}
                    rows loaded</span>.
                @if ($searchRoute)
                    Use <strong>Search all</strong> to search the full {{ number_format($total) }}.
                @endif
            </span>
        </template>
        <template x-if="!filter">
            <span>
                Showing <strong>{{ number_format($rows->count()) }}</strong> of
                <strong>{{ number_format($total) }}</strong> records.
            </span>
        </template>
    </p>

    <div class="cfg-tablewrap">
        <table class="cfg-table">
            <thead>
                <tr>
                    @if ($bulk)
                        <th class="cfg-checkbox-col">
                            <input type="checkbox" @checked(false)
                                x-on:change="toggleAll($event.target.checked)" aria-label="Select all rows on this page"
                                :aria-checked="allSelected">
                        </th>
                    @endif

                    @foreach ($columns as $col)
                        <th @if (!empty($col['width'])) style="width:{{ $col['width'] }}" @endif
                            @if (!empty($col['align'])) style="text-align:{{ $col['align'] }}" @endif>
                            @if (!empty($col['sort']))
                                <x-grid.sort-link :field="$col['sort']" :label="$col['label']" />
                            @else
                                {{ $col['label'] }}
                            @endif
                        </th>
                    @endforeach

                    @if (isset($rowActions) && ! $rowActions->isEmpty())
                        <th style="text-align:right;width:1%">Actions</th>
                    @endif
                </tr>
            </thead>

            <tbody>
                @forelse($rows as $i => $row)
                    @php
                        // The searchable text for this row, flattened once.
                        $haystack = collect($searchKeys)
                            ->map(fn($k) => data_get($row, $k))
                            ->filter(fn($v) => is_scalar($v))
                            ->implode(' ');
                    @endphp

                    <tr data-cfg-row data-search="{{ \Illuminate\Support\Str::lower($haystack) }}"
                        data-cfg-index="{{ $i }}"
                        @if ($bulk && method_exists($row, 'getKey')) data-cfg-id="{{ $row->getKey() }}" @endif
                        x-show="isRowVisible($el)"
                        :class="{ 'is-selected': selected.includes(String($el.dataset.cfgId)) }">
                        @if ($bulk)
                            <td class="cfg-checkbox-col">
                                <input type="checkbox" value="{{ $row->getKey() }}" x-model="selected"
                                    aria-label="Select this row">
                            </td>
                        @endif

                        @foreach ($columns as $col)
                            <td @if (!empty($col['align'])) style="text-align:{{ $col['align'] }}" @endif>
                                @if (!empty($col['render']))
                                    {!! $col['render']($row) !!}
                                @else
                                    {{ data_get($row, $col['key']) }}
                                @endif
                            </td>
                        @endforeach

                        @if (isset($rowActions) && ! $rowActions->isEmpty())
                            <td style="text-align:right;white-space:nowrap">{{ $rowActions($row) }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + ($bulk ? 1 : 0) + (isset($rowActions) && ! $rowActions->isEmpty() ? 1 : 0) }}">
                            <div class="cf-empty">
                                <p class="cf-empty__title">{{ $empty }}</p>
                                @if ($emptyHint)
                                    <p class="cf-small cf-muted">{{ $emptyHint }}</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse

                {{-- Shown when a filter matches nothing. Distinct from the
                     real empty state above: "no members yet" and "no members
                     match 'xyz'" are different situations and telling someone
                     the wrong one wastes their time. --}}
                <tr x-show="filter && visible === 0" x-cloak>
                    <td colspan="{{ count($columns) + ($bulk ? 1 : 0) + (isset($rowActions) && ! $rowActions->isEmpty() ? 1 : 0) }}">
                        <div class="cf-empty">
                            <p class="cf-empty__title">No rows match “<span x-text="filter"></span>” on this page</p>
                            <p class="cf-small cf-muted">
                                <button type="button" class="cf-linkish" @click="filter = ''">Clear the filter</button>
                                @if ($searchRoute)
                                    or use <strong>Search all</strong> to search every record.
                                @endif
                            </p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    @if ($bulk && $bulkRoute)
        {{-- A sticky bulk bar: an action on selected rows is easy to trigger by
             accident and hard to undo, so it moves with the selection rather
             than sitting at the top where it is out of sight. --}}
        <div class="cfg-bulkbar" x-show="selected.length" x-cloak>
            <span><strong x-text="selected.length"></strong> selected</span>
            <form method="POST" action="{{ $bulkRoute }}" x-ref="bulkForm" data-cf-loading>
                @csrf
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="{{ $bulkField }}[]" :value="id">
                </template>
                @foreach ($bulkActions as $action)
                    @if (($action['type'] ?? 'select') === 'select')
                        <select name="{{ $action['name'] }}" class="cfg-bulkbar__select" required>
                            <option value="">{{ $action['label'] }}</option>
                            @foreach ($action['options'] as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                @endforeach
                <button type="submit" class="cf-btn cf-btn--primary cf-btn--sm"
                    :disabled="!selected.length">Apply</button>
            </form>
            <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" @click="selected = []">Clear</button>
        </div>
    @endif

    @if (method_exists($rows, 'links') && $rows->hasPages())
        <div class="cfg-pager">{{ $rows->links() }}</div>
    @endif
</div>
