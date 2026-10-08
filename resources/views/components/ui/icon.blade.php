@props(['name', 'class' => 'h-5 w-5'])

{{--
 | Shared outline icon set for the marketing site.
 |
 | Before this existed, five different sections each inlined their own <svg> with a
 | hardcoded path — and four of those paths were the same generic "shield with a
 | check" plus a keyboard-mash `d="M4 6h16M4 12h16M4 18h7"`, so Members, Finance,
 | Subvention, Events, Reports and "And More" all showed an identical hamburger
 | glyph. An icon that says nothing is worse than no icon: it reads as unfinished.
 |
 | All paths are 24x24, stroke-based, and inherit `currentColor`, so a section
 | controls colour purely by setting a text-* class on the wrapper.
 |
 | Unknown names fall back to 'sparkle' rather than rendering an empty box, so a
 | typo shows up as a harmless neutral glyph instead of a hole in the layout.
--}}

@php
    $paths = [
        // People & organisation
        'users' => [
            'M17 20h5v-2a3 3 0 0 0-2.6-2.97M9 20H4v-2a3 3 0 0 1 2.6-2.97M15 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm4-.5a2.5 2.5 0 0 1 0 5M5 12a2.5 2.5 0 0 0 0-5',
            'M12 21c-3 0-5.5-.6-5.5-2.2S9 16.5 12 16.5s5.5.6 5.5 2.3S15 21 12 21Z',
        ],
        'user' => ['M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2', 'M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z'],
        'search' => ['M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z', 'M20 20l-3.5-3.5'],
        'family' => [
            'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8 0a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
            'M3 20v-1.5c0-2 1.8-3.5 4-3.5h4c2.2 0 4 1.5 4 3.5V20',
            'M17 20v-1.5c0-1.2-.5-2.2-1.4-2.9',
        ],
        'building' => ['M3 21h18', 'M5 21V7l8-4v18', 'M13 21V11l6 3v7', 'M9 9v.01M9 12v.01M9 15v.01'],
        'branches' => ['M12 21V9', 'M12 9 6 5', 'M12 9l6-4', 'M6 5H4v4a3 3 0 0 0 3 3h2', 'M18 5h2v4a3 3 0 0 1-3 3h-2'],

        // Finance
        'wallet' => [
            'M3 8.5A2.5 2.5 0 0 1 5.5 6H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5.5A2.5 2.5 0 0 1 3 16.5v-8Z',
            'M3 9h18',
            'M16.5 12.5h.01',
        ],
        'naira' => ['M6 19V5l12 14V5'],
        'chart-up' => ['M3 20h18', 'M6 16V9', 'M11 16v-4', 'M16 16V6', 'M21 16v-7'],
        'receipt' => ['M6 3h12v18l-3-2-3 2-3-2-3 2V3Z', 'M9 8h6', 'M9 12h6'],
        'pie' => ['M12 3a9 9 0 1 0 9 9h-9V3Z', 'M14 3.5A9 9 0 0 1 20.5 10H14V3.5Z'],
        'vault' => [
            'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z',
            'M12 14a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
            'M12 9V6.5M12 17.5V15',
        ],

        // Activities
        'calendar' => [
            'M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z',
            'M4 10h16',
            'M8 3v4',
            'M16 3v4',
        ],
        'check-in' => [
            'M9 4H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h3',
            'M15 4h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3',
            'M10 12l2 2 4-4',
        ],
        'megaphone' => ['M4 10v4a1 1 0 0 0 1 1h2l1 5h3l-1-5 8 3V6L7 9H5a1 1 0 0 0-1 1Z', 'M18 9a3 3 0 0 1 0 6'],
        'mail' => ['M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z', 'M3.5 6.5 12 13l8.5-6.5'],
        'chat' => ['M20 12c0 4-3.6 7-8 7a9 9 0 0 1-3.9-.9L4 20l1.2-3.6A7.3 7.3 0 0 1 4 12c0-3.9 3.6-7 8-7s8 3.1 8 7Z'],
        'task' => [
            'M9 5h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3Z',
            'M9 3h6v4H9V3Z',
            'M9 13l2 2 4-4',
        ],

        // Care & insight
        'heart' => ['M12 20.5 4.6 13a4.6 4.6 0 0 1 6.5-6.5l.9.9.9-.9A4.6 4.6 0 1 1 19.4 13L12 20.5Z'],
        'shield' => ['M12 3 5 6v5.5c0 4.3 2.9 8.2 7 9.5 4.1-1.3 7-5.2 7-9.5V6l-7-3Z', 'M9.5 12l1.8 1.8L15 10'],
        'lock' => [
            'M6 11h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Z',
            'M8.5 11V8a3.5 3.5 0 1 1 7 0v3',
        ],
        'bolt' => ['M13 3 5 13h6l-1 8 8-10h-6l1-8Z'],
        'doc' => ['M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z', 'M14 3v5h5', 'M9 13h6', 'M9 17h6'],
        'sparkle' => [
            'M12 4l1.7 4.6L18 10l-4.3 1.4L12 16l-1.7-4.6L6 10l4.3-1.4L12 4Z',
            'M18.5 15.5l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2Z',
        ],
        'flow' => ['M4 8c3-2.4 6-2.4 9 0s6 2.4 9 0', 'M4 14c3-2.4 6-2.4 9 0s6 2.4 9 0'],
        'rocket' => [
            'M14 4c3.5 0 6 2.5 6 6-1.8 4.4-4.8 7-9 7.5l-3-3C8.5 10.3 11 7.3 14 4Z',
            'M9 15l-3 3',
            'M7 17.5 5 21l3.5-2',
            'M15 9.5h.01',
        ],
        'settings' => [
            'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
            'M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 7 19.4a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 2.6 15H2.5a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 7a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 2.6V2.5a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.4 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 21.4 9h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.4 1Z',
        ],
        'clock' => ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 7.5V12l3 2'],
        'route' => [
            'M6 20a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
            'M18 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
            'M18 9v3a4 4 0 0 1-4 4H9a3 3 0 0 0-3 3',
        ],
        'phone' => [
            'M8 2.5h8a1.5 1.5 0 0 1 1.5 1.5v16a1.5 1.5 0 0 1-1.5 1.5H8A1.5 1.5 0 0 1 6.5 20V4A1.5 1.5 0 0 1 8 2.5Z',
            'M11 18h2',
        ],
        'arrow-right' => ['M5 12h14', 'M13 6l6 6-6 6'],
        'play' => ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M10.5 9l5 3-5 3V9Z'],
        'check' => ['M5 13l4 4L19 7'],
        'bell' => ['M18 15V10a6 6 0 1 0-12 0v5l-1.5 3h15L18 15Z', 'M10 20.5a2.2 2.2 0 0 0 4 0'],
        'grid' => [
            'M4 5h6a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z',
            'M14 5h6a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-6a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z',
            'M4 13h6a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1Z',
            'M14 13h6a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-6a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1Z',
        ],
        'sliders' => [
            'M4 7h10M18 7h2',
            'M4 17h4M12 17h8',
            'M16 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
            'M10 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
        ],
    ];

    $set = $paths[$name] ?? $paths['sparkle'];
@endphp

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor"
    stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @foreach ($set as $d)
        <path d="{{ $d }}" />
    @endforeach
</svg>
