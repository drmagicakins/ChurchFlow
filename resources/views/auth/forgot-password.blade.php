<x-layouts.auth :title="'Forgot password · ' . config('marketing.product.name', 'ChurchFlow')" heading="Forgot your password?"
    subheading="Enter the email address you sign in with and we will send you a link to set a new one.">

    <form method="POST" action="{{ route('password.email') }}" class="cf-card" style="display:grid;gap:1rem">
        @csrf

        <x-auth.field name="email" label="Email address" type="email" autocomplete="email" :autofocus="true" />

        <button type="submit" class="cf-btn cf-btn--primary">Send reset link</button>

        {{-- Deliberately the same sentence whether or not the address exists:
             see PasswordResetController — this page must not work as a
             directory of who has an account. --}}
        <p class="cf-small cf-muted">
            Remembered it? <a href="{{ route('login') }}">Back to sign in</a>
        </p>
    </form>

</x-layouts.auth>
