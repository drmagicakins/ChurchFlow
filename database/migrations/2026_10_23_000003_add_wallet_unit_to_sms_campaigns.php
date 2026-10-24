<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which wallet pays for this campaign (§17-19).
 *
 * A parish pastor's campaign is billed to their parish's wallet, never the main
 * church's. Recording the wallet ON the campaign — not inferring it at send
 * time — is what makes that honest: the estimate the pastor approved, the debit
 * that reserves it and the refunds for failed sends all point at the same
 * wallet, even if they are moved to another parish the next day.
 *
 * 0 = the church-wide wallet, matching SmsWallet::CHURCH_WIDE, so every existing
 * campaign keeps exactly the meaning it had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_unit_id')->default(0)->after('church_id');
        });

        // The same field on a credit PURCHASE, so paid-for units land in the
        // wallet that was actually bought for (0 = church-wide) even if the
        // payer's parish changes between checkout and webhook.
        Schema::table('checkouts', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_unit_id')->default(0)->after('sms_units');
        });
    }

    public function down(): void
    {
        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropColumn('wallet_unit_id');
        });

        Schema::table('sms_campaigns', function (Blueprint $table) {
            $table->dropColumn('wallet_unit_id');
        });
    }
};
