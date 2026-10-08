<?php

namespace App\Http\Controllers;

use App\Domains\Subscriptions\Gateways\PaymentGatewayInterface;
use App\Domains\Subscriptions\Services\CheckoutService;
use App\Domains\Subscriptions\Services\PaymentWebhookHandler;
use App\Models\Checkout;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Routes here sit behind 'auth' ONLY — deliberately not IdentifyTenant —
 * because the person using them has no church yet (§2). Every query below
 * therefore filters by user_id explicitly rather than relying on a tenant
 * scope that cannot exist for a not-yet-activated account.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkouts,
        private readonly PaymentGatewayInterface $gateway,
        private readonly PaymentWebhookHandler $webhooks,
    ) {}

    /** §4: order summary before any money moves. */
    public function review(Request $request, Plan $plan): View
    {
        abort_unless($plan->is_active, 404);

        $interval = $request->string('interval', 'monthly')->toString();

        return view('checkout.review', [
            'plan' => $plan,
            'interval' => $interval,
            'price' => $plan->priceFor($interval),
        ]);
    }

    public function start(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless($plan->is_active, 404);
        abort_if($request->user()->church_id, 409, 'This account already has a church.');

        $data = $request->validate(['interval' => ['required', 'in:monthly,yearly']]);

        [$checkout, $redirectUrl] = $this->checkouts->startSubscriptionCheckout($request->user(), $plan, $data['interval']);

        return redirect()->away($redirectUrl);
    }

    /**
     * §6: the person landing back here after paying proves NOTHING by
     * itself — this asks the gateway server-to-server whether the payment
     * actually succeeded, then feeds the same normalized event a real
     * webhook would send into the same idempotent handler, so this
     * "verify on return" path and the true webhook path can race each other
     * safely (§33) without either being able to double-activate.
     */
    public function verify(Request $request, Checkout $checkout): RedirectResponse
    {
        abort_unless($checkout->user_id === $request->user()->id, 403);
        abort_unless($checkout->provider_reference, 409, 'This checkout was never started.');

        $result = $this->gateway->verify($checkout->provider_reference);

        $this->webhooks->handle([
            'event' => $result->success ? 'payment.success' : 'payment.failed',
            'provider' => $checkout->provider,
            'provider_event_id' => 'verify:'.$checkout->provider_reference.':'.($result->success ? 'ok' : 'fail'),
            'reference' => $checkout->provider_reference,
        ]);

        $checkout->refresh();

        if ($checkout->status === 'completed') {
            $request->user()->refresh();

            if ($checkout->purpose === 'sms_credits') {
                return redirect()->route('billing.show')->with('status', "{$checkout->sms_units} SMS credits added to your wallet.");
            }

            return redirect()->route('dashboard')->with('status', 'Welcome! Your subscription is active. Let\'s set up your church.');
        }

        return redirect()->route('checkout.failed', $checkout);
    }

    /** §7: no church was created; the same checkout can simply be retried. */
    public function failed(Request $request, Checkout $checkout): View
    {
        abort_unless($checkout->user_id === $request->user()->id, 403);

        return view('checkout.failed', compact('checkout'));
    }

    public function retry(Request $request, Checkout $checkout): RedirectResponse
    {
        abort_unless($checkout->user_id === $request->user()->id, 403);
        abort_unless($checkout->status === 'pending', 409, 'Only a pending checkout can be retried.');

        $redirectUrl = $this->gateway->initiate($checkout);

        return redirect()->away($redirectUrl);
    }
}
