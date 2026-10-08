<x-layouts.auth
    :title="'Create your account · '.config('marketing.product.name', 'ChurchFlow')"
    heading="Create your account"
    subheading="Start with a free account, then choose a plan to set up your church. No church is created until payment is complete.">

    <form method="POST" action="{{ route('register') }}" class="cf-card" style="display:grid;gap:1rem">
        @csrf

        <x-auth.field
            name="name"
            label="Your name"
            autocomplete="name"
            :autofocus="true" />

        <x-auth.field
            name="email"
            label="Email address"
            type="email"
            autocomplete="email" />

        <x-auth.field
            name="password"
            label="Password"
            type="password"
            autocomplete="new-password"
            hint="At least 8 characters." />

        <x-auth.field
            name="password_confirmation"
            label="Confirm password"
            type="password"
            autocomplete="new-password" />

        <button type="submit" class="cf-btn cf-btn--primary">Create account</button>

        <p class="cf-small cf-muted">
            Already have an account? <a href="{{ route('login') }}">Sign in</a>
        </p>
    </form>

</x-layouts.auth>
