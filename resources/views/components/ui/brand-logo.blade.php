@props(['size' => 36, 'onDark' => false, 'href' => null])

{{--
 | ChurchFlow logo lockup: brand mark + live wordmark.
 |
 | See ui/brand-mark.blade.php for why the raster logo is not used here. The
 | wordmark is real text ("Church" in navy/white, "Flow" in the green accent) so it
 | stays legible at 14px, scales with the font scale setting, and is selectable and
 | searchable — none of which is true of a wordmark baked into a PNG.
 |
 | $onDark flips "Church" to white for the navy footer; "Flow" stays green either
 | way, because that two-tone split IS the wordmark.
--}}

@php
    $tag = $href ? 'a' : 'span';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'cf-brandlogo']) }}>
    <x-ui.brand-mark :size="$size" />
    <span class="cf-brandlogo__text {{ $onDark ? 'cf-brandlogo__text--dark' : '' }}">
        Church<span class="cf-brandlogo__accent">Flow</span>
    </span>
    </{{ $tag }}>
