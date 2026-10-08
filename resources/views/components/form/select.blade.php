@props([
    'name',
    'label' => null,
    'options' => [], // [value => label] or [['value'=>..,'label'=>..]]
    'value' => null,
    'hint' => null,
    'required' => false,
    'placeholder' => null, // renders a disabled empty first option
    'disabled' => false,
])

@php
    $errorBag = isset($errors) ? $errors : new Illuminate\Support\ViewErrorBag();
    $hasError = $errorBag->has($name);
    $id = 'field-' . str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $selected = old($name, $value);

    // Accept either a plain map or a list of {value,label} pairs, so a caller
    // with numeric-string keys (which PHP would silently cast to ints in a
    // plain map, breaking the comparison) isn't forced to work around it.
$normalised = collect($options)
    ->map(function ($label, $key) {
        return is_array($label)
            ? ['value' => (string) $label['value'], 'label' => $label['label']]
            : ['value' => (string) $key, 'label' => $label];
        })
        ->values();
@endphp

<div class="cf-field">
    @if ($label)
        <label class="cf-label" for="{{ $id }}">
            {{ $label }}
            @if ($required)
                <span class="cf-label__req" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <select
        {{ $attributes->merge([
            'class' => 'cf-select',
            'id' => $id,
            'name' => $name,
            'aria-invalid' => $hasError ? 'true' : null,
            'aria-describedby' => $hasError || $hint ? $id . '-meta' : null,
            'required' => $required ?: null,
            'disabled' => $disabled ?: null,
        ]) }}>
        @if ($placeholder)
            <option value="" disabled {{ $selected === null || $selected === '' ? 'selected' : '' }}>
                {{ $placeholder }}
            </option>
        @endif

        @foreach ($normalised as $option)
            <option value="{{ $option['value'] }}" {{ (string) $selected === $option['value'] ? 'selected' : '' }}>
                {{ $option['label'] }}
            </option>
        @endforeach
    </select>

    @if ($hasError)
        <p class="cf-error" id="{{ $id }}-meta" role="alert">{{ $errorBag->first($name) }}</p>
    @elseif($hint)
        <p class="cf-hint" id="{{ $id }}-meta">{{ $hint }}</p>
    @endif
</div>
