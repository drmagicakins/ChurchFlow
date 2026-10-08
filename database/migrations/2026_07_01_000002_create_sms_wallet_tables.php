<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->unique()->constrained('churches')->cascadeOnDelete();
            $table->unsignedInteger('balance_units')->default(0);
            $table->timestamps();
        });

        // The full ledger — every unit ever added or removed, with why.
        // balance_units on the wallet is a cache of this ledger's running
        // total, always updated in the same DB transaction as the ledger
        // row that changes it (see SmsWalletService) — never adjusted on
        // its own the way the legacy app mutated loans_svp.total_repay.
        Schema::create('sms_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('sms_wallet_id')->constrained('sms_wallets')->cascadeOnDelete();

            $table->string('type'); // purchase|debit|refund|adjustment
            $table->integer('units'); // positive for credit-adding types, negative for debit
            $table->unsignedInteger('balance_after');
            $table->string('reference')->nullable(); // e.g. "campaign:12", "payment:abc123"
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['church_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_transactions');
        Schema::dropIfExists('sms_wallets');
    }
};
