<?php

namespace App\Http\Controllers;

use App\Domains\Communication\Services\SmsWalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * §17-18/§29: this is deliberately NOT the "buy SMS credits" checkout flow
 * — that's payment-gated and belongs in Phase 8 (SaaS billing), which will
 * call SmsWalletService::credit(..., type: 'purchase', ...) from a verified
 * payment webhook. This controller is the manual/administrative top-up
 * path (a platform admin comping credits, correcting a support issue),
 * useful on its own and exercised by the same ledger the real purchase
 * flow will use later.
 */
class SmsWalletController extends Controller
{
    public function __construct(private readonly SmsWalletService $wallets) {}

    public function show(Request $request): View
    {
        $this->authorize('viewAny', \App\Models\SmsCampaign::class); // same sms.manage gate

        $wallet = $this->wallets->walletFor($request->user()->church);

        return view('sms.wallet.show', [
            'wallet' => $wallet,
            'transactions' => $wallet->transactions()->latest()->paginate(25),
        ]);
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
