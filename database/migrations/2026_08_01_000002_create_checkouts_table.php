<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();

            // Nullable: a subscription checkout happens BEFORE a church
            // exists (§2 — user account vs. church account), so church_id
            // is set only once payment is verified and the church is
            // created. An SMS-credit checkout already has a church.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('church_id')->nullable()->constrained('churches')->nullOnDelete();

            $table->string('purpose'); // subscription|sms_credits

            // subscription-purpose fields
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('billing_interval')->nullable(); // monthly|yearly

            // sms_credits-purpose fields
            $table->unsignedInteger('sms_units')->nullable();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('NGN');

            $table->string('status')->default('pending'); // pending|expired|completed
            $table->string('provider')->default('null'); // paystack|flutterwave|null (this scaffold's test/dev gateway)
            $table->string('provider_reference')->nullable()->unique();

            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkouts');
    }
};
