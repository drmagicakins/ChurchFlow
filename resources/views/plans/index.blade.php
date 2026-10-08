<x-marketing-layout :title="'Plans & pricing · '.config('marketing.product.name')">
    <section class="mk-section">
        <div class="mk-shell">
            <h1>Choose your plan</h1>
            <p>
                Email notifications are included with every plan. Bulk SMS is
                available separately through SMS credits.
            </p>

            <ul>
            @foreach($plans as $plan)
                <li>
                    <strong>{{ $plan->name }}</strong> — {{ $plan->currency }} {{ $plan->monthly_price }}/month
                    (members: {{ $plan->max_members ?? 'unlimited' }}, branches: {{ $plan->max_branches ?? 'unlimited' }},
                    admins: {{ $plan->max_admins ?? 'unlimited' }})
                    @auth
                        <a href="{{ route('checkout.review', $plan) }}">Choose plan</a>
                    @else
                        <a href="{{ route('register') }}">Get started</a>
                    @endauth
                </li>
            @endforeach
            </ul>
        </div>
    </section>
</x-marketing-layout>
