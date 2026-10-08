<x-marketing-layout :title="'Review your order · '.config('marketing.product.name')">
    <section class="mk-section">
        <div class="mk-shell" style="max-width:34rem;margin-inline:auto">
            <h1>Review your order</h1>
            <p class="cf-small cf-muted" style="margin-top:.4rem">
                Your church is created as soon as this payment is verified.
            </p>

            <div class="cf-card" style="margin-top:1.4rem">
                <div class="cf-stat">
                    <span class="cf-stat__label">{{ $plan->name }} plan</span>
                    <span class="cf-stat__value">{{ $plan->currency }} {{ $price }}</span>
                    <span class="cf-stat__hint">Billed {{ $interval }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('checkout.start', $plan) }}" style="margin-top:1.2rem">@csrf
                <input type="hidden" name="interval" value="{{ $interval }}">
                <button type="submit" class="cf-btn cf-btn--primary">Proceed to payment</button>
                <a href="{{ route('plans.index') }}" class="cf-btn cf-btn--secondary">Back to plans</a>
            </form>
        </div>
    </section>
</x-marketing-layout>
