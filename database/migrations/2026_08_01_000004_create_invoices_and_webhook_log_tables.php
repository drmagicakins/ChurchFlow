<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('checkout_id')->nullable()->constrained('checkouts')->nullOnDelete();

            // §31: subscription revenue and SMS revenue must stay
            // financially distinguishable — `type` is what a billing report
            // groups by.
            $table->string('type'); // subscription|sms_credits
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('status')->default('paid'); // this app only ever creates an invoice once payment is verified
            $table->text('description')->nullable();

            $table->string('provider')->nullable();
            // A second, DB-enforced idempotency guard alongside the
            // payment_webhook_events log below and the Checkout.status
            // check in the activation actions — belt and suspenders on the
            // one thing §33 says must never happen twice.
            $table->string('provider_reference')->nullable()->unique();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'type']);
        });

        // §32/§33: every webhook delivery is logged here BEFORE any
        // business-table write, keyed uniquely on (provider,
        // provider_event_id) — a provider redelivering the exact same
        // event (which every real provider's docs warn will happen) is a
        // no-op the instant it's looked up, before any money or credits
        // move a second time.
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('provider_event_id');
            $table->string('event_type'); // normalized: payment.success|payment.failed|...
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('invoices');
    }
};
