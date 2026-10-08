@props([
    'title' => null,
    'description' => null,
    'action' => null,
    'method' => 'POST',
    'submit' => 'Save',
    'submitIcon' => null,
    // 'inline' lays the fields out in a responsive grid (good for a search bar
    // or a compact create form); 'stacked' is one field per row (good for a
    // settings form where each decision deserves its own line).
    'layout' => 'stacked',
    'enctype' => null,
])

@php
    $http = strtoupper($method);
    $spoof = in_array($http, ['PUT', 'PATCH', 'DELETE'], true);
@endphp

<form method="{{ $spoof ? 'POST' : $http }}" @if ($action) action="{{ $action }}" @endif
    @if ($enctype) enctype="{{ $enctype }}" @endif
    {{ $attributes->merge(['class' => 'cf-form']) }}>
    @csrf
    @if ($spoof)
        @method($http)
    @endif

    @if ($title || $description)
        <div class="cf-form__head">
            @if ($title)
                <h2 class="cf-form__title">{{ $title }}</h2>
            @endif
            @if ($description)
                <p class="cf-form__desc">{{ $description }}</p>
            @endif
        </div>
    @endif

    @if (isset($errors) && $errors->any())
        <div class="cf-alert cf-alert--error" role="alert">
            <span class="cf-alert__icon"><x-ui.icon name="alert" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">
                    {{ $errors->count() === 1 ? 'There is a problem with your submission.' : 'There are ' . $errors->count() . ' problems with your submission.' }}
                </p>
                <ul class="cf-alert__list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="cf-form__body cf-form__body--{{ $layout }}">
        {{ $slot }}
    </div>

    <div class="cf-form__actions">
        {{ $actions ?? '' }}
        <button type="submit" class="cf-btn cf-btn--primary">
            @if ($submitIcon)
                <x-ui.icon :name="$submitIcon" class="h-4 w-4" />
            @endif
            {{ $submit }}
        </button>
    </div>
</form>
