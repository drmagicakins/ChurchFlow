<x-marketing-layout :title="'Payment failed · '.config('marketing.product.name')">
    <section class="mk-section">
        <div class="mk-shell" style="max-width:34rem;margin-inline:auto">
            <h1>Payment failed</h1>
            <p class="cf-small cf-muted" style="margin-top:.4rem">
                Your payment could not be completed. No church account has been activated,
                and nothing has been charged.
            </p>

            <div class="cf-cta-row" style="margin-top:1.4rem">
                <form method="POST" action="{{ route('checkout.retry', $checkout) }}">@csrf
                    <button type="submit" class="cf-btn cf-btn--primary">Try again</button>
                </form>
                <a href="{{ route('plans.index') }}" class="cf-btn cf-btn--secondary">Change plan</a>
            </div>
        </div>
    </section>
</x-marketing-layout>
