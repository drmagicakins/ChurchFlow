{{--
 | A labelled auth input.
 |
 | The login and register forms previously repeated the same five lines of markup
 | per field (label > span + input + optional hint). Six fields across two pages
 | meant a change to input styling had to be made in six places, and they had
 | already drifted: login labelled its inputs with `.cf-stat__label` — a class
 | named for the dashboard's stat tiles, borrowed because it happened to look
 | right — while the hint line used `.cf-tiny`.
 |
 | Props:
 |   name        (required) input name, also used for the error key and the id
 |   label       (required) visible label text
 |   type        input type, default "text"
 |   value       prefilled value; falls back to old($name) on the login/register forms
 |   hint        small helper text rendered under the input
 |   autocomplete / autofocus / required / placeholder — passed straight through
 |
 | The error message is rendered inline per field rather than only in the summary
 | at the top, because a summary alone makes the visitor hunt for which box is
 | wrong — and the login form's single error (a wrong password) is attached to the
 | email key by LoginController, so it would otherwise appear to accuse the email.
--}}

@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'autocomplete' => null,
    'autofocus' => false,
    'required' => true,
    'placeholder' => null,
])

@php
    $id = 'field-' . $name;
    $hasError = $errors->has($name);
    $resolved = old($name, $value);
@endphp

<div class="cf-field">
    <label for="{{ $id }}" class="cf-stat__label" style="display:block;margin-bottom:.35rem">
        {{ $label }}
        @if (!$required)
            <span class="cf-tiny cf-muted">(optional)</span>
        @endif
    </label>

    <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}"
        @if ($resolved !== null && $resolved !== '') value="{{ $resolved }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($autofocus) autofocus @endif @if ($required) required @endif
        @class(['cf-input', 'is-invalid' => $hasError])
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes }}>

    @if ($hint && !$hasError)
        <span class="cf-tiny cf-muted" style="display:block;margin-top:.3rem">{{ $hint }}</span>
    @endif

    @if ($hasError)
        <span id="{{ $id }}-error" class="cf-tiny"
            style="display:block;margin-top:.3rem;color:var(--danger, #dc2626)">
            {{ $errors->first($name) }}
        </span>
    @endif
</div>
