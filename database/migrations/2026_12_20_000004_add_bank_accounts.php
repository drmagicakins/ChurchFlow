<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two related additions to the finance area:
 *
 * 1. `financial_accounts` gains the bank-detail columns the "Add account"
 *    form already collects (bank name, account name, account number). They
 *    were being posted by the form but had nowhere to be stored.
 *
 * 2. `bank_accounts` is the pastor's own settlement account — where a
 *    subvention or remittance should be paid. It is deliberately separate
 *    from a church's `financial_accounts` (which are the church's own
 *    funds), because a pastor enters their PERSONAL account here and the
 *    super-admin/registrar reads it to make the transfer. One account per
 *    user per church.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('type');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // The 3-digit CBN bank code is the stable identifier; bank_name is
            // the human label from the dropdown at the time it was chosen.
            $table->string('bank_code', 10);
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number', 20);

            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // One settlement account per pastor per church.
            $table->unique(['church_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');

        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_name', 'bank_account_number']);
        });
    }
};
