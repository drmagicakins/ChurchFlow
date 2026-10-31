<x-layouts.auth :title="'Set a new password · ' . config('marketing.product.name', 'ChurchFlow')" heading="Set a new password" subheading="Choose a new password for your account.">

    @if (!$linkIsUsable)
        {{-- A dead link says so on arrival, instead of after the person has
             typed a new password twice. The form below is hidden in that case:
             offering it would only produce the same error one step later. --}}
        <div class="cf-card" style="display:grid;gap:1rem">
            <div class="cf-alert cf-alert--error" role="alert">
                This password reset link is invalid or has expired.
            </div>
            <p class="cf-small cf-muted" style="margin:0">
                A reset link can only be used once, and it expires one day after it is sent.
            </p>
            <p class="cf-small cf-muted" style="margin:0">
                <a href="{{ route('password.request') }}">Request a new link</a>
            </p>
        </div>
    @else
        <form method="POST" action="{{ route('password.update') }}" class="cf-card" style="display:grid;gap:1rem">
            @csrf

            {{-- The token travels in the body, not the URL: this POST changes a
                 password, and a form action that carried the token in its query
                 string would leak it into referrer logs. --}}
            <input type="hidden" name="token" value="{{ $token }}">

            <x-auth.field name="email" label="Email address" type="email" autocomplete="email" />

            <x-auth.field name="password" label="New password" type="password" autocomplete="new-password" />

            <x-auth.field name="password_confirmation" label="Confirm new password" type="password"
                autocomplete="new-password" />

            <p class="cf-hint" style="margin:0">At least 8 characters. This link can be used once.</p>

            <button type="submit" class="cf-btn cf-btn--primary">Save new password</button>
        </form>
    @endif

</x-layouts.auth>
