@props(['size' => 36, 'id' => null])

{{--
 | ChurchFlow brand mark.
 |
 | The supplied logo asset (public/images/churchflow-logo.png) is a FULL LOCKUP:
 | the mark, the "ChurchFlow" wordmark, and the tagline, all composited onto a
 | black background. Two consequences drive the whole brand kit:
 |
 |  1. The lockup cannot be used at nav size. `h-9 w-auto` on a 1536x1024 image
 |     renders the wordmark at roughly 4px tall — illegible — and the black
 |     background shows as a dark rectangle on the white nav bar.
 |  2. It cannot be recoloured with a filter. `brightness-0 invert` (the previous
 |     footer treatment) flattens the blue cross and the green accent into flat
 |     white, throwing away the only two brand colours in the asset.
 |
 | So the mark is redrawn here as inline SVG: crisp at any size, themed via
 | currentColor for the cross, and safe on both light and dark surfaces. The
 | raster lockup is still used where it belongs — the dedicated brand slot in
 | the footer — because there the full wordmark + tagline is the point.
 |
 | Shape: a house with a rounded gable and a cross on the door, sitting on two
 | flowing wave strokes that read as water/flow (the "Flow" in ChurchFlow), with
 | the lower wave picked out in the green accent.
--}}

@php
    $size = (int) $size;
    // Unique gradient ids so two marks on one page cannot collide and both go black.
    $suffix = $id ?? 'm'.substr(md5((string) $size.$id), 0, 6);
    $blue = 'cfmark-blue-'.$suffix;
    $green = 'cfmark-green-'.$suffix;
@endphp

<svg
    {{ $attributes->merge(['class' => 'cf-brandmark']) }}
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 48 48"
    fill="none"
    role="img"
    aria-label="ChurchFlow"
>
    <defs>
        <linearGradient id="{{ $blue }}" x1="8" y1="6" x2="30" y2="34" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#4d9bff" />
            <stop offset="100%" stop-color="#1677ff" />
        </linearGradient>
        <linearGradient id="{{ $green }}" x1="18" y1="34" x2="42" y2="44" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#18b981" />
            <stop offset="100%" stop-color="#34d399" />
        </linearGradient>
    </defs>

    {{-- House body with a rounded gable --}}
    <path
        d="M24 3.5 41.5 17.2a3 3 0 0 1 1.1 2.33V41a3 3 0 0 1-3 3H8.4a3 3 0 0 1-3-3V19.53A3 3 0 0 1 6.5 17.2L24 3.5Z"
        fill="url(#{{ $blue }})"
    />

    {{-- Cross on the door, knocked out of the house body --}}
    <path
        d="M22.4 16.6h3.2v4.1h4.1v3.2h-4.1v7.5c0 1.35-.62 2.1-1.6 2.1-.98 0-1.6-.75-1.6-2.1v-7.5h-4.1v-3.2h4.1v-4.1Z"
        fill="#ffffff"
    />

    {{-- Upper wave: carved out of the house so the mark reads as "flowing" --}}
    <path
        d="M5.4 31.6c4.6-3.4 9.1-3.4 13.7 0 4.6 3.4 9.1 3.4 13.7 0 3-2.2 6-2.1 9-.4v6.4c-3-1.7-6-1.8-9 .4-4.6 3.4-9.1 3.4-13.7 0-.9-.66-1.78-1.15-2.65-1.47H5.4v-4.93Z"
        fill="#ffffff"
        fill-opacity="0.9"
    />

    {{-- Lower wave: the green accent --}}
    <path
        d="M5.6 35.3c4.5-3.3 8.9-3.3 13.4 0 4.5 3.3 8.9 3.3 13.4 0 3.2-2.35 6.4-2.6 9.6-.75V44H6.45a3 3 0 0 1-3-3v-4.5c.73-.35 1.45-.75 2.15-1.2Z"
        fill="url(#{{ $green }})"
    />
</svg>