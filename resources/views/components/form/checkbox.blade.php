@props(['name', 'label', 'checked' => false, 'hint' => null, 'value' => '1'])

@php
    $id = 'field-' . str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $isChecked = (bool) old($name, $checked);
@endphp

{{-- A checkbox needs the same field wrapper as every other input, or it breaks
     the vertical rhythm of a form: the layout is driven by .cf-field margins.
     The visually-hidden input + styled box is what makes it match the rest of
     the design system rather than falling back to the browser default. --}}
<div class="cf-field">
    <label class="cf-check cf-check--boxed" for="{{ $id }}">
        <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}"
            {{ $isChecked ? 'checked' : '' }} {{ $attributes }}>
        <span class="cf-check__box" aria-hidden="true">
            <x-ui.icon name="check" class="h-3.5 w-3.5" />
        </span>
        <span class="cf-check__text">
            <span class="cf-check__label">{{ $label }}</span>
            @if ($hint)
                <span class="cf-check__hint">{{ $hint }}</span>
            @endif
        </span>
    </label>
</div>
