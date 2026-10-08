@props(['field', 'label'])
@php
    $current = request('sort', 'full_name');
    $dir = request('direction', 'asc');
    $isActive = $current === $field;
    $nextDir = $isActive && $dir === 'asc' ? 'desc' : 'asc';
@endphp
<a href="{{ request()->fullUrlWithQuery(['sort' => $field, 'direction' => $nextDir, 'page' => null]) }}" class="cfg-sort">
    {{ $label }}
    <span class="cfg-sort__arrow {{ $isActive ? 'is-active' : '' }}">
        {{ $isActive ? ($dir === 'asc' ? '▲' : '▼') : '↕' }}
    </span>
</a>
