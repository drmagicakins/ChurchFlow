<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parish SMS wallets (§17-19).
 *
 * A parish pastor must not be able to spend the main church's SMS credit —
 * they buy their own. That is a wallet PER WALLET OWNER, not a second balance
 * column: the ledger, the reservation discipline and the refund-on-failure rule
 * in SmsWalletService all stay exactly as they are.
 *
 * `organizational_unit_id` null = the church's own wallet, i.e. exactly the row
 * every existing deployment already has. Non-null = that parish's wallet.
 *
 * The old UNIQUE(church_id) index has to become UNIQUE(church_id,
 * organizational_unit_id) or a church could only ever have one wallet. On MySQL
 * two NULLs are distinct, so "one church-wide wallet" is enforced with a
 * sentinel of 0 instead of NULL — see walletFor(). `wallets_owner_unique` is
 * exactly that pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_wallets', function (Blueprint $table) {
            $table->dropUnique('sms_wallets_church_id_unique');
        });

        Schema::table('sms_wallets', function (Blueprint $table) {
            // 0 = church-wide wallet; a real id = that parish's wallet.
            $table->unsignedBigInteger('organizational_unit_id')->default(0)->after('church_id');

            $table->unique(['church_id', 'organizational_unit_id'], 'sms_wallets_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sms_wallets', function (Blueprint $table) {
            $table->dropUnique('sms_wallets_owner_unique');
        });

        // Collapse every parish wallet's balance into its church wallet BEFORE
        // the column goes, so no purchased units are thrown away. This runs as
        // one statement per church, not as a loop over rows.
        foreach (\Illuminate\Support\Facades\DB::table('sms_wallets')
            ->where('organizational_unit_id', '!=', 0)
            ->get() as $parishWallet) {
            \Illuminate\Support\Facades\DB::table('sms_wallets')
                ->where('church_id', $parishWallet->church_id)
                ->where('organizational_unit_id', 0)
                ->increment('balance_units', $parishWallet->balance_units);

            \Illuminate\Support\Facades\DB::table('sms_wallets')
                ->where('id', $parishWallet->id)
                ->update(['balance_units' => 0]);
        }

        Schema::table('sms_wallets', function (Blueprint $table) {
            $table->dropColumn('organizational_unit_id');
        });

        Schema::table('sms_wallets', function (Blueprint $table) {
            $table->unique('church_id');
        });
    }
};
