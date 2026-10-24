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

        // §17-19: a parish pastor sees their PARISH wallet, never the main
        // church's. The screen used to show the church balance to everybody,
        // which is exactly what made it possible to spend the wrong credits.
        $wallet = $this->wallets->walletForUser($request->user());

        $isParishWallet = (int) $wallet->organizational_unit_id !== \App\Models\SmsWallet::CHURCH_WIDE;

        return view('sms.wallet.show', [
            'wallet' => $wallet,
            'isParishWallet' => $isParishWallet,
            'transactions' => $wallet->transactions()->latest('id')->paginate(25),
            // §17-19: BUYING credits is this person's own decision. A parish
            // pastor subscribes for their parish, so they do not need the
            // church-wide billing permission to top up their own wallet —
            // otherwise "parish pastor should subscribe for their own SMS"
            // would have no screen to do it on. Spending the church's money on
            // the MAIN wallet still needs billing.manage.
            'canPurchase' => $isParishWallet || $request->user()->hasPermission('billing.manage'),
            'pricePerUnit' => $this->settings->smsPricePerUnit(),
            'packages' => [1000, 5000, 10000, 25000],
        ]);
    }

    /** §17-18: buy credits with real money via the payment gateway. */
    public function purchase(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', \App\Models\SmsCampaign::class);

        // Same rule as the screen: topping up a parish wallet is the parish's
        // own call; topping up the main church's needs billing.manage.
        $wallet = $this->wallets->walletForUser($request->user());
        $isParishWallet = (int) $wallet->organizational_unit_id !== \App\Models\SmsWallet::CHURCH_WIDE;

        abort_unless($isParishWallet || $request->user()->hasPermission('billing.manage'), 403,
            'You do not have permission to buy SMS credits for the main church. Ask your church owner to top up.');

        $data = $request->validate(['package' => ['required', 'in:1000,5000,10000,25000']]);

        $pricePerUnit = $this->settings->smsPricePerUnit();
        abort_if($pricePerUnit === null, 503, 'SMS pricing has not been configured by the platform administrator.');

        $units = (int) $data['package'];
        $amount = bcmul((string) $units, (string) $pricePerUnit, 2);

        [$checkout, $redirectUrl] = $this->checkouts->startSmsCreditsCheckout(
            $request->user(),
            $request->user()->church,
            $units,
            $amount,
            walletUnitId: (int) $wallet->organizational_unit_id,
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

        // The wallet the person may spend — so an administrative top-up by a
        // parish pastor lands in their parish, not in the main church.
        $wallet = $this->wallets->walletForUser($request->user());
        $this->wallets->credit($wallet, $data['units'], 'adjustment', null, $data['description'] ?? 'Manual top-up', $request->user());

        return back()->with('status', "{$data['units']} SMS credits added to {$wallet->ownerLabel()}.");
    }
}
