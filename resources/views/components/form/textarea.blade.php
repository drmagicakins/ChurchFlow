@props([
    'name',
    'label' => null,
    'value' => null,
    'hint' => null,
    'required' => false,
    'placeholder' => null,
    'rows' => 4,
])

@php
    $errorBag = isset($errors) ? $errors : new Illuminate\Support\ViewErrorBag();
    $hasError = $errorBag->has($name);
    $id = 'field-' . str_replace(['[', ']', '.'], ['-', '', '-'], $name);
@endphp

<div class="cf-field cf-field--wide">
    @if ($label)
        <label class="cf-label" for="{{ $id }}">
            {{ $label }}
            @if ($required)
                <span class="cf-label__req" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <textarea
        {{ $attributes->merge([
            'class' => 'cf-textarea',
            'id' => $id,
            'name' => $name,
            'rows' => $rows,
            'aria-invalid' => $hasError ? 'true' : null,
            'aria-describedby' => $hasError || $hint ? $id . '-meta' : null,
            'required' => $required ?: null,
        ]) }}
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif>{{ $value !== null ? $value : old($name) }}</textarea>

    @if ($hasError)
        <p class="cf-error" id="{{ $id }}-meta" role="alert">{{ $errorBag->first($name) }}</p>
    @elseif($hint)
        <p class="cf-hint" id="{{ $id }}-meta">{{ $hint }}</p>
    @endif
</div>
