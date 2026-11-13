<x-layouts.auth :title="'Two-step verification · ' . config('marketing.product.name', 'ChurchFlow')" heading="Two-step verification"
    subheading="Open your authenticator app and enter the 6-digit code for ChurchFlow.">

    <form method="POST" action="{{ route('two-factor.login') }}" class="cf-card" style="display:grid;gap:1rem">
        @csrf

        {{-- Neither box is `required`: the person may use EITHER the 6-digit code
             OR a recovery code. Browser-side `required` on a field inside a
             collapsed <details> also aborts the submit with "An invalid form
             control is not focusable", which is why the wrong-code message
             never appeared. The server rejects a submission with neither. --}}
        <x-auth.field name="code" label="6-digit code" type="text" inputmode="numeric" autocomplete="one-time-code"
            :autofocus="true" :required="false" />

        {{-- The recovery path. Hidden with <details> rather than a second page so
             a person holding a printed code does not have to sign in again to
             find the box. --}}
        <details>
            <summary class="cf-small" style="cursor:pointer">Use a recovery code instead</summary>
            <div style="margin-top:.6rem">
                <x-auth.field name="recovery_code" label="Recovery code" type="text" autocomplete="off"
                    :required="false" />
                <p class="cf-tiny cf-muted" style="margin:.3rem 0 0">
                    Each code works once. A used code is removed from your account.
                </p>
            </div>
        </details>

        <button type="submit" class="cf-btn cf-btn--primary">Verify</button>
    </form>

    {{-- Outside the form on purpose: a <form> inside a <form> is invalid HTML and
         browsers silently drop the inner one, so this button would have
         submitted the VERIFICATION form instead of signing out. --}}
    <form method="POST" action="{{ route('logout') }}" style="margin-top:.8rem;text-align:center">
        @csrf
        <button type="submit" class="cf-small"
            style="background:none;border:0;padding:0;color:var(--muted);cursor:pointer;text-decoration:underline">
            Sign in as a different account
        </button>
    </form>

</x-layouts.auth>
