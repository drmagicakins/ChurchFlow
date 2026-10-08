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
    public function walletFor(Church $church): SmsWallet
    {
        return SmsWallet::withoutGlobalScopes()->firstOrCreate(
            ['church_id' => $church->id],
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
