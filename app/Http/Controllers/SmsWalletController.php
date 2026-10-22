<?php

namespace App\Http\Controllers;

use App\Domains\Communication\Services\SmsWalletService;
use App\Domains\PlatformAdmin\Services\PlatformSettingsService;
use App\Domains\Subscriptions\Services\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * §17-18/§29: the SMS wallet — balance, ledger and top-up.
 *
 * Two ways to add credits, deliberately kept apart:
 *
 *  1. `purchase()` — the real, payment-gated top-up. It starts a
 *     `sms_credits` checkout through the same PaymentGatewayInterface the
 *     subscription flow uses (Paystack/Flutterwave behind one interface),
 *     and the wallet is only credited from the verified webhook / return
 *     trip (CreditSmsWalletFromCheckout). Money first, credits second.
 *
 *  2. `credit()` — the manual/administrative path (a platform admin comping
 *     credits or correcting a support issue), which is exercised by the same
 *     ledger.
 */
class SmsWalletController extends Controller
{
    public function __construct(
        private readonly SmsWalletService $wallets,
        private readonly CheckoutService $checkouts,
        private readonly PlatformSettingsService $settings,
    ) {}

    public function show(Request $request): View
    {
        $this->authorize('viewAny', \App\Models\SmsCampaign::class); // same sms.manage gate

        $wallet = $this->wallets->walletFor($request->user()->church);

        return view('sms.wallet.show', [
            'wallet' => $wallet,
            'transactions' => $wallet->transactions()->latest('id')->paginate(25),
            'canPurchase' => $request->user()->hasPermission('billing.manage'),
            'pricePerUnit' => $this->settings->smsPricePerUnit(),
            'packages' => [1000, 5000, 10000, 25000],
        ]);
    }

    /** §17-18: buy credits with real money via the payment gateway. */
    public function purchase(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', \App\Models\SmsCampaign::class);

        abort_unless($request->user()->hasPermission('billing.manage'), 403,
            'You do not have permission to buy SMS credits.');

        $data = $request->validate(['package' => ['required', 'in:1000,5000,10000,25000']]);

        $pricePerUnit = $this->settings->smsPricePerUnit();
        abort_if($pricePerUnit === null, 503, 'SMS pricing has not been configured by the platform administrator.');

        $units = (int) $data['package'];
        $amount = bcmul((string) $units, (string) $pricePerUnit, 2);

        [$checkout, $redirectUrl] = $this->checkouts->startSmsCreditsCheckout(
            $request->user(), $request->user()->church, $units, $amount,
        );

        return redirect()->away($redirectUrl);
    }

    public function credit(Request $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\SmsCampaign::class); // same sms.manage gate

        $data = $request->validate([
            'units' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $wallet = $this->wallets->walletFor($request->user()->church);
        $this->wallets->credit($wallet, $data['units'], 'adjustment', null, $data['description'] ?? 'Manual top-up', $request->user());

        return back()->with('status', "{$data['units']} SMS credits added.");
    }
}
