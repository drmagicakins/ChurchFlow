<x-layouts.auth
    :title="'Sign in · '.config('marketing.product.name', 'ChurchFlow')"
    heading="Sign in"
    subheading="Welcome back. Sign in to manage your church.">

    <form method="POST" action="{{ route('login') }}" class="cf-card" style="display:grid;gap:1rem">
        @csrf

        <x-auth.field
            name="email"
            label="Email address"
            type="email"
            autocomplete="email"
            :autofocus="true" />

        <x-auth.field
            name="password"
            label="Password"
            type="password"
            autocomplete="current-password" />

        <label class="cf-small" style="display:flex;align-items:center;gap:.5rem">
            <input type="checkbox" name="remember" value="1">
            <span>Remember me</span>
        </label>

        <button type="submit" class="cf-btn cf-btn--primary">Sign in</button>

        <p class="cf-small cf-muted">
            New here? <a href="{{ route('register') }}">Create an account</a>
        </p>
    </form>

</x-layouts.auth>
