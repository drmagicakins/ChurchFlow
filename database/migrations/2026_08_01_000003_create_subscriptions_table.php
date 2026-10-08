<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();

            // §9: pending|active|past_due|grace_period|cancelled|expired|suspended.
            // This table is the SOURCE OF TRUTH; churches.status is a
            // denormalized cache of it, kept in sync exclusively by
            // SubscriptionService — the same cached-column pattern Phase 4
            // used for Transaction.approval_status.
            $table->string('status')->default('pending');
            $table->string('billing_interval'); // monthly|yearly

            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->string('provider')->nullable();
            $table->string('provider_subscription_reference')->nullable()->unique();

            $table->timestamps();

            $table->index(['church_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
