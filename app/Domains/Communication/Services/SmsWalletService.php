<?php

namespace App\Domains\Communication\Services;

use App\Models\Church;
use App\Models\SmsWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every balance change goes through here, and every one of them writes a
 * ledger row in the same transaction — §27/§29 are explicit that a bare
 * balance mutation with no record of why is never acceptable, the same
 * principle Phase 4 applied to financial transactions and Phase 6 applied
 * to pastoral notes.
 */
class SmsWalletService
{
    /**
     * The wallet a user is allowed to spend (§17-19).
     *
     * THE RULE THIS METHOD EXISTS FOR: a parish pastor must not be able to
     * reach the main church's SMS credit. They subscribe for their own. So the
     * wallet is chosen from the USER, not from the church:
     *
     *   - a parish-scoped pastor  → their parish's wallet;
     *   - the owner / registrar,
     *     or anyone church-wide   → the church-wide wallet.
     *
     * Every caller that sends SMS on behalf of a person should pass them in.
     * The old church-only signature still works and still means "the church
     * wallet", which is what the payment webhook and the campaign sender (which
     * owns the campaign's church, not a user) need.
     */
    public function walletForUser(?User $user, ?Church $church = null): SmsWallet
    {
        $church = $church ?? $user?->church;
        abort_if($church === null, 500, 'No church context for this SMS wallet.');

        if ($user && $user->isParishScoped() && $user->organizational_unit_id) {
            return $this->walletFor($church, (int) $user->organizational_unit_id);
        }

        return $this->walletFor($church);
    }

    /**
     * The wallet for a church as a whole, or for one of its parishes.
     *
     * `$organizationalUnitId` omitted/null means the church-wide wallet — the
     * exact row every existing deployment already has on its original
     * UNIQUE(church_id) index, so upgrading cannot orphan a balance.
     */
    public function walletFor(Church $church, ?int $organizationalUnitId = null): SmsWallet
    {
        return SmsWallet::withoutGlobalScopes()->firstOrCreate(
            [
                'church_id' => $church->id,
                'organizational_unit_id' => $organizationalUnitId ?? SmsWallet::CHURCH_WIDE,
            ],
            ['balance_units' => 0],
        );
    }

    /**
     * §Purchasing SMS credits with real money is a Phase 8 (billing) concern
     * — this method is what a verified payment webhook calls once that
     * exists. Also usable directly for a platform-admin manual top-up.
     */
    public function credit(SmsWallet $wallet, int $units, string $type, ?string $reference = null, ?string $description = null, ?User $by = null): SmsWallet
    {
        abort_if($units <= 0, 422, 'Credit amount must be positive.');
        abort_unless(in_array($type, ['purchase', 'refund', 'adjustment'], true), 422, 'Invalid credit type.');

        return DB::transaction(function () use ($wallet, $units, $type, $reference, $description, $by) {
            $wallet = SmsWallet::withoutGlobalScopes()->lockForUpdate()->find($wallet->id);
            $newBalance = $wallet->balance_units + $units;

            $wallet->update(['balance_units' => $newBalance]);

            $wallet->transactions()->create([
                'church_id' => $wallet->church_id,
                'type' => $type,
                'units' => $units,
                'balance_after' => $newBalance,
                'reference' => $reference,
                'description' => $description,
                'created_by' => $by?->id,
            ]);

            return $wallet;
        });
    }

    /**
     * Reserves (debits) units up front, before any message is actually
     * sent — see SmsCampaignService::confirmAndQueue(). This is what makes
     * two campaigns confirmed at the same moment unable to both spend the
     * same units (§26): the debit and the balance check happen inside one
     * locked transaction, not as a separate "check, then spend" pair of
     * steps that could interleave.
     */
    public function debit(SmsWallet $wallet, int $units, string $reference, ?string $description = null): SmsWallet
    {
        abort_if($units <= 0, 422, 'Debit amount must be positive.');

        return DB::transaction(function () use ($wallet, $units, $reference, $description) {
            $wallet = SmsWallet::withoutGlobalScopes()->lockForUpdate()->find($wallet->id);

            abort_if($wallet->balance_units < $units, 422, 'Insufficient SMS credits.');

            $newBalance = $wallet->balance_units - $units;
            $wallet->update(['balance_units' => $newBalance]);

            $wallet->transactions()->create([
                'church_id' => $wallet->church_id,
                'type' => 'debit',
                'units' => -$units,
                'balance_after' => $newBalance,
                'reference' => $reference,
                'description' => $description,
            ]);

            return $wallet;
        });
    }

    /** §27: a failed message the provider didn't charge for returns its unit(s) to the wallet, with a reason on record. */
    public function refund(SmsWallet $wallet, int $units, string $reference, string $description): SmsWallet
    {
        return $this->credit($wallet, $units, 'refund', $reference, $description);
    }
}
