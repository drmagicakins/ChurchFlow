<x-layouts.auth :title="'Confirm your email · ' . config('marketing.product.name', 'ChurchFlow')" heading="Confirm your email address"
    subheading="We sent a confirmation link to your inbox. Open it to finish activating your account.">

    @php
        $user = auth()->user();

        // The "Not now" escape is offered ONLY to an account created before
        // verification existed — see EnsureEmailIsVerified. A new account is
        // never told it may skip.
        $isLegacy =
            $user?->created_at !== null &&
            $user->created_at->lt(\App\Http\Middleware\EnsureEmailIsVerified::verificationShippedAt()) &&
            !$user->hasVerifiedEmail();
    @endphp

    <div class="cf-card" style="display:grid;gap:.8rem">
        <h2 class="cf-form__title">Check your inbox</h2>

        {{-- The address is shown so a person notices a typo immediately — and so
             they know WHICH mailbox to open when they have several. --}}
        <p class="cf-small cf-muted" style="margin:0">
            A confirmation link was sent to <strong>{{ $email ?? $user?->email }}</strong>.
            It expires in {{ (int) config('auth.verification.expire', 60) }} minutes and can be used once.
        </p>

        <p class="cf-small cf-muted" style="margin:0">
            Nothing arrived? Check your spam or promotions folder first — confirmation messages are often filtered
            there.
        </p>
    </div>

    <form method="POST" action="{{ route('verification.send') }}" class="cf-card"
        style="display:grid;gap:.8rem;margin-top:1rem">
        @csrf
        <p class="cf-small cf-muted" style="margin:0">
            Still nothing, or the link has expired? Send a new one.
        </p>
        <button type="submit" class="cf-btn cf-btn--primary">Send a new link</button>
    </form>

    @if ($isLegacy)
        {{-- Legacy accounts only: they were working before this rule existed, so
             they get a choice rather than an ultimatum. The dashboard then keeps a
             visible reminder until the address is confirmed. --}}
        <form method="POST" action="{{ route('verification.defer') }}" style="margin-top:.8rem;text-align:center">
            @csrf
            <button type="submit" class="cf-small"
                style="background:none;border:0;padding:0;color:var(--muted);cursor:pointer;text-decoration:underline">
                Continue without confirming for now
            </button>
        </form>
    @endif

    <form method="POST" action="{{ route('logout') }}" style="margin-top:.8rem;text-align:center">
        @csrf
        <button type="submit" class="cf-small"
            style="background:none;border:0;padding:0;color:var(--muted);cursor:pointer;text-decoration:underline">
            Sign in as a different account
        </button>
    </form>

</x-layouts.auth>
