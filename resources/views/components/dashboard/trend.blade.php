@props(['pct' => null, 'invert' => false, 'suffix' => 'this month'])
{{-- Null means "no prior period to compare against" — shown as a dash, never a made-up percentage.
     $invert flips the colour logic for costs (an expense going down is good news). --}}
@if ($pct === null)
    <span class="cfd-trend cfd-trend--flat">— no prior data</span>
@else
    @php
        $up = $pct > 0;
        $good = $invert ? ! $up : $up;
        $class = $pct == 0 ? 'cfd-trend--flat' : ($good ? 'cfd-trend--up' : 'cfd-trend--down');
    @endphp
    <span class="cfd-trend {{ $class }}">{{ $pct > 0 ? '↑' : ($pct < 0 ? '↓' : '→') }} {{ rtrim(rtrim(number_format(abs($pct), 1), '0'), '.') }}% {{ $suffix }}</span>
@endif
