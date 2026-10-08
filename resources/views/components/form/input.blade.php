@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'placeholder' => null,
    'autocomplete' => null,
    'disabled' => false,
    // Renders the input on its own line even inside a grid — for a field whose
    // content is long (notes, an address) and would be cramped in a column.
    'wide' => false,
])

@php
    // $errors is always available inside a Blade view rendered by Laravel, but
    // a component can be rendered in contexts where it isn't (a mail template,
// a test asserting raw HTML), so fall back to an empty bag rather than
// letting an undefined variable take the whole page down.
$errorBag = isset($errors) ? $errors : new Illuminate\Support\ViewErrorBag();
$hasError = $errorBag->has($name);
$id = 'field-' . str_replace(['[', ']', '.'], ['-', '', '-'], $name);
@endphp

<div class="cf-field {{ $wide ? 'cf-field--wide' : '' }}">
    @if ($label)
        <label class="cf-label" for="{{ $id }}">
            {{ $label }}
            @if ($required)
                <span class="cf-label__req" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <input
        {{ $attributes->merge([
            'class' => 'cf-input',
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'aria-invalid' => $hasError ? 'true' : null,
            'aria-describedby' => $hasError || $hint ? $id . '-meta' : null,
            'required' => $required ?: null,
            'disabled' => $disabled ?: null,
        ]) }}
        @if ($value !== null) value="{{ $value }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif>

    @if ($hasError)
        <p class="cf-error" id="{{ $id }}-meta" role="alert">{{ $errorBag->first($name) }}</p>
    @elseif($hint)
        <p class="cf-hint" id="{{ $id }}-meta">{{ $hint }}</p>
    @endif
</div>
